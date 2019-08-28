<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

//Gallery
if($inputs['relativeAddress'] !== null){
    if(!\IOFrame\Util\validator::validateRelativeDirectoryPath($inputs['relativeAddress'])){
        if($test)
            echo 'Invalid address!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else{
    $inputs['relativeAddress'] = '';
}

//Name - folder name is similar to upload filename in terms of restrictions
if($inputs['name'] !== null){
    if(!preg_match('/'.UPLOAD_FILENAME_REGEX.'/',$inputs['name'])){
        if($test)
            echo 'Invalid folder name!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else
    $inputs['name'] = 'New Folder';

//TODO check gallery-specific auth first, then do this if it fails
if( !( $auth->hasAction(MEDIA_FOLDER_CREATE_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot create media folders!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}