<?php
$retrieveParams = [
    'test'=>$test
];

$requiredAuth = REQUIRED_AUTH_NONE;

//Set 'authAtMost' if not requested by the user
if($inputs['authAtMost'] !== null){
    if(!($inputs['authAtMost'] === 0 || filter_var($inputs['authAtMost'],FILTER_VALIDATE_INT))){
        if($test)
            echo 'authAtMost must be a valid integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    if($inputs['authAtMost'] === 1)
        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_RESTRICTED);
    elseif($inputs['authAtMost'] == 2)
        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_OWNER);
    elseif($inputs['authAtMost'] !== 0)
        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_ADMIN);
}
$retrieveParams['authAtMost'] = $requiredAuth;

//Handle id
if($inputs['id'] !== null){
    if(!filter_var($inputs['id'],FILTER_VALIDATE_INT)){
        if($test)
            echo 'id must be a valid integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else{
    if($inputs['articleAddress'] !== null){
        if(strlen($inputs['articleAddress']) > ADDRESS_MAX_LENGTH){
            if($test)
                echo 'Each articleAddress must be at most '.ADDRESS_MAX_LENGTH.' characters long!'.EOL;
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
        //One edge case - IF we are getting article by address and its auth is restricted, it may be ok depending on settings
        if($apiSettings->getSetting('restrictedArticleByAddress') && $requiredAuth <=REQUIRED_AUTH_RESTRICTED){
            $requiredAuth = REQUIRED_AUTH_NONE;
            $retrieveParams['authAtMost'] = REQUIRED_AUTH_RESTRICTED;
        }
    }
    else{
        if($test)
            echo 'id must be set!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//Set 'ignoreOrphan' if requested by the user
if($inputs['ignoreOrphan'] !== null){
    $requiredAuth = max($requiredAuth,REQUIRED_AUTH_OWNER);
    $inputs['ignoreOrphan'] = (bool)$inputs['ignoreOrphan'];
}
else
    $inputs['ignoreOrphan'] = true;

$inputs['preloadGalleries'] = $inputs['preloadGalleries'] !== null ? $inputs['preloadGalleries'] : true;


//Allows restriction of article types via settings
if(empty($apiSettings))
    $apiSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/apiSettings/',$defaultSettingsParams);
$relevantSetting = $apiSettings->getSetting('articles');
if(\IOFrame\Util\PureUtilFunctions::is_json($relevantSetting)){
    $relevantSetting = json_decode($relevantSetting,true);
    if(!empty($relevantSetting['articleContactTypes'])){
        $types = explode(',',$relevantSetting['articleContactTypes']);
        $retrieveParams['contactTypeIn'] = $types;
    }
}