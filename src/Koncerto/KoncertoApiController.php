<?php

namespace Koncerto;

use Koncerto\KoncertoAnnotation as K;

class KoncertoApiController extends KoncertoController
{
    /**
     * @see K::route() {"name": "/api/"}
     * @return KoncertoResponse
     */
    public function api()
    {
        return $this->json(array());
    }

    /**
     * @see K::route() {"name": "/api/docs/"}
     * @return KoncertoResponse
     */
    public function docs()
    {
        $response = new KoncertoResponse();
        $response->setContent('<h1>Under construction</h1>');

        return $response;
    }

    /**
     * @see K::route() {"name": "/api/%s/"}
     * @param array<mixed>|null $args
     * @return KoncertoResponse
     */
    public function crud($args = array())
    {
        return $this->json(array('params' => $args));
    }
}
