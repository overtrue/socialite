

```
    use Overtrue\Socialite\SocialiteManager as Socialite;
    private $socialite;
    public function __construct(){
        $config = [
            'corp-wechat' => [
                'client_id' => "{公众号的corp_id}",
                'client_secret' => "{公众号的secret}",
                'redirect' => "oauth的回调地址, 以http开头, 如 http://www.xxx.com/oauth/callback",
            ],
            'longlive_access_token'=>false, //当这个值为false的时候, 本provider会自动获取, 当和其他例如overtrue/wechat一起使用的时候, 这里的值建议直接传入,否则会引起冲突
       ];
        $this->socialite = (new Socialite($config));
    }

    public function getAuth(){

        $response = $this->socialite->driver('corp-wechat')->scopes(['snsapi_base'])->redirect();
        return  $response;// or $response->send();     

    }

    public function getOauthcallback(){
        $user = $this->socialite->driver('corp-wechat')->user();
        print_r($user);

    }
```
