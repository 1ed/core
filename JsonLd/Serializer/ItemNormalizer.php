<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\ApiBundle\JsonLd\Serializer;

use Dunglas\ApiBundle\Api\IriConverterInterface;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Api\ResourceResolver;
use Dunglas\ApiBundle\JsonLd\ContextBuilder;
use Dunglas\ApiBundle\Mapping\ClassMetadataInterface;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactoryInterface;
use Dunglas\ApiBundle\Mapping\AttributeMetadataInterface;
use PropertyInfo\Type;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Converts between objects and array including JSON-LD and Hydra metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ItemNormalizer extends AbstractNormalizer
{
    use ResourceResolver;

    /**
     * @var string
     */
    const FORMAT = 'json-ld';

    /**
     * @var IriConverterInterface
     */
    private $iriConverter;
    /**
     * @var ClassMetadataFactoryInterface
     */
    private $apiClassMetadataFactory;
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;
    /**
     * @var ContextBuilder
     */
    private $contextBuilder;

    public function __construct(
        ResourceCollectionInterface $resourceCollection,
        IriConverterInterface $iriConverter,
        ClassMetadataFactoryInterface $apiClassMetadataFactory,
        ContextBuilder $contextBuilder,
        PropertyAccessorInterface $propertyAccessor,
        NameConverterInterface $nameConverter = null
    ) {
        parent::__construct(null, $nameConverter);

        $this->resourceCollection = $resourceCollection;
        $this->iriConverter = $iriConverter;
        $this->apiClassMetadataFactory = $apiClassMetadataFactory;
        $this->contextBuilder = $contextBuilder;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return self::FORMAT === $format && (is_object($data) || is_array($data));
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException
     * @throws CircularReferenceException
     * @throws InvalidArgumentException
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (!$this->serializer instanceof NormalizerInterface) {
            throw new RuntimeException('The serializer must implement the NormalizerInterface.');
        }

        if (is_object($object) && $this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object);
        }

        $resource = $this->guessResource($object, $context, true);
        list($context, $data) = $this->contextBuilder->bootstrap($resource, $context);

        // Don't use hydra:Collection in sub levels
        $context['json_ld_sub_level'] = true;

        $classMetadata = $this->getMetadata($resource, $context);
        $attributesMetadata = $classMetadata->getAttributes();

        $data['@id'] = $this->iriConverter->getIriFromItem($object);
        $data['@type'] = ($iri = $classMetadata->getIri()) ? $iri : $resource->getShortName();

        foreach ($attributesMetadata as $attributeMetadata) {
            $attributeName = $attributeMetadata->getName();

            if ('id' === $attributeName || !$attributeMetadata->isReadable()) {
                continue;
            }
            $attributeValue = $this->propertyAccessor->getValue($object, $attributeName);

            if (isset($attributeMetadata->getTypes()[0])) {
                $type = $attributeMetadata->getTypes()[0];

                if (
                    $attributeValue &&
                    $type->isCollection() &&
                    ($collectionType = $type->getCollectionType()) &&
                    $class = $this->getClassHavingResource($collectionType)
                ) {
                    $values = [];
                    foreach ($attributeValue as $obj) {
                        $values[] = $this->normalizeRelation($resource, $attributeMetadata, $obj, $class);
                    }

                    $data[$attributeName] = $values;

                    continue;
                }

                if ($attributeValue && $class = $this->getClassHavingResource($type)) {
                    $data[$attributeName] = $this->normalizeRelation($resource, $attributeMetadata, $attributeValue, $class);

                    continue;
                }
            }

            $data[$attributeName] = $this->serializer->normalize($attributeValue, self::FORMAT, $context);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return self::FORMAT === $format;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        if (!$this->serializer instanceof DenormalizerInterface) {
            throw new RuntimeException('The serializer must implement the DenormalizerInterface to denormalize relations.');
        }

        $resource = $this->guessResource($data, $context, true);
        $normalizedData = $this->prepareForDenormalization($data);

        $attributesMetadata = $this->getMetadata($resource, $context)->getAttributes();

        $allowedAttributes = [];
        foreach ($attributesMetadata as $attributeName => $attributeMetadata) {
            if ($attributeMetadata->isReadable()) {
                $allowedAttributes[] = $attributeName;
            }
        }

        $reflectionClass = new \ReflectionClass($class);

        if (isset($data['@id']) && !isset($context['object_to_populate'])) {
            $context['object_to_populate'] = $this->iriConverter->getItemFromIri($data['@id']);

            // Avoid issues with proxies if we populated the object
            $overrideClass = true;
        } else {
            $overrideClass = false;
        }

        $object = $this->instantiateObject(
            $normalizedData,
            $overrideClass ? get_class($context['object_to_populate']) : $class,
            $context,
            $reflectionClass,
            $allowedAttributes
        );

        foreach ($normalizedData as $attributeName => $attributeValue) {
            // Ignore JSON-LD special attributes
            if ('@' === $attributeName[0]) {
                continue;
            }

            if ($this->nameConverter) {
                $attributeName = $this->nameConverter->denormalize($attributeName);
            }

            if (!in_array($attributeName, $allowedAttributes) || in_array($attributeName, $this->ignoredAttributes)) {
                continue;
            }

            $types = $attributesMetadata[$attributeName]->getTypes();
            if (isset($types[0])) {
                $type = $types[0];

                if (
                    $attributeValue &&
                    $type->isCollection() &&
                    ($collectionType = $type->getCollectionType()) &&
                    ($class = $collectionType->getClass())
                ) {
                    $values = [];
                    foreach ($attributeValue as $obj) {
                        $values[] = $this->denormalizeRelation($resource, $attributesMetadata[$attributeName], $class, $obj);
                    }

                    $this->setValue($object, $attributeName, $values);

                    continue;
                }

                if ($attributeValue && ($class = $type->getClass())) {
                    $this->setValue(
                        $object,
                        $attributeName,
                        $this->denormalizeRelation($resource, $attributesMetadata[$attributeName], $class, $attributeValue)
                    );

                    continue;
                }
            }

            $this->setValue($object, $attributeName, $attributeValue);
        }

        return $object;
    }

    /**
     * Normalizes a relation as an URI if is a Link or as a JSON-LD object.
     *
     * @param ResourceInterface          $currentResource
     * @param AttributeMetadataInterface $attribute
     * @param mixed                      $relatedObject
     * @param string                     $class
     *
     * @return string|array
     */
    private function normalizeRelation(
        ResourceInterface $currentResource,
        AttributeMetadataInterface $attribute,
        $relatedObject,
        $class
    ) {
        if ($attribute->isNormalizationLink()) {
            return $this->iriConverter->getIriFromItem($relatedObject);
        } else {
            $context = $this->contextBuilder->bootstrapRelation($currentResource, $class);

            return $this->serializer->normalize($relatedObject, 'json-ld', $context);
        }
    }

    /**
     * Denormalizes a relation.
     *
     * @param ResourceInterface          $currentResource
     * @param AttributeMetadataInterface $attributeMetadata
     * @param string                     $class
     * @param mixed                      $value
     *
     * @return object|null
     *
     * @throws InvalidArgumentException
     */
    private function denormalizeRelation(
        ResourceInterface $currentResource,
        AttributeMetadataInterface $attributeMetadata,
        $class,
        $value
    ) {
        if ('DateTime' === $class) {
            return $this->serializer->denormalize($value, $class ?: null, self::FORMAT);
        }

        $attributeName = $attributeMetadata->getName();

        // Always allow IRI to be compliant with the Hydra spec
        if (is_string($value)) {
            $item = $this->iriConverter->getItemFromIri($value);

            if (null === $item) {
                throw new InvalidArgumentException(sprintf(
                    'IRI  not supported (found "%s" in "%s" of "%s")',
                    $value,
                    $attributeName,
                    $currentResource->getEntityClass()
                ));
            }

            return $item;
        }

        if (!$this->resourceCollection->getResourceForEntity($class)) {
            throw new InvalidArgumentException(sprintf(
                'Type not supported (found "%s" in attribute "%s" of "%s")',
                $class,
                $attributeName,
                $currentResource->getEntityClass()
            ));
        }

        $context = $this->contextBuilder->bootstrapRelation($currentResource, $class);
        if (!$attributeMetadata->isDenormalizationLink()) {
            return $this->serializer->denormalize($value, $class, self::FORMAT, $context);
        }

        throw new InvalidArgumentException(sprintf(
            'Nested objects for attribute "%s" of "%s" are not enabled. Use serialization groups to change that behavior.',
            $attributeName,
            $currentResource->getEntityClass()
        ));
    }

    /**
     * Sets a value of the object using the PropertyAccess component.
     *
     * @param object $object
     * @param string $attributeName
     * @param mixed  $value
     */
    private function setValue($object, $attributeName, $value)
    {
        try {
            $this->propertyAccessor->setValue($object, $attributeName, $value);
        } catch (NoSuchPropertyException $exception) {
            // Properties not found are ignored
        }
    }

    /**
     * Gets metadata for the given resource with the current context.
     *
     * Fallback to the resource own groups if no context is provided.
     *
     * @param ResourceInterface $resource
     * @param array             $context
     *
     * @return ClassMetadataInterface
     */
    private function getMetadata(ResourceInterface $resource, array $context)
    {
        return $this->apiClassMetadataFactory->getMetadataFor(
            $resource->getEntityClass(),
            isset($context['json_ld_normalization_groups']) ? $context['json_ld_normalization_groups'] : $resource->getNormalizationGroups(),
            isset($context['json_ld_denormalization_groups']) ? $context['json_ld_denormalization_groups'] : $resource->getDenormalizationGroups(),
            isset($context['json_ld_validation_groups']) ? $context['json_ld_validation_groups'] : $resource->getValidationGroups()
        );
    }
}
