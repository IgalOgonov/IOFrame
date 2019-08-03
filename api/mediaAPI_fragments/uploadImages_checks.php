<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

if( !( $auth->hasAction(IMAGE_UPLOAD_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot upload images'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

//Items
if($inputs['items'] !== null){
    if(!\IOFrame\Util\is_json($inputs['items'])){
        if($test)
            echo 'items must be a json array, or unset!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['items'] = json_decode($inputs['items'] ,true);
}
else
    $inputs['items'] = [];

foreach($inputs['items'] as $uploadName => $itemArray){

    if(!preg_match('/'.UPLOAD_NAME_REGEX.'/',$uploadName)){
        if($test)
            echo 'Invalid upload name for of an item!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    if(isset($itemArray['filename'])){

        if(!preg_match('/'.UPLOAD_FILENAME_REGEX.'/',$itemArray['filename'])){
            if($test)
                echo 'Invalid upload file name for '.$uploadName.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if( !( $auth->hasAction(IMAGE_FILENAME_AUTH) || $auth->isAuthorized(0) ) ){
            if($test)
                echo 'Cannot upload images with specific filename!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }

    if(isset($itemArray['alt'])){

        if(strlen($itemArray['alt'])>IMAGE_ALT_MAX_LENGTH){
            if($test)
                echo 'Invalid image alt tag for '.$uploadName.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if( !( $auth->hasAction(IMAGE_ALT_AUTH) || $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized(0) ) ){
            if($test)
                echo 'Cannot upload images with specific alt tags!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }

    }

    if(isset($itemArray['name'])){

        if(strlen($itemArray['name'])>IMAGE_NAME_MAX_LENGTH){
            if($test)
                echo 'Invalid image name for '.$uploadName.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if( !( $auth->hasAction(IMAGE_NAME_AUTH) || $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized(0) ) ){
            if($test)
                echo 'Cannot upload images with specific name!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }

    }

    if(isset($itemArray['caption'])){

        if(strlen($itemArray['caption'])>IMAGE_CAPTION_MAX_LENGTH){
            if($test)
                echo 'Invalid image caption for '.$uploadName.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if( !( $auth->hasAction(IMAGE_CAPTION_AUTH) || $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized(0) ) ){
            if($test)
                echo 'Cannot upload images with specific captions!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }

    }
}

//Address
if($inputs['address'] === null)
    $inputs['address'] = '';

if(!\IOFrame\Util\validator::validateRelativeDirectoryPath($inputs['address'])){
    if($test)
        echo 'Invalid address name for image upload!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['address'][strlen($inputs['address'])-1] !== '/' && $inputs['address'] !== '')
    $inputs['address'] .= '/';

//Image quality
if($inputs['imageQualityPercentage'] === null)
    $inputs['imageQualityPercentage'] = $resourceSettings->getSetting('imageQualityPercentage');

if($inputs['imageQualityPercentage']<0)
    $inputs['imageQualityPercentage'] = 0;
elseif($inputs['imageQualityPercentage']>100)
    $inputs['imageQualityPercentage'] = 100;

//Overwrite
if($inputs['overwrite'] === null)
    $inputs['overwrite'] = false;
else{
    if( !( $auth->hasAction(IMAGE_OVERWRITE_AUTH) || $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized(0) ) ){
        if($test)
            echo 'Cannot overwrite images!'.EOL;
        exit(AUTHENTICATION_FAILURE);
    }
    else
        $inputs['overwrite'] = true;
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










