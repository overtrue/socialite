<?php

namespace Overtrue\Socialite\Contracts;

interface ProviderInterface
{
    public function redirect(?string $redirectUrl = null): string;

    public function userFromCode(string $code): UserInterface;

    public function userFromToken(string $token): UserInterface;
}
