<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\JsonLdApiBundle\EventListener;

use Dunglas\JsonLdApiBundle\Exception\DeserializationException;
use Dunglas\JsonLdApiBundle\Response\JsonLdResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Handle requests errors.
 *
 * @author Samuel ROZE <samuel.roze@gmail.com>
 */
class RequestExceptionListener
{
    /**
     * @var NormalizerInterface
     */
    private $normalizer;

    /**
     * @param NormalizerInterface $normalizer
     */
    public function __construct(NormalizerInterface $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $exception = $event->getException();

        // Normalize exceptions with hydra errors only for resources
        if ($request->attributes->has('_json_ld_resource')) {
            $event->setResponse(new JsonLdResponse(
                $this->normalizer->normalize($exception, 'hydra-error'),
                $exception instanceof DeserializationException ? 400 : 500
            ));
        }
    }
}
