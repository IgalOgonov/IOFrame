<?php
if($siteSettings->getSetting('capcha_site_key') && $siteSettings->getSetting('capcha_secret_key')){
    $site = $siteSettings->getSetting('capcha_site_key');
    $secret = $siteSettings->getSetting('capcha_secret_key');

    if(empty($_REQUEST['captcha']))
        die(CAPTCHA_MISSING);

    $post = [
        'response='.$_REQUEST['captcha'],
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
    curl_close ($ch);

    //TODO log failure
    if(empty($data))
        die(CAPTCHA_SERVER_FAILURE);
    else
        $data = json_decode($data,true);

    if($data['success']){
        //TODO log success - challenge_ts and hostname
    }
    else
        switch ($data['error-codes'][0]){
            case 'missing-input-secret':
            case 'invalid-input-secret':
            case 'missing-input-response':
            case 'bad-request':
            case 'sitekey-secret-mismatch':
                //TODO log failure
                die(CAPTCHA_SERVER_FAILURE);
            case 'invalid-input-response':
                die(CAPTCHA_INVALID);
            case 'invalid-or-already-seen-response':
                die(CAPTCHA_ALREADY_VALIDATED);
        }
}