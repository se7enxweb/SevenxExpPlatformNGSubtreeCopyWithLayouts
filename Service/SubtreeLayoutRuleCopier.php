<?php

declare(strict_types=1);

namespace App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\Service;

use Doctrine\DBAL\Connection;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Netgen\Layouts\API\Service\LayoutResolverService;
use Netgen\Layouts\API\Values\LayoutResolver\RuleGroup;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Copies Netgen Layouts resolver rules from an original subtree to a
 * newly copied subtree by building a 1-to-1 old→new location ID map
 * via parallel tree traversal, then duplicating every matching rule.
 *
 * Supported target types carried over:
 *   - ibexa_location  (exact location match rules)
 *   - ibexa_subtree   (subtree match rules — only for the copy root)
 */
final class SubtreeLayoutRuleCopier
{
    /**
     * Root rule group UUID is fixed in nglayouts schema (all rules live under it).
     */
    private const ROOT_RULE_GROUP_UUID = '00000000-0000-0000-0000-000000000000';

    public function __construct(
        private readonly LocationService $locationService,
        private readonly LayoutResolverService $layoutResolverService,
        private readonly Connection $connection,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Builds a map of old location ID → new location ID by traversing both
     * trees in parallel.  The copy must have been performed before calling this.
     *
     * @return array<int,int>  [oldLocationId => newLocationId]
     */
    public function buildLocationMap(int $oldRootId, int $newRootId, OutputInterface $output): array
    {
        $map = [];
        $this->traverseAndMap($oldRootId, $newRootId, $map, $output);
        return $map;
    }

    /**
     * For every entry in $locationMap, look up any published nglayouts rules
     * targeting the OLD location (ibexa_location) and create equivalent rules
     * targeting the NEW location.
     *
     * Additionally, ibexa_subtree rules are copied for the source root only
     * (the first entry in the map).
     *
     * @param array<int,int> $locationMap  [oldId => newId]
     *
     * @return array{created: int, skipped: int}
     */
    public function copyRules(array $locationMap, OutputInterface $output, bool $dryRun = false): array
    {
        $rootGroup = $this->layoutResolverService->loadRuleGroup(
            Uuid::fromString(self::ROOT_RULE_GROUP_UUID),
        );

        $created = 0;
        $skipped = 0;
        $isFirst = true;

        foreach ($locationMap as $oldLocationId => $newLocationId) {
            // ibexa_location rules for every location in the subtree
            $locationRules = $this->fetchPublishedRulesForTarget('ibexa_location', (string) $oldLocationId);
            foreach ($locationRules as $rule) {
                $result = $this->duplicateRule(
                    $rootGroup,
                    'ibexa_location',
                    (string) $newLocationId,
                    $rule['layout_uuid'],
                    (int) $rule['priority'],
                    $output,
                    $dryRun,
                );
                $result ? ++$created : ++$skipped;
            }

            // ibexa_subtree rules — only meaningful for the copy root
            if ($isFirst) {
                $subtreeRules = $this->fetchPublishedRulesForTarget('ibexa_subtree', (string) $oldLocationId);
                foreach ($subtreeRules as $rule) {
                    $result = $this->duplicateRule(
                        $rootGroup,
                        'ibexa_subtree',
                        (string) $newLocationId,
                        $rule['layout_uuid'],
                        (int) $rule['priority'],
                        $output,
                        $dryRun,
                    );
                    $result ? ++$created : ++$skipped;
                }
                $isFirst = false;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Recursively walks old and new subtrees in parallel, populating $map.
     *
     * @param array<int,int> $map  Passed by reference
     */
    private function traverseAndMap(int $oldId, int $newId, array &$map, OutputInterface $output): void
    {
        $map[$oldId] = $newId;

        $oldChildren = $this->locationService->loadLocationChildren(
            $this->locationService->loadLocation($oldId),
        );
        $newChildren = $this->locationService->loadLocationChildren(
            $this->locationService->loadLocation($newId),
        );

        $oldList = $oldChildren->locations;
        $newList = $newChildren->locations;

        if (count($oldList) !== count($newList)) {
            $output->writeln(sprintf(
                '<comment>Warning: child count mismatch at old=%d (old=%d, new=%d children). Some children may be skipped.</comment>',
                $oldId,
                count($oldList),
                count($newList),
            ));
        }

        $limit = min(count($oldList), count($newList));
        for ($i = 0; $i < $limit; ++$i) {
            $this->traverseAndMap($oldList[$i]->id, $newList[$i]->id, $map, $output);
        }
    }

    /**
     * Returns all published nglayouts rules with a target of given type and value.
     *
     * Direct DBAL query because the LayoutResolverService API does not expose
     * a "find rules by target value" lookup — only forward resolution (matching).
     *
     * @return list<array{layout_uuid: string, priority: int}>
     */
    private function fetchPublishedRulesForTarget(string $targetType, string $targetValue): array
    {
        // status=1 means PUBLISHED in nglayouts schema
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('r.layout_uuid', 'rd.priority')
            ->from('nglayouts_rule_target', 'rt')
            ->join('rt', 'nglayouts_rule', 'r', 'r.id = rt.rule_id AND r.status = 1')
            ->join('r', 'nglayouts_rule_data', 'rd', 'rd.rule_id = r.id')
            ->where('rt.status = 1')
            ->andWhere('rt.type = :type')
            ->andWhere('rt.value = :value')
            ->andWhere('rd.enabled = 1')
            ->andWhere('r.layout_uuid IS NOT NULL')
            ->setParameter('type', $targetType)
            ->setParameter('value', $targetValue);

        /** @var list<array{layout_uuid: string, priority: int}> */
        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Creates a new published rule targeting $newLocationId with the same
     * layout UUID as the original rule.  Priority is decremented by 1 to avoid
     * collisions with the original (original takes precedence).
     *
     * Returns true when rule was created, false when skipped (dry-run or
     * duplicate detected).
     */
    private function duplicateRule(
        RuleGroup $rootGroup,
        string $targetType,
        string $newTargetValue,
        string $layoutUuid,
        int $originalPriority,
        OutputInterface $output,
        bool $dryRun,
    ): bool {
        $newPriority = $originalPriority - 1;

        $output->writeln(sprintf(
            '  %s new rule: target=%s:%s → layout=%s @ priority=%d',
            $dryRun ? '[DRY-RUN] Would create' : 'Creating',
            $targetType,
            $newTargetValue,
            $layoutUuid,
            $newPriority,
        ));

        if ($dryRun) {
            return true;
        }

        // Create rule draft
        $createStruct = $this->layoutResolverService->newRuleCreateStruct();
        $createStruct->layoutId = Uuid::fromString($layoutUuid);
        $createStruct->priority = $newPriority;
        $createStruct->isEnabled = true;
        $createStruct->description = sprintf(
            'Auto-created by SevenxExpPlatformNGSubtreeCopyWithLayouts — copy of rule targeting %s:%s',
            $targetType,
            $newTargetValue,
        );

        $ruleDraft = $this->layoutResolverService->createRule($createStruct, $rootGroup);

        // Add target
        $targetStruct = $this->layoutResolverService->newTargetCreateStruct($targetType);
        $targetStruct->value = $newTargetValue;
        $this->layoutResolverService->addTarget($ruleDraft, $targetStruct);

        // Publish rule
        $this->layoutResolverService->publishRule($ruleDraft);

        return true;
    }
}
