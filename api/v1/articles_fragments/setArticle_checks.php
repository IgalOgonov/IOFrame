<?php
if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';
require_once $settings->getSetting('absPathToRoot').'vendor/autoload.php';
$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.AllowedElements', []);
$purifier = new HTMLPurifier($config);

$requiredAuth = REQUIRED_AUTH_OWNER;

$cleanInputs = [];

$setParams['test'] = $test;

if(!$auth->isLoggedIn()){
    if($test)
        echo 'Must be logged in to set articles!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

$requiredParams = [];
$optionalParams = ['articleAuth','articleAddress','subtitle','caption','alt','name','thumbnailAddress','blockOrder','weight','language'];
$metaParams = ['subtitle','caption','alt','name'];
$canBeNullParams = array_merge($metaParams,['language']);

if($inputs['create']){
    $requiredAuth = REQUIRED_AUTH_ADMIN;
    $cleanInputs[$articleSetColumnMap['creatorId']] = $auth->getDetail('ID');
    $setParams['override'] = false;
    $setParams['update'] = false;
    array_push($requiredParams,'title');
}
else{
    $setParams['override'] = true;
    $setParams['update'] = true;
    array_push($requiredParams,'articleId');
    array_push($optionalParams,'title');
}

$purifyParams = ['title','subtitle','caption','name','alt'];
foreach($purifyParams as $param)
    if($inputs[$param] !== null)
        $inputs[$param] = $purifier->purify($inputs[$param]);


foreach($requiredParams as $param){
    if($inputs[$param] === null && !$inputs['create']){
        if($test)
            echo $param.' must be set!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    elseif($inputs[$param] !== null){
        switch($param){
            case 'title':
                if(!preg_match('/'.TITLE_REGEX.'/',$inputs[$param])){
                    if($test)
                        echo $param.' must match '.TITLE_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'articleId':
                if(!filter_var($inputs[$param],FILTER_VALIDATE_INT)){
                    if($test)
                        echo 'Each blockOrder id needs to be a valid int!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
        }
        $cleanInputs[$articleSetColumnMap[$param]] = $inputs[$param];
    }
}

foreach($optionalParams as $param){

    if($inputs[$param] !== null && !(in_array($param,$canBeNullParams) && ($inputs[$param] === '@')) ){
        switch($param){
            case 'articleAuth':
            case 'weight':
                if(!filter_var($inputs[$param],FILTER_VALIDATE_INT) && $inputs[$param] !== 0){
                    if($test)
                        echo $param.' needs to be a valid int!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                if( ($param === 'articleAuth' && $param > 2) || $param === 'weight'){
                    $requiredAuth = REQUIRED_AUTH_ADMIN;
                }
                break;
            case 'subtitle':
                if(!preg_match('/'.SUBTITLE_REGEX.'/',$inputs[$param])){
                    if($test)
                        echo $param.' must match '.SUBTITLE_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'caption':
                if(!preg_match('/'.CAPTION_REGEX.'/',$inputs[$param])){
                    if($test)
                        echo $param.' must match '.CAPTION_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'alt':
                if(!preg_match('/'.IMAGE_ALT_REGEX.'/',$inputs[$param])){
                    if($test)
                        echo $param.' must match '.IMAGE_ALT_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'name':
                if(!preg_match('/'.IMAGE_NAME_REGEX.'/',$inputs[$param])){
                    if($test)
                        echo $param.' must match '.IMAGE_NAME_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'articleAddress':
                if(strlen( $inputs['articleAddress']) > ADDRESS_MAX_LENGTH){
                    if($test)
                        echo 'Each value in articleAddress must be at most '.ADDRESS_MAX_LENGTH.' characters long!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                $address = explode('-', $inputs['articleAddress']);
                foreach( $address as $subValue)
                    if(!preg_match('/'.ADDRESS_SUB_VALUE_REGEX.'/',$subValue)){
                        if($test)
                            echo 'Each value in articleAddress must be a sequence of low-case characters
                                and numbers separated by "-", each sequence no longer than 24 characters long'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                break;
            case 'language':
                if(!preg_match('/'.LANGUAGE_REGEX.'/',$inputs['language'])){
                    if($test)
                        echo 'Language must match '.LANGUAGE_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'thumbnailAddress':
                $valid = \IOFrame\Util\validator::validateRelativeFilePath($inputs['thumbnailAddress']);
                $valid = $valid || filter_var($inputs['thumbnailAddress'],FILTER_VALIDATE_URL);

                if(!$valid){
                    if($test)
                        echo 'Invalid thumbnailAddress!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'blockOrder':
                $actualOrder = explode(',',$inputs['blockOrder']);
                foreach($actualOrder as $id){
                    if(!filter_var($id,FILTER_VALIDATE_INT)){
                        if($test)
                            echo 'Each blockOrder id needs to be a valid int!'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                }
                break;
        }

        if(!in_array($param,$metaParams))
            $cleanInputs[$articleSetColumnMap[$param]] = $inputs[$param];
        else{
            if(!isset($cleanInputs['Article_Text_Content']))
                $cleanInputs['Article_Text_Content'] = [];
            $cleanInputs['Article_Text_Content'][$param] = $inputs[$param];
        }
    }
    elseif($param === 'articleAddress' && $inputs['create']){
        $pattern = '/[\W]+/';
        $replacement = '-';
        $address = preg_replace($pattern, $replacement, $inputs['title']);
        $address = explode('-',$address);
        $temp = [];
        foreach($address as $index => $subAddr){
            if(preg_match('/^[a-zA-Z0-9\_]+$/',$address[$index]))
                array_push($temp,substr($subAddr,0,24));
        }
        $address = $temp;
        $time = date('d-m-Y');
        $address = strtolower(substr(implode('-',$address),0,ADDRESS_MAX_LENGTH-strlen($time)-1));
        $address .= '-'.$time;
        $cleanInputs[$articleSetColumnMap['articleAddress']] = $address;
    }
    elseif(in_array($param,$canBeNullParams) && ($inputs[$param] === '@')){
        if(!in_array($param,$metaParams))
            $cleanInputs[$articleSetColumnMap[$param]] = '';
        else{
            if(!isset($cleanInputs['Article_Text_Content']))
                $cleanInputs['Article_Text_Content'] = [];
            $cleanInputs['Article_Text_Content'][$param] = null;
        }
    }

}

if(isset($cleanInputs['Article_Text_Content']))
    $cleanInputs['Article_Text_Content'] = json_encode($cleanInputs['Article_Text_Content']);