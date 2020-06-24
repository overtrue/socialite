<h1 align="center"> Socialite</h1>
<p align="center">
<a href="https://packagist.org/packages/overtrue/socialite"><img src="https://poser.pugx.org/overtrue/socialite/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/overtrue/socialite"><img src="https://poser.pugx.org/overtrue/socialite/v/unstable.svg" alt="Latest Unstable Version"></a>
<a href="https://scrutinizer-ci.com/g/overtrue/socialite/build-status/master"><img src="https://scrutinizer-ci.com/g/overtrue/socialite/badges/build.png?b=master" alt="Build Status"></a>
<a href="https://scrutinizer-ci.com/g/overtrue/socialite/?branch=master"><img src="https://scrutinizer-ci.com/g/overtrue/socialite/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality"></a>
<a href="https://scrutinizer-ci.com/g/overtrue/socialite/?branch=master"><img src="https://scrutinizer-ci.com/g/overtrue/socialite/badges/coverage.png?b=master" alt="Code Coverage"></a>
<a href="https://packagist.org/packages/overtrue/socialite"><img src="https://poser.pugx.org/overtrue/socialite/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/overtrue/socialite"><img src="https://poser.pugx.org/overtrue/socialite/license" alt="License"></a>
</p>


<p align="center">Socialite is an OAuth2 Authentication tool. It is inspired by <a href="https://github.com/laravel/socialite">laravel/socialite</a>, You can easily use it in any PHP project.</p>

# Requirement

```
PHP >= 7.4
```
# Installation

```shell
$ composer require "overtrue/socialite" -vvv
```

# Usage

For Laravel 5: [overtrue/laravel-socialite](https://github.com/overtrue/laravel-socialite)

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

$url = $socialite->driver('github')->redirect();

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

$user = $socialite->driver('github')->userFromCode($code);

$user->getId();        // 1472352
$user->getNickname();  // "overtrue"
$user->getUsername();  // "overtrue"
$user->getName();      // "安正超"
$user->getEmail();     // "anzhengchao@gmail.com"
...
```

### Configuration

Now we support the following sites:

`Alipay`, `Dingtalk`, `facebook`, `github`, `google`, `linkedin`, `outlook`, `weibo`, `taobao`, `qq`, `wechat`, `douyin`, `baidu`, `feishu`, and `douban`.

Each driver uses the same configuration keys: `client_id`, `client_secret`, `redirect`.

Example:
```
...
  'weibo' => [
    'client_id'     => 'your-app-id',
    'client_secret' => 'your-app-secret',
    'redirect'      => 'http://localhost/socialite/callback.php',
  ],
...
```

##### [Alipay](https://opendocs.alipay.com/open/200/105310#s2)
If you want to use `alipay` driver, you need set config like below.
```php
...
  'alipay' => [
    // the key, pointed by the key value of this array, can also be named as 'app_id' like the official documentation.
    'client_id' => 'your-app-id', 
 
    // Please refer to the official documentation, in the official management background configuration RSA2.
    // Note: This is your own private key.
    // Note: Do not allow the private key content to have extra characters.
    // Recommendation: For security, you can read directly from the file. But here as long as the value, please remember to remove the head and tail of the decoration.
    'rsa_private_key' => 'your-rsa-private-key',

    // Be sure to set this value and make sure that it is the same address value as set in the official admin system.
    // the key, pointed by the key value of this array, can also be named as 'redirect_url' like the official documentation.
    'redirect' => 'http://localhost/socialite/callback.php',
  ],
...
```
Only RSA2 personal private keys are supported, so stay tuned if you want to log in with a certificate.

##### [DingTalk](https://ding-doc.dingtalk.com/doc#/serverapi3/mrugr3)

Follow the documentation and configure it in the development panel.

Note: We only support QR code access to third-part websites. i.e exchange for user information(opendid, unionid and nickname)

```php
...
'dingtalk' => [
    // or 'app_id'
    'client_id' => 'your app id',

    // or 'app_secret' 
    'client_secret' => 'your app secret',

    // or 'redirect_url'
    'redirect' => 'redirect URL'
],
...
```

##### [Douyin](https://open.douyin.com/platform/doc/OpenAPI-oauth2)

Note using the Douyin driver that if you get user information directly using access token, set up the openid first. the openid can be obtained by code when access is obtained, so call `userFromCode()` automatically configured for you openid, if call `userFromToken()` first call `withOpenId()`

```php
$user = $socialite->driver('douyin')->userFromCode('here is auth code');

$user = $socialite->driver('douyin')->withOpenId('openId')->userFromToken('here is a access token');
```


##### [Baidu](https://developer.baidu.com/wiki/index.php?title=docs/oauth)

You can choose the form you want display by using `withDisplay()`.

```php
$authUrl = $socialite->driver('baidu')->withDisplay('mobile')->redirect();

```
`popup` mode is the default setting with display. `basic` is the default with scopes.

##### [Taobao](https://open.taobao.com/doc.htm?docId=102635&docType=1&source=search)

You can choose the form you want display by using `withView()`.

```php
$authUrl = $socialite->driver('taobao')->withView('wap')->redirect();
```
`web` mode is the default setting with display. `user_info` is the default with scopes.

##### [WeChat](https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/Official_Accounts/official_account_website_authorization.html)

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
                // or 'app_id', 'component_app_id' as key
                'id' => 'component-app-id',
                // or 'app_token', 'access_token', 'component_access_token' as key
                'token' => 'component-access-token',
            ]
        ]
],
...
```


### Scope

Before redirecting the user, you may also set "scopes" on the request using the scope method. This method will overwrite all existing scopes:

```php
$response = $socialite->driver('github')
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

#### Redirect with `state` parameter 
```php
<?php
session_start();
 
$config = [
    //...
];

// Assign to state the hashing of the session ID
$state = hash('sha256', session_id());

$socialite = new SocialiteManager($config);

$url = $socialite->driver('github')->withState($state)->redirect();

return redirect($url); 
```

#### Validate the callback `state`

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
$user = $socialite->driver('github')->userFromCode($code);

// authorized
```

[Read more about `state` parameter](https://auth0.com/docs/protocols/oauth2/oauth-state)

### Additional parameters

To include any optional parameters in the request, call the with method with an associative array:

```php
$response = $socialite->driver('google')
                    ->with(['hd' => 'example.com'])->redirect();
```


### User interface

#### Standard user api:

```php
$user = $socialite->driver('github')->userFromCode($code);
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

#### Get raw response from OAuth API

The `$user->getRaw()` method will return an **array** of the API raw response.

#### Get the token response when you use userFromCode()

The `$user->getTokenResponse()` method will return an **array** of the get token(access token) API response.

> Note: This method only return a **valid array** when you use `userFromCode()`, else will return **null** because use `userFromToken()` have no token response. 

### Get user with access token

```php
$accessToken = 'xxxxxxxxxxx';
$user = $socialite->userFromToken($accessToken);
```

Enjoy it! :heart:

# Reference

- [Alipay - 用户信息授权](https://opendocs.alipay.com/open/289/105656)
- [DingTalk - 扫码登录第三方网站](https://ding-doc.dingtalk.com/doc#/serverapi3/mrugr3)
- [Google - OpenID Connect](https://developers.google.com/identity/protocols/OpenIDConnect)
- [Github - Authorizing OAuth Apps](https://developer.github.com/apps/building-oauth-apps/authorizing-oauth-apps/)
- [Facebook - Graph API](https://developers.facebook.com/docs/graph-api)
- [Linkedin - Authenticating with OAuth 2.0](https://developer.linkedin.com/docs/oauth2)
- [微博 - OAuth 2.0 授权机制说明](http://open.weibo.com/wiki/%E6%8E%88%E6%9D%83%E6%9C%BA%E5%88%B6%E8%AF%B4%E6%98%8E)
- [QQ - OAuth 2.0 登录QQ](http://wiki.connect.qq.com/oauth2-0%E7%AE%80%E4%BB%8B)
- [腾讯云 - OAuth2.0](https://cloud.tencent.com/document/product/628/38393)
- [微信公众平台 - OAuth文档](http://mp.weixin.qq.com/wiki/9/01f711493b5a02f24b04365ac5d8fd95.html)
- [微信开放平台 - 网站应用微信登录开发指南](https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419316505&token=&lang=zh_CN)
- [微信开放平台 - 代公众号发起网页授权](https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419318590&token=&lang=zh_CN)
- [豆瓣 - OAuth 2.0 授权机制说明](http://developers.douban.com/wiki/?title=oauth2)
- [抖音 - 网站应用开发指南](http://open.douyin.com/platform/doc)
- [飞书 - 授权说明](https://open.feishu.cn/document/ukTMukTMukTM/uMTNz4yM1MjLzUzM)

## PHP 扩展包开发

> 想知道如何从零开始构建 PHP 扩展包？
>
> 请关注我的实战课程，我会在此课程中分享一些扩展开发经验 —— [《PHP 扩展包实战教程 - 从入门到发布》](https://learnku.com/courses/creating-package)

# License

MIT
