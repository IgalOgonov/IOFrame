<?php

function validateCSRFToken(): bool {
    switch($_SERVER['REQUEST_METHOD']){
        case 'PUT':
            $put_vars = [];
            $serverParams = isset($psrRequest)? $psrRequest->getServerParams() : $_SERVER;
            \IOFrame\Util\HttpFunctions::parseRawHTTPRequest($put_vars,$serverParams);
            $res = ( isset($put_vars['CSRF_token']) && $put_vars['CSRF_token'] === $_SESSION['CSRF_token'] ) ||
                (!empty($_SESSION['CSRF_validated']) && $_SESSION['CSRF_validated'] > time());
            unset($put_vars);
            return $res;
        default:
            return ( isset($_REQUEST['CSRF_token']) && $_REQUEST['CSRF_token'] === $_SESSION['CSRF_token'] ) ||
                (!empty($_SESSION['CSRF_validated']) && $_SESSION['CSRF_validated'] > time());
    }
}

//TODO Once personal user tokens (not generic Tokens, specific ones for APIs) are implemented, allow checking for them instead.
function validateThenRefreshCSRFToken($SessionManager): bool {
    $res = validateCSRFToken();
    if($res){
        //Prolong CSRF_validated but reset the token
        $_SESSION['CSRF_validated'] = time() + 10;
        $SessionManager->reset_CSRF_token();
    }
    return $res;
}

