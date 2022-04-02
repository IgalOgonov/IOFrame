<?php

//User input validator

if($inputs['id']!=null && (preg_match_all('/[0-9]/',$inputs['id'])<strlen($inputs['id'])) ){
    if($test)
        echo 'Illegal user id!' ;
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['code']!='' && (preg_match_all('/[a-z]|[A-Z]|[0-9]/',$inputs['code'])<strlen($inputs['code'])) ){
    if($test)
        echo 'Illegal confirmation code!' ;
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['mail']!==null && !filter_var($inputs['mail'],FILTER_VALIDATE_EMAIL) ){
    if($test)
        echo 'Illegal mail!.';
    exit(INPUT_VALIDATION_FAILURE);
}

if(!empty($inputs['language']))
    if(!preg_match('/'.LANGUAGE_REGEX.'/',$inputs['language'])){
        if($test)
            echo 'Language must match '.LANGUAGE_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
