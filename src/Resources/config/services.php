<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->public();

    $services->load('App\\DoctrineEntityDtoBundle\\', '../../../src')
        ->exclude('../../../src/Attribute')
        ->exclude('../../../src/DependencyInjection')
        ->exclude('../../../src/Exception')
        ->exclude('../../../src/Hydration')
        ->exclude('../../../src/Resources');
};
