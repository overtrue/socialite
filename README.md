<h1 align="center"> Socialite</h1>
<p align="center">
<a href="https://packagist.org/packages/overtrue/socialite"><img src="https://poser.pugx.org/overtrue/socialite/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/overtrue/socialite"><img src="https://poser.pugx.org/overtrue/socialite/v/unstable.svg" alt="Latest Unstable Version"></a>
<a href="https://scrutinizer-ci.com/g/overtrue/socialite/build-status/master"><img src="https://scrutinizer-ci.com/g/overtrue/socialite/badges/build.png?b=master" alt="Build Status"></a>
<a href="https://packagist.org/packages/overtrue/socialite"><img src="https://poser.pugx.org/overtrue/socialite/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/overtrue/socialite"><img src="https://poser.pugx.org/overtrue/socialite/license" alt="License"></a>
</p>


<p align="center">Socialite is an <a href="https://oauth.net/2/">OAuth2</a>  Authentication tool. It is inspired by <a href="https://github.com/laravel/socialite">laravel/socialite</a>, You can easily use it in any PHP project.       <a href="/README_CN.md">中文文档</a></p>

<p align="center">This tool now supports platforms such as Facebook, GitHub, Google, LinkedIn, Outlook, QQ, Tapd, Alipay, Taobao, Baidu, DingTalk, Weibo, WeChat, Douyin, Feishu, Douban, WeWork, Tencent Cloud, Line, Gitee.</p>

[![Sponsor me](https://raw.githubusercontent.com/overtrue/overtrue/master/sponsor-me-button-s.svg)](https://github.com/sponsors/overtrue)

如果你喜欢我的项目并想支持它，[点击这里 :heart:](https://github.com/sponsors/overtrue)


- [Requirement](#requirement)
- [Installation](#installation)
- [Usage](#usage)
  - [Configuration](#configuration)
    - [Custom app name](#custom-app-name)
    - [Extends custom provider](#extends-custom-provider)
  - [Platform](#platform)
    - [Alipay](#alipay)
    - [DingTalk](#dingtalk)
    - [Douyin](#douyin)
    - [Baidu](#baidu)
    - [Feishu](#feishu)
    - [Taobao](#taobao)
    - [WeChat](#wechat)
  - [Some Skill](#some-skill)
    - [Scopes](#scopes)
    - [Redirect URL](#redirect-url)
    - [State](#state)
    - [Redirect with `state` parameter](#redirect-with-state-parameter)
    - [Validate the callback `state`](#validate-the-callback-state)
    - [Additional parameters](#additional-parameters)
  - [User interface](#user-interface)
    - [Standard user api:](#standard-user-api)
    - [Get raw response from OAuth API](#get-raw-response-from-oauth-api)
    - [Get the token response when you use userFromCode()](#get-the-token-response-when-you-use-userfromcode)
    - [Get user with access token](#get-user-with-access-token)
- [Enjoy it! :heart:](#enjoy-it-heart)
- [Reference](#reference)
- [PHP 扩展包开发](#php-扩展包开发)
- [License](#license)

# Requirement

```
PHP >= 7.4
```
# Installation

```shell
$ composer require "overtrue/socialite" -vvv
```

# Usage

Users just need to create the corresponding configuration variables, then create the authentication application for each platform through the tool, and easily obtain the access_token and user  information for that platform. The implementation logic of the tool is referred to OAuth2 documents of major platforms for details.

The tool is used in the following steps:

1. Configurate platform config
2. Use this tool to create a platform application
3. Let the user redirect to platform authentication
4. The server receives a Code callback from the platform, and uses the Code to exchange the user information on the platform (including access_token).

Packages created for Laravel users are easier to integrate:  [overtrue/laravel-socialite](https://github.com/overtrue/laravel-socialite)

`authorize.php`:

```php
<?php

use Overtrue\Socialite\SocialiteManager;

$config = [
    'github' => [
        'client_id'     => 'your-app-id',
        'client_secret' => 'your-app-secret',
        'redirect'      => 'http://localhost/socialite/callback.php',
    ],
];

$socialite = new SocialiteManager($config);

$url = $socialite->create('github')->redirect();

return redirect($url); 
```

`callback.php`:

```php
<?php

use Overtrue\Socialite\SocialiteManager;

$config = [
    'github' => [
        'client_id' => 'your-app-id',
        'client_secret' => 'your-app-secret',
        'redirect' => 'http://localhost/socialite/callback.php',
    ],
];

$socialite = new SocialiteManager($config);

$code = request()->query('code');

$user = $socialite->create('github')->userFromCode($code);

$user->getId();        // 1472352
$user->getNickname();  // "overtrue"
$user->getUsername();  // "overtrue"
$user->getName();      // "安正超"
$user->getEmail();     // "anzhengchao@gmail.com"
...
```

## Configuration

Each create uses the same configuration keys: `client_id`, `client_secret`, `redirect`.

Example:
```php
$config = [
  'weibo' => [
    'client_id'     => 'your-app-id',
    'client_secret' => 'your-app-secret',
    'redirect'      => 'http://localhost/socialite/callback.php',
  ],
  'facebook' => [
    'client_id'     => 'your-app-id',
    'client_secret' => 'your-app-secret',
    'redirect'      => 'http://localhost/socialite/callback.php',
  ],
];
```

### Custom app name

You can use any name you like as the name of the application, such as `foo`, and set provider using `provider` key：

```php
$config = [
    'foo' => [
        'provider' => 'github',  // <-- provider name
        'client_id' => 'your-app-id',
        'client_secret' => 'your-app-secret',
        'redirect' => 'http://localhost/socialite/callback.php',
    ],
       
    // another github app
    'bar' => [
        'provider' => 'github',  // <-- provider name
        'client_id' => 'your-app-id',
        'client_secret' => 'your-app-secret',
        'redirect' => 'http://localhost/socialite/callback.php',
    ],
    //...
];
```

### Extends custom provider

You can create application from you custom provider easily，you have to ways to do this: 

1. Using custom creator:
   As shown in the following code, the service provider name is defined for the Foo application, but the tool itself does not support it, so use the creator `extend()` to create an instance of the service provider as a closure function.

```php
$config = [
    'foo' => [
        'provider' => 'myprovider',  // <-- provider name
        'client_id' => 'your-app-id',
        'client_secret' => 'your-app-secret',
        'redirect' => 'http://localhost/socialite/callback.php',
    ],
];

$socialite = new SocialiteManager($config);
   
$socialite->extend('myprovider', function(array $config) {
    return new MyCustomProvider($config);
});

$app = $socialite->create('foo');
```

2. Using provider:

>👋🏻 Your custom provider class must be implements of `Overtrue\Socialite\Contracts\ProviderInterface`.

```php
class MyCustomProvider implements \Overtrue\Socialite\Contracts\ProviderInterface 
{
    //...
}
```

then set `provider` with the class name:

```php
$config = [
    'foo' => [
        'provider' => MyCustomProvider::class,  // <-- class name
        'client_id' => 'your-app-id',
        'client_secret' => 'your-app-secret',
        'redirect' => 'http://localhost/socialite/callback.php',
    ],
];

$socialite = new SocialiteManager($config);
$app = $socialite->create('foo');
```



## Platform

Different platforms have different configuration methods, so please check the platform Settings you are using.

### [Alipay](https://opendocs.alipay.com/open/200/105310#s2)

You must have the following configuration.
```php
$config = [
  'alipay' => [
    // This can also be named as 'app_id' like the official documentation.
    'client_id' => 'your-app-id', 
 
    // Please refer to the official documentation, in the official management background configuration RSA2.
    // Note: This is your own private key.
    // Note: Do not allow the private key content to have extra characters.
    // Recommendation: For security, you can read directly from the file. But here as long as the value, please remember to remove the head and tail of the decoration.
    'rsa_private_key' => 'your-rsa-private-key',

    // Be sure to set this value and make sure that it is the same address value as set in the official admin system.
    // This can also be named as 'redirect_url' like the official documentation.
    'redirect' => 'http://localhost/socialite/callback.php',
  ]
  ...
];

$socialite = new SocialiteManager($config);

$user = $socialite->create('alipay')->userFromCode('here is auth code');

// See this documents "User interface"
$user->getId();        // 1472352
$user->getNickname();  // "overtrue"
$user->getUsername();  // "overtrue"
$user->getName();      // "安正超"
...
```
Only RSA2 personal private keys are supported, so stay tuned if you want to log in with a certificate.

### [DingTalk](https://ding-doc.dingtalk.com/doc#/serverapi3/mrugr3)

Follow the documentation and configure it like following.

> Note: It only supported QR code access to third-part websites. i.e exchange for user information(opendid, unionid and nickname)

```php
$config = [
  'dingtalk' => [
      // or 'app_id'
      'client_id' => 'your app id',

      // or 'app_secret' 
      'client_secret' => 'your app secret',

      // or 'redirect_url'
      'redirect' => 'redirect URL'
  ]
];

$socialite = new SocialiteManager($config);

$user = $socialite->create('dingtalk')->userFromCode('here is auth code');

// See this documents "User interface"
$user->getId();        // 1472352
$user->getNickname();  // "overtrue"
$user->getUsername();  // "overtrue"
$user->getName();      // "安正超"
...
```

### [Douyin](https://open.douyin.com/platform/doc/OpenAPI-oauth2)

> Note： using the Douyin create that if you get user information directly using access token, set up the openid first. the openid can be obtained by code when access is obtained, so call `userFromCode()` automatically configured for you openid, if call `userFromToken()` first call `withOpenId()`

```php
$config = [
  'douyin' => [
      'client_id' => 'your app id',

      'client_secret' => 'your app secret',

      'redirect' => 'redirect URL'
  ]
];

$socialite = new SocialiteManager($config);

$user = $socialite->create('douyin')->userFromCode('here is auth code');

$user = $socialite->create('douyin')->withOpenId('openId')->userFromToken('here is the access token');
```


### [Baidu](https://developer.baidu.com/wiki/index.php?title=docs/oauth)

You can choose the form you want display by using `withDisplay()`.

- **page**
- **popup**
- **dialog**
- **mobile**
- **tv**
- **pad**

```php
$authUrl = $socialite->create('baidu')->withDisplay('mobile')->redirect();

```
`popup` mode is the default setting with display. `basic` is the default with scopes.

### [Feishu](https://open.feishu.cn/document/ukTMukTMukTM/uITNz4iM1MjLyUzM)

Some simple way to use by internal app mode and config app_ticket.

```php
$config = [
    'feishu' => [
        // or 'app_id'
        'client_id' => 'your app id',

        // or 'app_secret' 
        'client_secret' => 'your app secret',

        // or 'redirect_url'
        'redirect' => 'redirect URL',

        // if you want to use internal way to get app_access_token
        // set this key by 'internal' then you already turn on the internal app mode 
        'app_mode' => 'internal'
    ]
];

$socialite = new SocialiteManager($config);

$feishuDriver = $socialite->create('feishu');

$feishuDriver->withInternalAppMode()->userFromCode('here is code');
$feishuDriver->withDefaultMode()->withAppTicket('app_ticket')->userFromCode('here is code');
```

### [Taobao](https://open.taobao.com/doc.htm?docId=102635&docType=1&source=search)

You can choose the form you want display by using `withView()`.

```php
$authUrl = $socialite->create('taobao')->withView('wap')->redirect();
```
`web` mode is the default setting with display. `user_info` is the default with scopes.

### [WeChat](https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/Official_Accounts/official_account_website_authorization.html)

We support Open Platform Third-party Platform webpage authorizations on behalf of Official Account.

You just need input your config like below config. Official Accounts authorizations only doesn't need.
```php
...
[
    'wechat' =>
        [
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect' => 'redirect-url',

            // Open Platform - Third-party Platform Need
            'component' => [
                'id' => 'component-app-id',
                'token' => 'component-access-token', // or Using a callable as value.
            ]
        ]
],
...
```

## Some Skill

### Scopes

Before redirecting the user, you may also set "scopes" on the request using the `scopes()` method. This method will overwrite all existing scopes:

```php
$response = $socialite->create('github')
                ->scopes(['scope1', 'scope2'])->redirect();
```

### Redirect URL

You may also want to dynamically set `redirect_uri`，you can use the following methods to change the `redirect_uri` URL:

```php
$url = 'your callback url.';

$socialite->redirect($url);
// or
$socialite->withRedirectUrl($url)->redirect();
```

### State

Your app can use a state parameter for making sure the response belongs to a request initiated by the same user, therefore preventing cross-site request forgery (CSFR) attacks. A CSFR attack occurs when a malicious attacker tricks the user into performing unwanted actions that only the user is authorized to perform on a trusted web application, and all will be done without involving or alerting the user.

Here's the simplest example of how providing the state can make your app more secure. in this example, we use the session ID as the state parameter, but you can use whatever logic you want to create value for the state.

### Redirect with `state` parameter 
```php
<?php
session_start();
 
$config = [
    //...
];

// Assign to state the hashing of the session ID
$state = hash('sha256', session_id());

$socialite = new SocialiteManager($config);

$url = $socialite->create('github')->withState($state)->redirect();

return redirect($url); 
```

### Validate the callback `state`

Once the user has authorized your app, the user will be redirected back to your app's redirect_uri. The OAuth server will return the state parameter unchanged. Check if the state provided in the redirect_uri matches the state generated by your app:

```php
<?php
session_start();
 
$state = request()->query('state');
$code = request()->query('code');
 
// Check the state received with current session id
if ($state != hash('sha256', session_id())) {
    exit('State does not match!');
}
$user = $socialite->create('github')->userFromCode($code);

// authorized
```

[Read more about `state` parameter](https://auth0.com/docs/protocols/oauth2/oauth-state)

### Additional parameters

To include any optional parameters in the request, call the `with()` method with an associative array:

```php
$response = $socialite->create('google')
                    ->with(['hd' => 'example.com'])->redirect();
```


## User interface

### Standard user api:

```php
$user = $socialite->create('github')->userFromCode($code);
```

```json
{
  "id": 1472352,
  "nickname": "overtrue",
  "name": "安正超",
  "email": "anzhengchao@gmail.com",
  "avatar": "https://avatars.githubusercontent.com/u/1472352?v=3",
  "raw": {
    "login": "overtrue",
    "id": 1472352,
    "avatar_url": "https://avatars.githubusercontent.com/u/1472352?v=3",
    "gravatar_id": "",
    "url": "https://api.github.com/users/overtrue",
    "html_url": "https://github.com/overtrue",
    ...
  },
  "token_response": {
    "access_token": "5b1dc56d64fffbd052359f032716cc4e0a1cb9a0",
    "token_type": "bearer",
    "scope": "user:email"
  }
}
```

You can fetch the user attribute as a array keys like these:

```php
$user['id'];        // 1472352
$user['nickname'];  // "overtrue"
$user['name'];      // "安正超"
$user['email'];     // "anzhengchao@gmail.com"
...
```

Or using the method:

```php
mixed   $user->getId();
?string $user->getNickname();
?string $user->getName();
?string $user->getEmail();
?string $user->getAvatar();
?string $user->getRaw();
?string $user->getAccessToken(); 
?string $user->getRefreshToken();
?int    $user->getExpiresIn();
?array  $user->getTokenResponse();


```

### Get raw response from OAuth API

The `$user->getRaw()` method will return an **array** of the API raw response.

### Get the token response when you use userFromCode()

The `$user->getTokenResponse()` method will return an **array** of the get token(access token) API response.

> Note: This method only return a **valid array** when you use `userFromCode()`, else will return **null** because use `userFromToken()` have no token response. 

### Get user with access token

```php
$accessToken = 'xxxxxxxxxxx';
$user = $socialite->userFromToken($accessToken);
```



# Enjoy it! :heart:

# Reference

- [Alipay - 用户信息授权](https://opendocs.alipay.com/open/289/105656)
- [DingTalk - 扫码登录第三方网站](https://ding-doc.dingtalk.com/doc#/serverapi3/mrugr3)
- [Google - OpenID Connect](https://developers.google.com/identity/protocols/OpenIDConnect)
- [Github - Authorizing OAuth Apps](https://developer.github.com/apps/building-oauth-apps/authorizing-oauth-apps/)
- [Facebook - Graph API](https://developers.facebook.com/docs/graph-api)
- [Linkedin - Authenticating with OAuth 2.0](https://developer.linkedin.com/docs/oauth2)
- [微博 - OAuth 2.0 授权机制说明](http://open.weibo.com/wiki/%E6%8E%88%E6%9D%83%E6%9C%BA%E5%88%B6%E8%AF%B4%E6%98%8E)
- [QQ - OAuth 2.0 登录 QQ](http://wiki.connect.qq.com/oauth2-0%E7%AE%80%E4%BB%8B)
- [腾讯云 - OAuth2.0](https://cloud.tencent.com/document/product/306/37730#.E6.8E.A5.E5.85.A5.E8.85.BE.E8.AE.AF.E4.BA.91-oauth)
- [微信公众平台 - OAuth 文档](http://mp.weixin.qq.com/wiki/9/01f711493b5a02f24b04365ac5d8fd95.html)
- [微信开放平台 - 网站应用微信登录开发指南](https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419316505&token=&lang=zh_CN)
- [微信开放平台 - 代公众号发起网页授权](https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419318590&token=&lang=zh_CN)
- [企业微信 - OAuth 文档](https://open.work.weixin.qq.com/api/doc/90000/90135/91020)
- [企业微信第三方应用 - OAuth 文档](https://open.work.weixin.qq.com/api/doc/90001/90143/91118)
- [豆瓣 - OAuth 2.0 授权机制说明](http://developers.douban.com/wiki/?title=oauth2)
- [抖音 - 网站应用开发指南](http://open.douyin.com/platform/doc)
- [飞书 - 授权说明](https://open.feishu.cn/document/ukTMukTMukTM/uMTNz4yM1MjLzUzM)
- [Tapd - 用户授权说明](https://www.tapd.cn/help/show#1120003271001000093)
- [Line - OAuth 2.0](https://developers.line.biz/en/docs/line-login/integrate-line-login/)
- [Gitee - OAuth文档](https://gitee.com/api/v5/oauth_doc#/)

[![Sponsor me](https://raw.githubusercontent.com/overtrue/overtrue/master/sponsor-me.svg)](https://github.com/sponsors/overtrue)

## Project supported by JetBrains

Many thanks to Jetbrains for kindly providing a license for me to work on this and other open-source projects.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/overtrue)


# PHP 扩展包开发

> 想知道如何从零开始构建 PHP 扩展包？
>
> 请关注我的实战课程，我会在此课程中分享一些扩展开发经验 —— [《PHP 扩展包实战教程 - 从入门到发布》](https://learnku.com/courses/creating-package)

# License

MIT
