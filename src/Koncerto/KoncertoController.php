<?php

namespace Koncerto;

class KoncertoController
{
    /**
     * @param mixed $data
     * @return KoncertoResponse
     */
    public function json($data)
    {
        $response = new KoncertoResponse();
        $response->setContentType('application/json');
        $response->setContent((string)json_encode($data));

        return $response;
    }
}
