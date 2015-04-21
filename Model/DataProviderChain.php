<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\ApiBundle\Model;

use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * A chain of data providers.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DataProviderChain implements DataProviderInterface
{
    /**
     * @var ResourceCollectionInterface
     */
    private $resourceCollection;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var DataProviderInterface[]
     */
    private $dataProviders;

    /**
     * @param ResourceCollectionInterface $resourceCollection
     * @param RouterInterface             $router
     * @param DataProviderInterface[]     $dataProviders
     */
    public function __construct(ResourceCollectionInterface $resourceCollection, RouterInterface $router, array $dataProviders)
    {
        $this->resourceCollection = $resourceCollection;
        $this->router = $router;
        $this->dataProviders = $dataProviders;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(ResourceInterface $resource, $id, $fetchData = false)
    {
        foreach ($this->dataProviders as $dataProvider) {
            if ($dataProvider->supports($resource) && $result = $dataProvider->getItem($resource, $id, $fetchData)) {
                return $result;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemFromIri($iri, $fetchData = false)
    {
        try {
            $parameters = $this->router->match($iri);
        } catch (ResourceNotFoundException $e) {
            return;
        }

        if (
            !isset($parameters['_resource']) ||
            !isset($parameters['id']) ||
            !($resource = $this->resourceCollection->getResourceForShortName($parameters['_resource']))
        ) {
            throw new \InvalidArgumentException(sprintf('No resource associated with the IRI "%s".', $iri));
        }

        return $this->getItem($resource, $parameters['id'], $fetchData);
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection(ResourceInterface $resource, array $filters = [], array $order = [], $page = null, $itemsPerPage = null)
    {
        foreach ($this->dataProviders as $dataProvider) {
            if ($dataProvider->supports($resource) &&
                $result = $dataProvider->getCollection($resource, $filters, $order, $page, $itemsPerPage)) {
                return $result;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ResourceInterface $resource)
    {
        return true;
    }
}
