<?php

namespace Overtrue\Socialite\Contracts;

const ABNF_APP_ID = 'app_id';
const ABNF_APP_SECRET = 'app_secret';
const ABNF_OPEN_ID = 'open_id';
const ABNF_TOKEN = 'token';

interface FactoryInterface
{
    public function create(string $driver): ProviderInterface;
}
