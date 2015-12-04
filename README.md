# Socialite

[![Build Status](https://travis-ci.org/overtrue/socialite.svg?branch=master)](https://travis-ci.org/overtrue/socialite)
[![Latest Stable Version](https://poser.pugx.org/overtrue/socialite/v/stable.svg)](https://packagist.org/packages/overtrue/socialite)
[![Latest Unstable Version](https://poser.pugx.org/overtrue/socialite/v/unstable.svg)](https://packagist.org/packages/overtrue/socialite)
[![Total Downloads](https://poser.pugx.org/overtrue/socialite/downloads)](https://packagist.org/packages/overtrue/socialite)
[![License](https://poser.pugx.org/overtrue/socialite/license)](https://packagist.org/packages/overtrue/socialite)

Socialite is an OAuth2 Authentication tool. It extends from [laravel/socialite](https://github.com/laravel/socialite), You can easily use it without Laravel.

# Requirement

```
PHP >= 5.4
```
# Install

```shell
$ composer require overtrue/socialite
```

# Usage

`authorize.php`:

```php
<?php

use Overtrue\Socialite\SocialiteManager;

include __DIR__.'/vendor/autoload.php';

$config = [
    'weibo' => [
        'client_id'     => 'your-app-id',
        'client_secret' => 'your-app-secret',
        'redirect'      => 'http://localhost/socialite/callback.php',
    ],
];

$socialite = new SocialiteManager($config);

$response = $socialite->driver('weibo')->redirect();

echo $response;// or $response->send();
```

`callback.php`:

```php
<?php

// ...
$user = $socialite->driver('weibo')->user();

var_dump($user);
// 'id' => int 2193182644
// 'nickname' => null
// 'name' => string '安正超' (length=9)
// 'email' => null
// 'avatar' => string 'http://tp1.sinaimg.cn/2193182644/180/40068307042/1' (length=50)
```

### Scope

Before redirecting the user, you may also set "scopes" on the request using the scope method. This method will overwrite all existing scopes:

```php
$response = $socialite->driver('github')
                ->scopes(['scope1', 'scope2'])->redirect();

```

### Additional parameters

To include any optional parameters in the request, call the with method with an associative array:

```php
$response = $socialite->driver('google')
                    ->with(['hd' => 'example.com'])->redirect();
```

### User interface

```php

$user = $socialite->driver('weibo')->user();

$user->getId();
$user->getNickname();
$user->getName();
$user->getEmail();
$user->getAvatar();
```

# Reference

- [Google - OpenID Connect](https://developers.google.com/identity/protocols/OpenIDConnect)
- [Facebook - Graph API](https://developers.facebook.com/docs/graph-api)
- [Linkedin - Authenticating with OAuth 2.0](https://developer.linkedin.com/docs/oauth2)
- [微博 - OAuth 2.0 授权机制说明](http://open.weibo.com/wiki/%E6%8E%88%E6%9D%83%E6%9C%BA%E5%88%B6%E8%AF%B4%E6%98%8E)
- [QQ - OAuth 2.0 登录QQ](http://wiki.connect.qq.com/oauth2-0%E7%AE%80%E4%BB%8B)
- [微信公众平台 - OAuth文档](http://mp.weixin.qq.com/wiki/9/01f711493b5a02f24b04365ac5d8fd95.html)
- [微信开放平台 - 网站应用微信登录开发指南](https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419316505&token=&lang=zh_CN)
- [豆瓣 - OAuth 2.0 授权机制说明](http://developers.douban.com/wiki/?title=oauth2)

# License

MIT