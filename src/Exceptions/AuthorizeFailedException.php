<?php

namespace Overtrue\Socialite\Exceptions;

class AuthorizeFailedException extends Exception
{
    /**
     * @var array
     */
    public $body;

    /**
     * @param string $message
     * @param array  $body
     */
    public function __construct($message, $body)
    {
        parent::__construct($message, -1);

        $this->body = $body;
    }
}
