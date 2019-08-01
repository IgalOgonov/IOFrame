<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

//One type of auth to overwrite existing galleries..
if( $inputs['update']  || $inputs['overwrite'] ){
    if( !( $auth->hasAction(GALLERY_UPDATE_AUTH) || $auth->isAuthorized(0) ) ){
        if($test)
            echo 'Cannot update/overwrite existing galleries!'.EOL;
        exit(AUTHENTICATION_FAILURE);
    }
}

//..Another to just create new ones
if( !$inputs['update'] ){
    if( !( $auth->hasAction(GALLERY_CREATE_AUTH) || $auth->isAuthorized(0) ) ){
        if($test)
            echo 'Cannot create new galleries galleries!'.EOL;
        exit(AUTHENTICATION_FAILURE);
    }
}


if( !( $auth->hasAction(IMAGE_UPLOAD_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot upload images'.EOL;
    exit(AUTHENTICATION_FAILURE);
}


//Gallery
if($inputs['gallery'] !== null){
    if(!preg_match('/'.GALLERY_REGEX.'/',$inputs['gallery'])){
        if($test)
            echo 'Invalid gallery name!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    //TODO Check gallery auth and ownership
}

//Meta information
if($inputs['name']){

    if(strlen($inputs['name'])>GALLERY_NAME_MAX_LENGTH){
        if($test)
            echo 'Invalid image name for '.$inputs['gallery'].EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    $meta = json_encode(['name'=>$inputs['name']]);

}
else
    $meta = null;









