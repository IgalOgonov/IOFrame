<?php

function validateCSRFToken(){
    switch($_SERVER['REQUEST_METHOD']){
        case 'PUT':
            $put_vars = [];
            $serverParams = isset($psrRequest)? $psrRequest->getServerParams() : $_SERVER;
            IOFrame\Util\parse_raw_http_request($put_vars,$serverParams);
            $res = ( isset($put_vars['CSRF_token']) && $put_vars['CSRF_token'] === $_SESSION['CSRF_token'] ) ||
                (!empty($_SESSION['CSRF_validated']) && $_SESSION['CSRF_validated'] > time());
            unset($put_vars);
            return $res;
            break;
        default:
            return ( isset($_REQUEST['CSRF_token']) && $_REQUEST['CSRF_token'] === $_SESSION['CSRF_token'] ) ||
                (!empty($_SESSION['CSRF_validated']) && $_SESSION['CSRF_validated'] > time());
    }
}

function validateThenRefreshCSRFToken($SessionHandler){
    $res = validateCSRFToken();
    if($res){
        //Prolong CSRF_validated but reset the token
        $_SESSION['CSRF_validated'] = time() + 10;
        $SessionHandler->reset_CSRF_token();
    }
    return $res;
}

