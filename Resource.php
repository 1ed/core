<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\JsonLdApiBundle;

use Doctrine\Common\Inflector\Inflector;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class representing a JSON-LD / Hydra resource.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Resource
{
    /**
     * @var string
     */
    const ROUTE_NAME_PREFIX = 'json_ld_api_';
    /**
     * @var string
     */
    const ROUTE_PATH_PREFIX = '/';

    /**
     * @var string
     */
    protected $entityClass;
    /**
     * @var string
     */
    protected $shortName;
    /**
     * @var array
     */
    protected $normalizationContext;
    /**
     * @var array
     */
    protected $denormalizationContext;
    /**
     * @var array|null
     */
    protected $validationGroups;
    /**
     * @var array
     */
    protected $serializerContext;
    /**
     * @var array
     */
    protected $collectionOperations;
    /**
     * @var array
     */
    protected $itemOperations;
    /**
     * @var string
     */
    protected $controllerName;
    /**
     * @var string
     */
    protected $serviceId;
    /**
     * @var RouteCollection|null
     */
    protected $routeCollection = null;
    /**
     * @var string|null
     */
    protected $elementRoute = null;
    /**
     * @var string|null
     */
    protected $collectionRoute = null;

    /**
     * @param string     $entityClass
     * @param string     $shortName
     * @param array      $normalizationContext
     * @param array      $denormalizationContext
     * @param array|null $validationGroups
     * @param array      $collectionOperations
     * @param array      $itemOperations
     * @param string     $controllerName
     */
    public function __construct(
        $entityClass,
        $shortName = null,
        array $normalizationContext = [],
        array $denormalizationContext = [],
        array $validationGroups = null,
        array $collectionOperations = [
            [
                '@type' => 'Operation',
                'method' => 'GET',
            ],
            [
                '@type' => 'CreateResourceOperation',
                'method' => 'POST',
            ],
        ],
        array $itemOperations = [
            [
                '@type' => 'Operation',
                'method' => 'GET',
            ],
            [
                '@type' => 'ReplaceResourceOperation',
                'method' => 'PUT',
            ],
            [
                '@type' => 'DeleteResourceOperation',
                'method' => 'DELETE'
            ],
        ],
        $controllerName = 'DunglasJsonLdApiBundle:Resource'
    ) {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException(sprintf('The class %s does not exist.', $entityClass));
        }

        $this->entityClass = $entityClass;
        $this->shortName = $shortName ?: substr($this->entityClass, strrpos($this->entityClass, '\\') + 1);
        $this->normalizationContext = $normalizationContext;
        $this->denormalizationContext = $denormalizationContext;
        $this->validationGroups = $validationGroups;
        $this->collectionOperations = $collectionOperations;
        $this->itemOperations = $itemOperations;
        $this->controllerName = $controllerName;

        $this->normalizationContext['resource'] = $this;
        $this->denormalizationContext['resource'] = $this;
    }

    /**
     * Gets the associated entity class.
     *
     * @return string
     */
    public function getEntityClass()
    {
        return $this->entityClass;
    }

    /**
     * Gets the short name (display name) of the resource.
     *
     * @return string
     */
    public function getShortName()
    {
        return $this->shortName;
    }

    /**
     * Gets the normalization context.
     *
     * @return array
     */
    public function getNormalizationContext()
    {
        return $this->normalizationContext;
    }

    /**
     * Gets normalization groups.
     *
     * @return string[]|null
     */
    public function getNormalizationGroups()
    {
        return isset($this->normalizationContext['groups']) ? $this->normalizationContext['groups'] : null;
    }

    /**
     * Gets the denormalization context.
     *
     * @return array
     */
    public function getDenormalizationContext()
    {
        return $this->denormalizationContext;
    }

    /**
     * Gets denormalization groups.
     *
     * @return string[]|null
     */
    public function getDenormalizationGroups()
    {
        return isset($this->denormalizationContext['groups']) ? $this->denormalizationContext['groups'] : null;
    }

    /**
     * Gets validation groups to use.
     *
     * @return string[]|null
     */
    public function getValidationGroups()
    {
        return $this->validationGroups;
    }

    /**
     * Gets the controller name.
     *
     * @return string
     */
    public function getControllerName()
    {
        return $this->controllerName;
    }

    /**
     * Gets the route collection for this resource.
     *
     * @return RouteCollection
     */
    public function getRouteCollection()
    {
        if ($this->routeCollection) {
            return $this->routeCollection;
        }

        $this->routeCollection = new RouteCollection();
        $beautified = Inflector::pluralize(Inflector::tableize($this->shortName));

        foreach ($this->collectionOperations as $collectionOperation) {
            $this->addRoute($beautified, $this->routeCollection, $collectionOperation, true);
        }

        foreach ($this->itemOperations as $itemOperation) {
            $this->addRoute($beautified, $this->routeCollection, $itemOperation, false);
        }

        return $this->routeCollection;
    }

    /**
     * Sets the associated service id.
     *
     * @param string $serviceId
     */
    public function setServiceId($serviceId)
    {
        $this->serviceId = $serviceId;
    }

    /**
     * Adds a route to the collection.
     *
     * @param string          $beautified
     * @param RouteCollection $routeCollection
     * @param array           $operation
     * @param boolean         $isCollection
     */
    private function addRoute($beautified, RouteCollection $routeCollection, array $operation, $isCollection)
    {
        $method = isset($operation['method']) ? $operation['method'] : 'GET';
        $action = $method === 'GET' && $isCollection ? 'cget' : strtolower($method);

        if (isset($operation['_controller'])) {
            $controller = $operation['_controller'];
        } else {
            $controller = sprintf('%s:%s', $this->controllerName, $action);
        }

        if (isset($operation['_route_name'])) {
            $routeName = $operation['_route_name'];
        } else {
            $routeName = sprintf('%s%s_%s', self::ROUTE_NAME_PREFIX, $beautified, $action);
        }

        if (isset($operation['_route_path'])) {
            $routePath = $operation['_route_path'];
        } else {
            $routePath = self::ROUTE_PATH_PREFIX.$beautified;

            if (!$isCollection) {
                $routePath .= '/{id}';
            }
        }

        $methods = 'GET' === $method ? ['GET', 'HEAD'] : [$method];

        $routeCollection->add($routeName, new Route(
            $routePath,
            [
                '_controller' => $controller,
                '_json_ld_api_resource' => $this->serviceId,
            ],
            [],
            [],
            '',
            [],
            $methods
        ));

        // Set routes
        if ('GET' === $method) {
            if (!$this->collectionRoute && $isCollection) {
                $this->collectionRoute = $routeName;
            }

            if (!$this->elementRoute && !$isCollection) {
                $this->elementRoute = $routeName;
            }
        }
    }

    public function getCollectionRoute()
    {
        if (!$this->collectionRoute) {
            // Can be optimized
            $this->getRouteCollection();
        }

        return $this->collectionRoute;
    }

    /**
     * Gets route to generate an identifier.
     *
     * @return string
     */
    public function getElementRoute()
    {
        if (!$this->elementRoute) {
            // Can be optimized
            $this->getRouteCollection();
        }

        return $this->elementRoute;
    }
}
