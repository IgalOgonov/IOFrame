<?php


//Check to see if the API is even enabled
if(!$resourceSettings->getSetting('allowDBMediaGet')){
    header("HTTP/1.1 403 API Disabled");
    die();
}