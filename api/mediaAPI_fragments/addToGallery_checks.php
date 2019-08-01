<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

//Check auth
if( !( $auth->hasAction(GALLERY_UPDATE_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot modify galleries, or their memberships!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

//Addresses
if($inputs['addresses'] === null || !IOFrame\Util\is_json($inputs['addresses'])){
    if($test)
        echo 'Addresses need to be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

$inputs['addresses'] = json_decode($inputs['addresses'],true);

if(count($inputs['addresses'])<1){
    if($test)
        echo 'Addresses need to contain something!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

foreach($inputs['addresses'] as $index => $address){
    //TODO Add address specific auth check
    if(!\IOFrame\Util\validator::validateRelativeFilePath($address)){
        if($test)
            echo 'Invalid address at index '.$index.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//TODO Add gallery specific auth check
if($inputs['gallery'] === null){
    if($test)
        echo 'Gallery name must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);

}

if(!preg_match('/'.GALLERY_REGEX.'/',$inputs['gallery'])){
    if($test)
        echo 'Gallery name invalid!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}