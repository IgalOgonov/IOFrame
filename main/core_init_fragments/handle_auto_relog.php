<?php

if(empty($SessionManager))
    require 'initiate_session.php';

if(
    (!$SessionManager->checkSessionNotExpired() || !isset($_SESSION['logged_in'])) &&
    isset($_COOKIE['sesID']) && $_COOKIE['sesID'] &&
    isset($_COOKIE['sesIV']) && $_COOKIE['sesIV'] &&
    isset($_COOKIE['userMail']) && $_COOKIE['userMail'] &&
    isset($_COOKIE['userID']) && $_COOKIE['userID']
){
    $userID = $_COOKIE['userID'];
    $sesID = $_COOKIE['sesID'];

    $key = \IOFrame\Util\PureUtilFunctions::stringScrumble($_COOKIE['sesID'],$_COOKIE['sesIV']);
    $sesKey = bin2hex(base64_decode(openssl_encrypt($userID,'aes-256-ecb' , hex2bin($key) ,OPENSSL_ZERO_PADDING)));

    //Try to log in
    $UsersHandler = new \IOFrame\Handlers\UsersHandler(
        $settings,
        $defaultSettingsParams
    );
    $inputs = [
        'log'=>'temp',
        'sesKey'=> $sesKey,
        'm'=> $_COOKIE['userMail'],
        'userID'=>$userID
    ];

    //The result wont matter, since if we fail, the sesID wont change, but otherwise it will
    $res = $UsersHandler->logIn($inputs);
    $success = false;

    if(
        gettype($res) === 'string' &&
        strlen($res) === 128
    ){
        $res = openssl_decrypt(base64_encode(hex2bin($res)),'aes-256-ecb', hex2bin( $key ), OPENSSL_ZERO_PADDING );
        $res = \IOFrame\Util\PureUtilFunctions::stringDescrumble($res);
        $oldID = $res[0];
        $newID = $res[1];
        //Should always happen with https only cookies, but here just for consistency
        if($oldID === $sesID){
            //Should not matter at this point, but whatever
            $_COOKIE['sesID'] = $newID;
            setcookie("sesID", $newID, time()+(60*60*24*365),'/','', 1, 1);
            $success = true;
        }
        else
            $res = 'POSSIBLE_FAKE_SERVER';
    }

    //This is how we check if it worked - if $sesID is still the same, we didn't relog
    if(!$success){
        unset($_COOKIE['lastRelogResult']);
        setcookie("lastRelogResult", $res, time()+(60*60*24*365),'/','', 1, 1);
        unset($_COOKIE['sesID']);
        setcookie("sesID", '', -1,'/');
        unset($_COOKIE['sesIV']);
        setcookie("sesIV", '', -1,'/');
        unset($_COOKIE['userMail']);
        setcookie("userMail", '', -1,'/');
    }
    /*Auth remembers whether we are logged in*/
    else
        require 'initiate_auth.php';

    setcookie("lastRelog", time(), time()+(60*60*24*365),'/','', 1, 1);
    setcookie("lastRelogResult", ($success ? 'success' : $res), time()+(60*60*24*365),'/','', 1, 1);
}