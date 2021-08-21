<?php
if($inputs['token']===null || !preg_match('/'.TOKEN_REGEX.'/',$inputs['token']) ){
    if($test)
        echo 'Illegal token!';
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['mail']!==null && !filter_var($inputs['mail'],FILTER_VALIDATE_EMAIL) ){
    if($test)
        echo 'Illegal mail!';
    exit(INPUT_VALIDATION_FAILURE);
}

?>