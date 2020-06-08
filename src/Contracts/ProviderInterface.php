<?php

/*
 * This file is part of the overtrue/socialite.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\Socialite\Contracts;

use Overtrue\Socialite\User;

interface ProviderInterface
{
    public function redirect(string $redirectUrl = ''): string;

    public function userFromCode(string $code): User;

    public function userFromToken(string $token): User;
}
