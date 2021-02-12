<?php

namespace Providers;

use Overtrue\Socialite\Providers\FeiShu;
use PHPUnit\Framework\TestCase;

class FeiShuTest extends TestCase
{
    public function testProviderCanCreateCorrect()
    {
        // one way
        $config = [
            'app_id'     => 'xxxxx',
            'app_secret' => 'yyyyy',
            'app_mode'   => 'internal'
        ];
        $f = new FeiShu($config);
        $rf = new \ReflectionObject($f);

        $this->assertEquals('xxxxx', $f->getClientId());
        $this->assertEquals('yyyyy', $f->getClientSecret());

        $rfProperty = $rf->getProperty('isInternalApp');
        $rfProperty->setAccessible(true);
        $this->assertEquals(true, $rfProperty->getValue($f));

        // diff filed way
        $config = [
            'client_id'     => 'xxxxx',
            'client_secret' => 'yyyyy',
            'mode'          => 'internal'
        ];

        $f = new FeiShu($config);
        $rf = new \ReflectionObject($f);

        $this->assertEquals('xxxxx', $f->getClientId());
        $this->assertEquals('yyyyy', $f->getClientSecret());
        $rfProperty = $rf->getProperty('isInternalApp');
        $rfProperty->setAccessible(true);
        $this->assertEquals(true, $rfProperty->getValue($f));

        // no mode config way
        $config = [
            'client_id'     => 'xxxxx',
            'client_secret' => 'yyyyy',
        ];

        $f = new FeiShu($config);
        $rf = new \ReflectionObject($f);

        $this->assertEquals('xxxxx', $f->getClientId());
        $this->assertEquals('yyyyy', $f->getClientSecret());
        $rfProperty = $rf->getProperty('isInternalApp');
        $rfProperty->setAccessible(true);
        $this->assertEquals(false, $rfProperty->getValue($f));
    }
}
