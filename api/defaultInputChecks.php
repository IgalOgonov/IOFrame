<?php

//This always indicates test mode
require_once 'defaultTestChecks.php';

//Fix any values that are strings due to softly typed language bullshit
foreach($_REQUEST as $key=>$value){
    if($_REQUEST[$key] === '')
        unset($_REQUEST[$key]);
    else if($_REQUEST[$key] === 'false')
        $_REQUEST[$key] = false;
    else if($_REQUEST[$key] === 'true')
        $_REQUEST[$key] = true;
    else if(preg_match('/\D/',$_REQUEST[$key]) == 0)
        $_REQUEST[$key] = (int)$_REQUEST[$key];
}





