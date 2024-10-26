<?php
$retrieveParams = [
    'test'=>$test
];

$requiredAuth = REQUIRED_AUTH_NONE;

//Set 'authAtMost' if not requested by the user
if(isset($inputs['authAtMost'])){
    if($inputs['authAtMost'] === 1)
        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_RESTRICTED);
    elseif($inputs['authAtMost'] == 2)
        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_OWNER);
    elseif($inputs['authAtMost'] !== 0)
        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_ADMIN);
}
$retrieveParams['authAtMost'] = $requiredAuth;

//Handle the case where we're getting specific article by address
if(!empty($inputs['id']) && $apiSettings->getSetting('restrictedArticleByAddress') && ($requiredAuth <=REQUIRED_AUTH_RESTRICTED)){
    $requiredAuth = REQUIRED_AUTH_NONE;
    $retrieveParams['authAtMost'] = REQUIRED_AUTH_RESTRICTED;
}

//Set 'ignoreOrphan' if requested by the user
if(isset($inputs['ignoreOrphan']) && !$inputs['ignoreOrphan']){
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