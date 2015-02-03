<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\JsonLdApiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The configuration of the bundle.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('dunglas_json_ld_api');

        $rootNode
            ->children()
                ->scalarNode('title')->cannotBeEmpty()->isRequired()->info('API\'s title.')->end()
                ->scalarNode('description')->cannotBeEmpty()->isRequired()->info('API\'s description.')->end()
                ->integerNode('elements_by_page')->min(1)->defaultValue(100)->cannotBeEmpty()->info('The number of elements by page in collections.')->end()
                ->booleanNode('enable_fos_user_event_subscriber')->defaultFalse()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
