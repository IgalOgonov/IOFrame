<?php
if(!empty($inputs['language']))
    if(!preg_match('/'.LANGUAGE_REGEX.'/',$inputs['language'])){
        if($test)
            echo 'Language must match '.LANGUAGE_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
if(!isset($_SESSION['Extra_2FA_Mail'])){
    if($test)
        echo 'Mail must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}