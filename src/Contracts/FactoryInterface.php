<?php

namespace Overtrue\Socialite\Contracts;

interface FactoryInterface
{
    /**
     * @param string $driver
     *
     * @return \Overtrue\Socialite\Contracts\ProviderInterface
     */
    public function create(string $driver): ProviderInterface;
}
