<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\JsonLdApiBundle\Controller;

use Dunglas\JsonLdApiBundle\Response\JsonLdResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Generates API documentation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DocumentationController extends Controller
{
    /**
     * Namespace of types specific to the current API.
     *
     * @return Response
     *
     * @Route(name="json_ld_api_vocab", path="/vocab")
     */
    public function vocabAction()
    {
        return new Response('The app vocab.');
    }

    /**
     * JSON-LD context for a given type
     *
     * @param string $shortName
     *
     * @return JsonLdResponse
     *
     * @Route(name="json_ld_api_context", path="/contexts/{shortName}")
     */
    public function contextAction($shortName)
    {
        $resource = $this->get('dunglas_json_ld_api.resources')->getResourceForShortName($shortName);
        if (!$resource) {
            throw $this->createNotFoundException();
        }

        return new JsonLdResponse(
            ['@context' => $this->get('dunglas_json_ld_api.context_builder')->buildContext($resource)]
        );
    }
}
