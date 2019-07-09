<?php

function validateCSRFToken(){
    return isset($_REQUEST['CSRF_token']) && $_REQUEST['CSRF_token'] === $_SESSION['CSRF_token'];
}

function validateThenRefreshCSRFToken($sessionHandler){
    $res = validateCSRFToken();
    if($res)
        $sessionHandler->reset_CSRF_token();
    return $res;
}

