<?php

declare(strict_types=1);

namespace App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle
 *
 * Copies an Ibexa DXP content subtree and carries over all Netgen Layouts
 * layout-resolver rules (ibexa_location and ibexa_subtree targets) from the
 * original locations to the newly created ones, preserving section assignments
 * and object state metadata on each copied content item.
 *
 * @author se7enxweb
 */
final class SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle extends AbstractBundle
{
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->import(__DIR__ . '/Resources/config/services.yaml');
    }
}
