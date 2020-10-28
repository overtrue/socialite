<?php

namespace Overtrue\Socialite\Exceptions;

class AuthorizeFailedException extends Exception
{
    public array $body;

    /**
     * @param string $message
     * @param array  $body
     */
    public function __construct(string $message, $body)
    {
        parent::__construct($message, -1);

        $this->body = (array) $body;
    }
}
