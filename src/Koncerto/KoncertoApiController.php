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
}
