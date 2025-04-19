<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\DependencyInjection\Extension;

use Danilovl\DoctrineEntityDtoBundle\Service\ConfigurationService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;

class DoctrineEntityDtoExtension extends Extension
{
    private const string DIR_CONFIG = '/../../Resources/config';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration;
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . self::DIR_CONFIG));
        $loader->load('services.yaml');

        $configurationService = $container->getDefinition(ConfigurationService::class);
        $configurationService->setArguments([
            $config['isEnableEntityDTO'],
            $config['isEnableEntityRuntimeNameDTO'],
            $config['isAsEntityDTO'],
            $config['entityDTO'],
            $config['isEnableScalarDTO'],
            $config['isAsScalarDTO'],
            $config['scalarDTO']
        ]);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
