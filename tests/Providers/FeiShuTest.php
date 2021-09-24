<?php

namespace Providers;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;
use Overtrue\Socialite\Exceptions\Feishu\InvalidTicketException;
use Overtrue\Socialite\Exceptions\InvalidTokenException;
use Overtrue\Socialite\Providers\FeiShu;
use PHPUnit\Framework\TestCase;

class FeiShuTest extends TestCase
{
    public function testProviderCanCreateCorrect()
    {
        // one way
        $config = [
            'app_id' => 'xxxxx',
            'app_secret' => 'yyyyy',
            'app_mode' => 'internal'
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
            'client_id' => 'xxxxx',
            'client_secret' => 'yyyyy',
            'mode' => 'internal'
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
            'client_id' => 'xxxxx',
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

    public function testProviderWithInternalAppModeWork()
    {
        $config = [
            'client_id' => 'xxxxx',
            'client_secret' => 'yyyyy',
        ];

        $f = new FeiShu($config);
        $rf = new \ReflectionObject($f);

        $rfProperty = $rf->getProperty('isInternalApp');
        $rfProperty->setAccessible(true);

        $f->withInternalAppMode();
        $this->assertEquals(true, $rfProperty->getValue($f));

        $f->withDefaultMode();
        $this->assertEquals(false, $rfProperty->getValue($f));
    }

    public function testProviderWithAppTicketWork()
    {
        $config = [
            'client_id' => 'xxxxx',
            'client_secret' => 'yyyyy',
        ];

        $f = new FeiShu($config);
        $f->withAppTicket('app_ticket');
        $this->assertEquals('app_ticket', $f->getConfig()->get('app_ticket'));
    }

    public function testConfigAppAccessTokenWithDefaultModeNoAppTicketWork()
    {
        $config = [
            'client_id' => 'xxxxx',
            'client_secret' => 'yyyyy',
        ];

        $f = new FeiShu($config);
        $fr = new \ReflectionObject($f);
        $frClient = $fr->getProperty('httpClient');
        $frClient->setAccessible(true);
        $ff = new \ReflectionMethod(FeiShu::class, 'configAppAccessToken');

        $mock = new MockHandler([
            new Response(403, []),
            new Response(200, [], json_encode([
                'app_access_token' => 'app_access_token'
            ]))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $frClient->setValue($f, $client);
        $ff->setAccessible(true);

        // 默认模式下没有 app_ticket
        $this->expectException(InvalidTicketException::class);
        $ff->invoke($f);

        $ff->invoke($f);
        $f->withAppTicket('app_ticket');
        $this->assertEquals('app_access_token', $f->getConfig()->get('app_access_token'));

        $this->expectException(InvalidTokenException::class);
        $ff->invoke($f);
    }

    public function testConfigAppAccessTokenWithDefaultModeAndAppTicketWorkInBadResponse()
    {
        $config = [
            'client_id' => 'xxxxx',
            'client_secret' => 'yyyyy',
        ];

        $f = new FeiShu($config);
        $fr = new \ReflectionObject($f);
        $frClient = $fr->getProperty('httpClient');
        $frClient->setAccessible(true);
        $ff = new \ReflectionMethod(FeiShu::class, 'configAppAccessToken');

        $mock = new MockHandler([
            new Response(200, []),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $frClient->setValue($f, $client);
        $ff->setAccessible(true);

        $this->expectException(InvalidTokenException::class);
        $ff->invoke($f->withAppTicket('app_ticket'));
    }

    public function testConfigAppAccessTokenWithDefaultModeAndAppTicketWorkInGoodResponse()
    {
        $config = [
            'client_id' => 'xxxxx',
            'client_secret' => 'yyyyy',
        ];

        $f = new FeiShu($config);
        $fr = new \ReflectionObject($f);
        $frClient = $fr->getProperty('httpClient');
        $frClient->setAccessible(true);
        $ff = new \ReflectionMethod(FeiShu::class, 'configAppAccessToken');

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'app_access_token' => 'app_access_token'
            ]))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $frClient->setValue($f, $client);
        $ff->setAccessible(true);

        $this->assertEquals(null, $f->getConfig()->get('app_access_token'));
        $ff->invoke($f->withAppTicket('app_ticket'));
        $this->assertEquals('app_access_token', $f->getConfig()->get('app_access_token'));
    }

    public function testConfigAppAccessTokenWithInternalInBadResponse()
    {
        $config = [
            'client_id' => 'xxxxx',
            'client_secret' => 'yyyyy',
            'mode' => 'internal'
        ];

        $f = new FeiShu($config);
        $fr = new \ReflectionObject($f);
        $frClient = $fr->getProperty('httpClient');
        $frClient->setAccessible(true);
        $ff = new \ReflectionMethod(FeiShu::class, 'configAppAccessToken');

        $mock = new MockHandler([
            new Response(200, []),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $frClient->setValue($f, $client);
        $ff->setAccessible(true);

        $this->expectException(InvalidTokenException::class);
        $ff->invoke($f);
    }

    public function testConfigAppAccessTokenWithInternalInGoodResponse()
    {
        $config = [
            'client_id' => 'xxxxx',
            'client_secret' => 'yyyyy',
            'mode' => 'internal'
        ];

        $f = new FeiShu($config);
        $fr = new \ReflectionObject($f);
        $frClient = $fr->getProperty('httpClient');
        $frClient->setAccessible(true);
        $ff = new \ReflectionMethod(FeiShu::class, 'configAppAccessToken');

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'app_access_token' => 'app_access_token'
            ]))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $frClient->setValue($f, $client);
        $ff->setAccessible(true);

        $this->assertEquals(null, $f->getConfig()->get('app_access_token'));
        $ff->invoke($f);
        $this->assertEquals('app_access_token', $f->getConfig()->get('app_access_token'));
    }
}
