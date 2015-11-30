# Socialite

Socialite is a collection of OAuth 2 packages that extracts from [laravel/socialite](https://github.com/laravel/socialite).

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

$socialite->driver('weibo')->redirect()->response();
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

# License

MIT