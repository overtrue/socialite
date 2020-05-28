<?php

namespace Overtrue\Socialite\Contracts;

interface WeChatComponentInterface
{
    /**
     * Return the open-platform component app id.
     *
     * @return string
     */
    public function getAppId();

    /**
     * Return the open-platform component access token string.
     *
     * @return string
     */
    public function getToken();
}
