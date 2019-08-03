<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

//Address
if($inputs['address'] !== null){

    if(!\IOFrame\Util\validator::validateRelativeFilePath($inputs['address'])){
        if($test)
            echo 'Invalid address!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    $inputs['addresses'] = [$inputs['address']];
}
else{
    if($test)
        echo 'Address must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//Alt and Name
$meta = [];

if($inputs['alt'] === null && $inputs['name'] === null && $inputs['caption'] === null ){
    if($test)
        echo 'Either alt, caption or name have to be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['alt'] !== null){

    if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_ALT_AUTH) || $auth->isAuthorized(0) ) ){
        if($test)
            echo 'Cannot change image alt tag!'.EOL;
        exit(AUTHENTICATION_FAILURE);
    }

    if(strlen($inputs['alt'])>IMAGE_ALT_MAX_LENGTH){
        if($test)
            echo 'Maximum alt length: '.IMAGE_ALT_MAX_LENGTH.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    $meta['alt'] = $inputs['alt'];
}

if($inputs['name'] !== null){

    if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_NAME_AUTH) || $auth->isAuthorized(0) ) ){
        if($test)
            echo 'Cannot change image name!'.EOL;
        exit(AUTHENTICATION_FAILURE);
    }

    if(strlen($inputs['name'])>IMAGE_NAME_MAX_LENGTH){
        if($test)
            echo 'Maximum name length: '.IMAGE_NAME_MAX_LENGTH.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    $meta['name'] = $inputs['name'];
}


if($inputs['caption'] !== null){

    if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_CAPTION_AUTH) || $auth->isAuthorized(0) ) ){
        if($test)
            echo 'Cannot change image caption!'.EOL;
        exit(AUTHENTICATION_FAILURE);
    }

    if(strlen($inputs['caption'])>IMAGE_CAPTION_MAX_LENGTH){
        if($test)
            echo 'Maximum caption length: '.IMAGE_CAPTION_MAX_LENGTH.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    $meta['caption'] = $inputs['caption'];
}


$meta = json_encode($meta);
