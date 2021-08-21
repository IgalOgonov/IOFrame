<?php
if(!isset($_SESSION['MAIL_CHANGE_ID']) || !isset($_SESSION['MAIL_CHANGE_EXPIRES']) ){
    if($test)
        echo 'Mail change not authorized!';
    exit(AUTHENTICATION_FAILURE);
}
