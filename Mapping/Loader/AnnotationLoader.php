<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\JsonLdApiBundle\Mapping\Loader;

use Doctrine\Common\Annotations\Reader;
use Dunglas\JsonLdApiBundle\Mapping\ClassMetadata;
use Dunglas\JsonLdApiBundle\Util\Reflection;

/**
 * Annotation loader.
 *
 * Attributes must be loaded first.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AnnotationLoader implements LoaderInterface
{
    use Reflection;

    /**
     * @var string
     */
    const IRI_ANNOTATION_NAME = 'Dunglas\JsonLdApiBundle\Annotation\Iri';

    /**
     * @var Reader
     */
    private $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(
        ClassMetadata $classMetadata,
        array $normalizationGroups = null,
        array $denormalizationGroups = null,
        array $validationGroups = null
    ) {
        $reflectionClass = $classMetadata->getReflectionClass();
        if ($iri = $this->reader->getClassAnnotation($reflectionClass, self::IRI_ANNOTATION_NAME)) {
            $classMetadata->setIri($iri->value);
        }

        foreach ($classMetadata->getAttributes() as $attributeMetadata) {
            $attributeName = $attributeMetadata->getName();

            if ($reflectionProperty = $this->getReflectionProperty($reflectionClass, $attributeName)) {
                if ($iri = $this->reader->getPropertyAnnotation($reflectionProperty, self::IRI_ANNOTATION_NAME)) {
                    $attributeMetadata->setIri($iri->value);
                }
            }
        }

        return true;
    }
}
