<?php
if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';
require_once $settings->getSetting('absPathToRoot').'vendor/autoload.php';

$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.AllowedElements', []);
$purifier = new HTMLPurifier($config);

//Address
if($inputs['address'] !== null){

    $valid = \IOFrame\Util\validator::validateRelativeFilePath($inputs['address']);
    $valid = $valid || filter_var($inputs['address'],FILTER_VALIDATE_URL);

    if(!$valid){
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
$expected = ['name','caption'];
if($action === 'updateVideo')
    array_push($expected,'autoplay','loop','mute','controls','poster','preload');
else
    array_push($expected,'alt');
$purify = ['name','alt','caption'];
foreach($languages as $lang){
    array_push($expected,$lang.'_name');
    array_push($purify,$lang.'_name');
    array_push($expected,$lang.'_caption');
    array_push($purify,$lang.'_caption');
}
$anythingSet = false;
foreach($expected as $attr)
    if($inputs[$attr] !== null){
        $anythingSet = true;
        if(in_array($attr,$purify))
            $inputs[$attr] = $purifier->purify($inputs[$attr]);
    }

if($action === 'updateImage'){
    if($inputs['alt'] !== null){

        if(strlen($inputs['alt'])>IMAGE_ALT_MAX_LENGTH){
            if($test)
                echo 'Invalid media alt tag for '.$uploadName.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if( !( $auth->hasAction(IMAGE_ALT_AUTH) || $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized(0) ) ){
            if($test)
                echo 'Cannot upload media with specific alt tags!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
        $meta['alt'] = $inputs['alt'];
    }
}
elseif($action === 'updateVideo'){
    $booleanArr = [
        'autoplay',
        'loop',
        'mute',
        'controls'
    ];
    foreach($booleanArr as $boolName){
        if($inputs[$boolName] !== null)
            $meta[$boolName] = (bool)$inputs[$boolName];
    }
    if($inputs['poster'] !== null){
        if(!filter_var($inputs['poster'], FILTER_VALIDATE_URL)){
            if($test)
                echo 'poster must be an media URL!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $meta['poster'] = $inputs['poster'];
    }
    if($inputs['preload'] !== null){
        if(!in_array($inputs['preload'],['none','auto','metadata'])){
            if($test)
                echo 'preload must be "none", "auto" or "metadata"!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $meta['preload'] = $inputs['preload'];
    }
}

if(!$inputs['deleteEmpty'] && !$anythingSet ){
    if($test)
        echo 'With deleteEmpty, at least one meta attribute needs to be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

$nameArr = ['name'];
foreach($languages as $lang){
    array_push($nameArr,$lang.'_name');
}
foreach($nameArr as $nameParam)
    if($inputs[$nameParam] !== null){
        if(strlen($inputs[$nameParam])>IMAGE_NAME_MAX_LENGTH){
            if($test)
                echo 'Maximum name length: '.IMAGE_NAME_MAX_LENGTH.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $meta[$nameParam] = $inputs[$nameParam];
    }


$captionArr = ['caption'];
foreach($languages as $lang){
    array_push($captionArr,$lang.'_caption');
}
foreach($captionArr as $captionParam)
    if($inputs[$captionParam] !== null){
        if(strlen($inputs[$captionParam])>IMAGE_CAPTION_MAX_LENGTH){
            if($test)
                echo 'Maximum caption length: '.IMAGE_CAPTION_MAX_LENGTH.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $meta[$captionParam] = $inputs[$captionParam];
    }

if($inputs['deleteEmpty']){
    foreach($expected as $attr)
        if($inputs[$attr] === null){
            $meta[$attr] = null;
        };
}

$meta = json_encode($meta);
