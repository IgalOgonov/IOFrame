<?php

//This always indicates test mode
require_once 'defaultTestChecks.php';

//Fix any values that are strings due to softly typed language bullshit
foreach($_REQUEST as $key=>$value){
    if($value === '')
        unset($_REQUEST[$key]);
    else if($value === 'false')
        $_REQUEST[$key] = false;
    else if($value === 'true')
        $_REQUEST[$key] = true;
    else if(preg_match('/\D/', $value) == 0)
        $_REQUEST[$key] = (int)$value;
}





