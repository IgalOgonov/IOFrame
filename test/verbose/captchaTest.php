<?php

if(!$siteSettings->getSetting('captcha_site_key'))
    echo 'Site key missing!'.EOL;
elseif(!$siteSettings->getSetting('captcha_secret_key'))
    echo 'Secret key missing!'.EOL;
else{
    $site = $siteSettings->getSetting('captcha_site_key');
    $secret = $siteSettings->getSetting('captcha_secret_key');

    if(!empty($_REQUEST['email'])){
        echo 'Capcha request recieved:'.EOL;
        var_dump($_REQUEST);
        $post = [
            'response='.$_REQUEST['h-captcha-response'],
            'secret='.$secret,
            'sitekey='.$site,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL,"https://hcaptcha.com/siteverify");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $post));
        $data = curl_exec ($ch);
        $info = curl_getinfo($ch);
        curl_close ($ch);
        echo 'Response data:'.EOL;
        var_dump(json_decode($data,true));
        echo 'Response info:'.EOL;
        var_dump($info);
    }
    echo 'Capcha example:'.EOL;

    echo '
        <script>
        var capchaSuccess = function(response){
            console.log(\'success\',response);
        }
        var capchaExpired = function(response){
            console.log(\'expired\',response);
        }
        var capchaError = function(response){
            console.log(\'error\',response);
        }
        </script>
        <script src="https://hcaptcha.com/1/api.js" async defer></script>
        <form action="" method="POST">
          <input type="text" name="email" placeholder="Email" required />
          <input type="text" name="requireOnlyTab" value="captchaTest" hidden />
          <div class="h-captcha" data-sitekey="'.$site.'" data-callback="capchaSuccess" data-expired-callback="capchaExpired" data-error-callback="capchaError"></div>
          <br />
          <input type="submit" value="Submit" />
        </form>
    ';

}