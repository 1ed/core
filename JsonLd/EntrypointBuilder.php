<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\JsonLdApiBundle\JsonLd;

use Dunglas\JsonLdApiBundle\Api\ResourceCollectionInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * API Entrypoint builder.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class EntrypointBuilder
{
    /**
     * @var ResourceCollectionInterface
     */
    private $resourceCollection;
    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        ResourceCollectionInterface $resourceCollection,
        RouterInterface $router
    ) {
        $this->resourceCollection = $resourceCollection;
        $this->router = $router;
    }

    /**
     * Gets the entrypoint of the API.
     *
     * return array
     */
    public function getEntrypoint()
    {
        $entrypoint = [
            '@context' => $this->router->generate('api_json_ld_entrypoint_context'),
            '@id' => $this->router->generate('api_json_ld_entrypoint'),
            '@type' => 'Entrypoint',
        ];

        foreach ($this->resourceCollection as $resource) {
            $entrypoint[lcfirst($resource->getShortName())] = $this->router->generate($resource);
        }

        return $entrypoint;
    }
}
