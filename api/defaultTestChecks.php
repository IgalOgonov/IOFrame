<?php

switch($_SERVER['REQUEST_METHOD']){
    case 'PUT':
        $put_vars = [];
        $serverParams = isset($psrRequest)? $psrRequest->getServerParams() : $_SERVER;
        IOFrame\Util\parse_raw_http_request($put_vars,$serverParams);
        $testRequest =  isset($put_vars['req']) && $put_vars['req'] == 'test';
        unset($put_vars);
        break;
    default:
        $testRequest =  isset($_REQUEST['req']) && $_REQUEST['req'] == 'test' ;
}

//This always indicates test mode
$test = $testRequest && ( $siteSettings->getSetting('allowTesting') || $auth->isAuthorized(0) || !empty($_SESSION['allowTesting']) );
unset($testRequest);



