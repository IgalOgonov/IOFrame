<?php

if(!filter_var($inputs['newMail'],FILTER_VALIDATE_EMAIL)){
    if($test)
        echo 'Invalid new email!';
    exit('-1');
}

if(!isset($_SESSION['MAIL_CHANGE_ID']) || !isset($_SESSION['MAIL_CHANGE_EXPIRES']) ){
    if($test)
        echo 'Mail change not authorized!';
    exit('-2');
}

if($_SESSION['MAIL_CHANGE_EXPIRES']<time()){
    if($test)
        echo 'Mail change token expired!';
    exit('2');
}
