<?php

namespace Koncerto;

class KoncertoResponse
{
    /** @var string */
    private $content;

    /** @var string */
    private $contentType = 'text/html';

    /**
     * @param string $content
     * @return KoncertoResponse
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

   /**
     * @param string $contentType
     * @return KoncertoResponse
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }
}
