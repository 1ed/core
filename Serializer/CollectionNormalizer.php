<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\JsonLdApiBundle\Serializer;

use Dunglas\JsonLdApiBundle\JsonLd\ContextBuilder;
use Dunglas\JsonLdApiBundle\JsonLd\Resources;
use Dunglas\JsonLdApiBundle\Model\PaginatorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\SerializerAwareNormalizer;

/**
 * This normalizer handles collections and paginated collections.
 *
 * @author Kevin Dunglas <dunglas@gmail.com>
 * @author Samuel ROZE <samuel.roze@gmail.com>
 */
class CollectionNormalizer extends SerializerAwareNormalizer implements NormalizerInterface
{
    use ResourceResolver;

    const HYDRA_COLLECTION = 'hydra:Collection';
    const HYDRA_PAGED_COLLECTION = 'hydra:PagedCollection';

    /**
     * @var ContextBuilder
     */
    private $contextBuilder;

    public function __construct(Resources $resources, ContextBuilder $contextBuilder)
    {
        $this->resources = $resources;
        $this->contextBuilder = $contextBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return 'json-ld' === $format && (is_array($data) || $data instanceof \Traversable);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $resource = $this->guessResource($object, $context);
        list($context, $data) = $this->contextBuilder->bootstrap($resource, $context);

        if (isset($context['json_ld_sub_level'])) {
            $data = [];
            foreach ($object as $obj) {
                $data[] = $this->serializer->normalize($obj, $format, $context);
            }
        } else {
            $data['@id'] = $this->resources->getCollectionUri($resource);

            if ($object instanceof PaginatorInterface) {
                $data['@type'] = self::HYDRA_PAGED_COLLECTION;

                $currentPage = $object->getCurrentPage();
                $lastPage = $object->getLastPage();

                $baseUrl = $data['@id'];
                $paginatedUrl = $baseUrl.'?page=';

                if (1. !== $currentPage) {
                    $previousPage = $currentPage - 1.;
                    $data['@id'] .= $paginatedUrl.$currentPage;
                    $data['hydra:previousPage'] = 1. === $previousPage ? $baseUrl : $paginatedUrl.$previousPage;
                }

                if ($currentPage !== $lastPage) {
                    $data['hydra:nextPage'] = $paginatedUrl.($currentPage + 1.);
                }

                $data['hydra:totalItems'] = $object->getTotalItems();
                $data['hydra:itemsPerPage'] = $object->getItemsPerPage();
                $data['hydra:firstPage'] = $baseUrl;
                $data['hydra:lastPage'] = 1. === $lastPage ? $baseUrl : $paginatedUrl.$lastPage;
            } else {
                $data['@type'] = self::HYDRA_COLLECTION;
            }

            $data['hydra:member'] = [];
            foreach ($object as $obj) {
                $data['hydra:member'][] = $this->serializer->normalize($obj, $format, $context);
            }
        }

        return $data;
    }
}
