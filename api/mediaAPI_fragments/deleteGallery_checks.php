<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

//Gallery
if($inputs['gallery'] !== null){
    if(!preg_match('/'.GALLERY_REGEX.'/',$inputs['gallery'])){
        if($test)
            echo 'Invalid gallery name!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//TODO check gallery-specific auth first, then do this if it fails
if( !( $auth->hasAction(GALLERY_DELETE_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot delete galleries!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}