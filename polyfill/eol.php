<?php

if(!defined('EOL')){
    if (\php_sapi_name() == "cli") {
        define("EOL",PHP_EOL);
    } else {
        define("EOL",'<br>');
    }
}
define('EOL_FILE',\mb_convert_encoding('&#x000A;', 'UTF-8', 'HTML-ENTITIES'));