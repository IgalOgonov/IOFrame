<?php

function validateCSRFToken(){
    return isset($_REQUEST['CSRF_token']) && $_REQUEST['CSRF_token'] === $_SESSION['CSRF_token'];
}

function validateThenRefreshCSRFToken($SessionHandler){
    $res = validateCSRFToken();
    if($res)
        $SessionHandler->reset_CSRF_token();
    return $res;
}

