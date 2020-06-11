<?php

namespace Overtrue\Socialite\Exceptions;

class InvalidTokenException extends Exception
{
    public string $token;

    /**
     * @param string $message
     * @param string $token
     */
    public function __construct(string $message, string $token)
    {
        parent::__construct($message, -1);

        $this->token = $token;
    }
}
