<?php

declare(strict_types=1);

namespace App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\Command;

use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\UserService;
use App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\Service\SubtreeLayoutRuleCopier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'se7enx:nglayouts:copy-subtree-with-layouts',
    description: 'Copies an Ibexa content subtree and duplicates all Netgen Layouts resolver rules for the new location IDs.',
)]
final class CopySubtreeWithLayoutsCommand extends Command
{
    /**
     * Ibexa admin user ID (8) is used so the copy has full repository access.
     */
    private const ADMIN_USER_ID = 14;

    public function __construct(
        private readonly LocationService $locationService,
        private readonly SubtreeLayoutRuleCopier $copier,
        private readonly PermissionResolver $permissionResolver,
        private readonly UserService $userService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'source-location-id',
                InputArgument::REQUIRED,
                'Location ID of the root of the subtree to copy',
            )
            ->addArgument(
                'target-parent-location-id',
                InputArgument::REQUIRED,
                'Location ID of the parent that will receive the copied subtree',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Print what would be done without creating any rules or copying any content',
            )
            ->addOption(
                'skip-layout-rules',
                null,
                InputOption::VALUE_NONE,
                'Copy the subtree but do NOT duplicate layout resolver rules',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourceLocationId = (int) $input->getArgument('source-location-id');
        $targetParentLocationId = (int) $input->getArgument('target-parent-location-id');
        $dryRun = (bool) $input->getOption('dry-run');
        $skipLayoutRules = (bool) $input->getOption('skip-layout-rules');

        if ($dryRun) {
            $io->note('DRY-RUN mode — no content will be written to the repository.');
        }

        // Run as admin to ensure the copy has all required permissions
        $adminUser = $this->userService->loadUser(self::ADMIN_USER_ID);
        $this->permissionResolver->setCurrentUserReference($adminUser);

        // Load locations for validation / display
        try {
            $sourceLocation = $this->locationService->loadLocation($sourceLocationId);
            $targetParentLocation = $this->locationService->loadLocation($targetParentLocationId);
        } catch (\Exception $e) {
            $io->error(sprintf('Could not load location: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->section('Subtree Copy with Netgen Layouts');
        $io->definitionList(
            ['Source location' => sprintf('%d — %s', $sourceLocation->id, $sourceLocation->contentInfo->name)],
            ['Target parent' => sprintf('%d — %s', $targetParentLocation->id, $targetParentLocation->contentInfo->name)],
        );

        // -----------------------------------------------------------------
        // Step 1: Copy the Ibexa content subtree
        // -----------------------------------------------------------------
        $io->section('Step 1: Copying Ibexa subtree');

        if ($dryRun) {
            $io->writeln('[DRY-RUN] Would call LocationService::copySubtree()');
            $io->writeln('[DRY-RUN] Skipping layout rule copy (no new location IDs available in dry-run).');
            $io->success('Dry-run complete — no changes made.');
            return Command::SUCCESS;
        }

        try {
            $newRootLocation = $this->locationService->copySubtree($sourceLocation, $targetParentLocation);
        } catch (\Exception $e) {
            $io->error(sprintf('Subtree copy failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->writeln(sprintf(
            ' New root location ID: <info>%d</info> — %s',
            $newRootLocation->id,
            $newRootLocation->contentInfo->name,
        ));

        if ($skipLayoutRules) {
            $io->success(sprintf(
                'Subtree copied successfully (layout rules skipped). New root: location %d.',
                $newRootLocation->id,
            ));
            return Command::SUCCESS;
        }

        // -----------------------------------------------------------------
        // Step 2: Build old→new location ID map
        // -----------------------------------------------------------------
        $io->section('Step 2: Building location ID map (parallel tree traversal)');

        $locationMap = $this->copier->buildLocationMap(
            $sourceLocation->id,
            $newRootLocation->id,
            $output,
        );

        $io->writeln(sprintf(' Mapped <info>%d</info> location(s).', count($locationMap)));

        // -----------------------------------------------------------------
        // Step 3: Duplicate Netgen Layouts rules
        // -----------------------------------------------------------------
        $io->section('Step 3: Duplicating Netgen Layouts resolver rules');

        $stats = $this->copier->copyRules($locationMap, $output, false);

        // -----------------------------------------------------------------
        // Summary
        // -----------------------------------------------------------------
        $io->success(sprintf(
            'Done. Subtree copied to location %d. Layout rules created: %d, skipped: %d.',
            $newRootLocation->id,
            $stats['created'],
            $stats['skipped'],
        ));

        $io->table(
            ['Old location ID', 'New location ID'],
            array_map(
                static fn(int $old, int $new): array => [$old, $new],
                array_keys($locationMap),
                array_values($locationMap),
            ),
        );

        return Command::SUCCESS;
    }
}
