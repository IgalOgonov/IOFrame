<?php

if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';
require_once $settings->getSetting('absPathToRoot').'vendor/autoload.php';
$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.AllowedElements', []);
$purifier = new HTMLPurifier($config);

$requiredAuth = REQUIRED_AUTH_OWNER;

if($inputs['safe']){
    $requiredAuth = REQUIRED_AUTH_ADMIN;
    $config->set('HTML.AllowedElements', null);
}

$cleanInputs = [];
$setParams = [];

$setParams['test'] = $test;

if(!$auth->isLoggedIn()){
    if($test)
        echo 'Must be logged in to set article blocks!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

if(!isset($inputs['type'])){
    if($test)
        echo 'type must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}


$requiredParams = ['articleId'];
$optionalParams = ['orderIndex'];

switch($inputs['type']){
    case 'markdown':
        if($inputs['create'])
            array_push($requiredParams,'text');
        else
            array_push($optionalParams,'text');
        break;
    case 'image':
    case 'cover':
        if($inputs['create'])
            array_push($requiredParams,'blockResourceAddress');
        else
            array_push($optionalParams,'blockResourceAddress');
        array_push($optionalParams,'alt','caption','name');
        break;
    case 'gallery':
        if($inputs['create'])
            array_push($requiredParams,'blockCollectionName');
        else
            array_push($optionalParams,'blockCollectionName');
        array_push($optionalParams,'caption','name','autoplay','loop','center','preview','fullScreenOnClick','slider');
        break;
    case 'youtube':
        if($inputs['create'])
            array_push($requiredParams,'text');
        else
            array_push($optionalParams,'text');
        array_push($optionalParams,'caption','name','height','width','autoplay','mute','loop','embed','controls');
        break;
    case 'video':
        if($inputs['create'])
            array_push($requiredParams,'blockResourceAddress');
        else
            array_push($optionalParams,'blockResourceAddress');
        array_push($optionalParams,'caption','name','height','width','autoplay','mute','loop','controls');
        break;
    case 'article':
        if($inputs['create'])
            array_push($requiredParams,'otherArticleId');
        else
            array_push($optionalParams,'otherArticleId');
        array_push($optionalParams,'caption');
        break;
    default:
        if($test)
            echo 'Invalid block type!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
}

$cleanInputs[$blocksSetColumnMap['type']] = $inputs['type'];

if($inputs['create']){
    $setParams['override'] = false;
    $setParams['update'] = false;
}
else{
    $setParams['override'] = true;
    $setParams['update'] = true;
    array_push($requiredParams,'blockId');
}

$purifyParams = ['caption','name','alt','text'];
$metaParams = $metaMap['blockMeta'];
foreach($purifyParams as $param)
    if($inputs[$param] !== null)
        $inputs[$param] = $purifier->purify($inputs[$param]);


foreach($requiredParams as $param){
    if($inputs[$param] === null){
        if($test)
            echo $param.' must be set!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

foreach(array_merge($optionalParams,$requiredParams) as $param){
    if($inputs[$param] !== null){
        switch($param){
            case 'orderIndex':
                if(!( in_array($inputs[$param],[-1,0]) ||  filter_var($inputs[$param],FILTER_VALIDATE_INT))){
                    if($test)
                        echo $param.' needs to be a valid int!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'height':
            case 'width':
            case 'otherArticleId':
            case 'blockId':
            case 'articleId':
                if(!filter_var($inputs[$param],FILTER_VALIDATE_INT)){
                    if($test)
                        echo $param.' needs to be a valid int!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            //No breaks!
            case 'subtitle':
            case 'caption':
            case 'alt':
            case 'name':
                if($param === 'subtitle')
                    $regex = SUBTITLE_REGEX;
                elseif($param === 'caption')
                    $regex = CAPTION_REGEX;
                elseif($param === 'alt')
                    $regex = IMAGE_ALT_REGEX;
                elseif($param === 'name')
                    $regex = IMAGE_NAME_REGEX;

                if(!preg_match('/'.$regex.'/',$inputs[$param])){
                    if($test)
                        echo $param.' must match '.$regex.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'blockResourceAddress':
                $valid = \IOFrame\Util\validator::validateRelativeFilePath($inputs[$param]);
                $valid = $valid || filter_var($inputs[$param],FILTER_VALIDATE_URL);
                if(!$valid){
                    if($test)
                        echo 'Invalid address!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'blockCollectionName':
                if(!preg_match('/'.GALLERY_REGEX.'/',$inputs[$param])){
                    if($test)
                        echo 'Gallery name invalid!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'autoplay':
            case 'controls':
            case 'mute':
            case 'loop':
            case 'embed':
            case 'center':
            case 'preview':
            case 'fullScreenOnClick':
            case 'slider':
                $inputs[$param] = (bool)$inputs[$param];
                break;
            case 'text':
                if($inputs['type'] === 'markdown'){
                    $inputs[$param] = urldecode($inputs[$param]);
                    if(strlen($inputs[$param])>=MARKDOWN_TEXT_MAX_LENGTH){
                        if($test)
                            echo 'markdown text too long!'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                }
                elseif($inputs['type'] === 'youtube'){
                    if(!preg_match('/'.YOUTUBE_IDENTIFIER_REGEX.'/',$inputs[$param])){
                        if($test)
                            echo 'Youtube video identifier invalid!'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                }
                break;
        }
        if(!empty($blocksSetColumnMap[$param]) || in_array($param,$metaParams)){
            if(!in_array($param,$metaParams))
                $cleanInputs[$blocksSetColumnMap[$param]] = $inputs[$param];
            else{
                if(!isset($cleanInputs['Meta']))
                    $cleanInputs['Meta'] = [];
                $cleanInputs['Meta'][$param] = $inputs[$param];
            }
        }
    }
    elseif($param === 'orderIndex'){
        $inputs[$param] = 10000;
    }

}

if(isset($cleanInputs['Meta']))
    $cleanInputs['Meta'] = json_encode($cleanInputs['Meta']);