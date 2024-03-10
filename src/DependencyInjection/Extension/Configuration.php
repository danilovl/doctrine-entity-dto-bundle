<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\DependencyInjection\Extension;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const string ALIAS = 'danilovl_doctrine_entity_dto';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('isEnableEntityDTO')->defaultFalse()->end()
                ->scalarNode('isAsEntityDTO')->defaultFalse()->end()
                ->arrayNode('entityDTO')
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('isEnableScalarDTO')->defaultFalse()->end()
                ->scalarNode('isAsScalarDTO')->defaultFalse()->end()
                ->arrayNode('scalarDTO')
                    ->prototype('scalar')->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
