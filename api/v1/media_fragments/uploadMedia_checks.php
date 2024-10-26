<?php

require_once $settings->getSetting('absPathToRoot').'vendor/autoload.php';

$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.AllowedElements', []);
$purifier = new HTMLPurifier($config);
//Category
if($inputs['category'] !== null){
    if(!in_array($inputs['category'],['img','vid'])){
        if($test)
            echo 'Upload category must be either "img" or "vid"!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else
    $inputs['category'] = 'img';

//Type
if($inputs['type'] !== null){
    if(!in_array($inputs['type'],['local','db','link'])){
        if($test)
            echo 'Upload type must be either "local" or "db" or "link"!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else
    $inputs['type'] = 'local';

//Items
if($inputs['items'] !== null){
    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['items'])){
        if($test)
            echo 'items must be a json array, or unset!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['items'] = json_decode($inputs['items'] ,true);
}
else
    $inputs['items'] = [];

foreach($inputs['items'] as $uploadName => $itemArray){

    $purifyArr = ['caption','name','alt'];
    $nameArr = ['name'];
    $captionArr = ['caption'];

    foreach($languages as $lang){
        $nameArr[] = $lang . '_name';
        $captionArr[] = $lang . '_caption';
        $purifyArr[] = $lang . '_name';
        $purifyArr[] = $lang . '_caption';
    }

    foreach($purifyArr as $param){
        if(!empty($itemArray[$param]))
            $itemArray[$param] = $purifier->purify($itemArray[$param]);
    }

    if(!preg_match('/'.UPLOAD_NAME_REGEX.'/',$uploadName)){
        if($test)
            echo 'Invalid upload name for of an item!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    if(isset($itemArray['filename'])){

        if($inputs['type'] !== 'link'){
            if(!preg_match('/'.UPLOAD_FILENAME_REGEX.'/',$itemArray['filename'])){
                if($test)
                    echo 'Invalid upload file name for '.$uploadName.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
        else{
            if(!filter_var($itemArray['filename'], FILTER_VALIDATE_URL)){
                if($test)
                    echo 'Invalid url '.$uploadName.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }

        if( !( $auth->hasAction(IMAGE_FILENAME_AUTH) || $auth->isAuthorized() ) ){
            if($test)
                echo 'Cannot upload media with specific filename!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }
    elseif($inputs['type'] === 'link'){
        if($test)
            echo 'Filename must be set for each item in link mode!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    foreach($nameArr as $nameParam)
        if(isset($itemArray[$nameParam])){

            if(strlen($itemArray[$nameParam])>IMAGE_NAME_MAX_LENGTH){
                if($test)
                    echo 'Invalid media name for '.$uploadName.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }

            if( !( $auth->hasAction(IMAGE_NAME_AUTH) || $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized() ) ){
                if($test)
                    echo 'Cannot upload media with specific name!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }

            $inputs['items'][$uploadName][$nameParam] = $itemArray[$nameParam];

        }
    foreach($captionArr as $captionParam)
        if(isset($itemArray[$captionParam])){

            if(strlen($itemArray[$captionParam])>IMAGE_CAPTION_MAX_LENGTH){
                if($test)
                    echo 'Invalid media caption for '.$uploadName.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }

            if( !( $auth->hasAction(IMAGE_CAPTION_AUTH) || $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized() ) ){
                if($test)
                    echo 'Cannot upload media with specific captions!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }

            $inputs['items'][$uploadName][$captionParam] = $itemArray[$captionParam];
        }

    if($inputs['category'] === 'img'){
        if(isset($itemArray['alt'])){

            if(strlen($itemArray['alt'])>IMAGE_ALT_MAX_LENGTH){
                if($test)
                    echo 'Invalid media alt tag for '.$uploadName.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }

            if( !( $auth->hasAction(IMAGE_ALT_AUTH) || $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized() ) ){
                if($test)
                    echo 'Cannot upload media with specific alt tags!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }

        }
    }
    elseif($inputs['category'] === 'vid'){
        $booleanArr = [
            'autoplay'=>false,
            'loop'=>true,
            'mute'=>true,
            'controls'=>false
        ];
        foreach($booleanArr as $boolName => $defaultValue){
            $inputs['items'][$uploadName][$boolName] = isset($itemArray[$boolName])?(bool)$itemArray[$boolName] : $defaultValue;
        }
        if(isset($itemArray['poster'])){
            if(!filter_var($itemArray['poster'], FILTER_VALIDATE_URL)){
                if($test)
                    echo 'poster must be an media URL!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
        if(isset($itemArray['preload'])){
            if(!in_array($itemArray['preload'],['none'.'auto','metadata'])){
                if($test)
                    echo 'preload must be "none", "auto" or "metadata"!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            else
                $inputs['items'][$uploadName]['preload'] = 'auto';
        }
    }
}

if($inputs['type'] !== 'link'){
//Address
    if($inputs['address'] === null)
        $inputs['address'] = '';

    if(!\IOFrame\Util\ValidatorFunctions::validateRelativeDirectoryPath($inputs['address'])){
        if($test)
            echo 'Invalid address name for media upload!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    if($inputs['address'] !== '' && $inputs['address'][strlen($inputs['address'])-1] !== '/')
        $inputs['address'] .= '/';

//Image quality
    if($inputs['imageQualityPercentage'] === null)
        $inputs['imageQualityPercentage'] = $resourceSettings->getSetting('imageQualityPercentage');

    if($inputs['imageQualityPercentage']<0)
        $inputs['imageQualityPercentage'] = 0;
    elseif($inputs['imageQualityPercentage']>100)
        $inputs['imageQualityPercentage'] = 100;
}

//Overwrite
if($inputs['overwrite'] === null)
    $inputs['overwrite'] = false;
else{
    if( !( $auth->hasAction(IMAGE_OVERWRITE_AUTH) || $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized() ) ){
        if($test)
            echo 'Cannot overwrite media!'.EOL;
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

//Files
if($inputs['type'] !== 'link' && is_array($_FILES))
    foreach($_FILES as $name=>$fileArray){
        $array = explode('.', $fileArray['name']);
        $extension = @array_pop($array); //Yes I know only variables should be passed by reference stfu
        if(
            ($inputs['category'] === 'img' && !in_array($extension,ALLOWED_EXTENSIONS_IMAGES)) ||
            ($inputs['category'] === 'vid' && !in_array($extension,ALLOWED_EXTENSIONS_VIDEO))
        ){
            if($test)
                echo 'File type of '.$name.' not allowed!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        elseif(
            ($inputs['category'] === 'img' && explode('/',$fileArray['type'])[0] !== 'image') ||
            ($inputs['category'] === 'vid' && explode('/',$fileArray['type'])[0] !== 'video')
        ){
            if($test)
                echo 'Data type of '.$name.' must be '.($inputs['category'] === 'img' ? 'image' : 'video').'!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }








