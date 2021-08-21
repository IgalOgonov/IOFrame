<?php

if(!isset($_SESSION['PWD_RESET_ID']) || !isset($_SESSION['PWD_RESET_EXPIRES']) ){
    if($test)
        echo 'Password reset not authorized!';
    exit(AUTHENTICATION_FAILURE);
}