# KakaoLink-PHP
the kakaolink module on php

use like:
```php
$kakao = new Kakao(/* your domain with Http/Https */);
$kakao->init(/* your API key */);
$kakao->login("email", "pw");
$kakao->send("roomname", // or array like ["room1", "room2"]
// custom type arguments
    array(
    "link_ver" => "4.0",
    "template_id" => 00000,
    "template_args" => array(/* not empty */)
    ),
"custom");
```
