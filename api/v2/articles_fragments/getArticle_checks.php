<?php
$retrieveParams = [
    'test'=>$test
];

$requiredAuth = REQUIRED_AUTH_NONE;

//Set 'authAtMost' if not requested by the user
if(isset($inputs['authAtMost'])){
    if($inputs['authAtMost'] === 0)
        $requiredAuth = REQUIRED_AUTH_NONE;
    elseif($inputs['authAtMost'] === 1)
        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_RESTRICTED);
    elseif($inputs['authAtMost'] == 2)
        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_OWNER);
    else
        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_ADMIN);
}
$retrieveParams['authAtMost'] = $requiredAuth;

//Handle the case where we're getting specific article by address
if(!empty($inputs['id']) && $apiSettings->getSetting('restrictedArticleByAddress') && $requiredAuth <=REQUIRED_AUTH_RESTRICTED){
    $requiredAuth = REQUIRED_AUTH_NONE;
    $retrieveParams['authAtMost'] = REQUIRED_AUTH_RESTRICTED;
}

//Set 'ignoreOrphan' if requested by the user
if(isset($inputs['ignoreOrphan'])){
    $requiredAuth = max($requiredAuth,REQUIRED_AUTH_OWNER);
    $inputs['ignoreOrphan'] = (bool)$inputs['ignoreOrphan'];
}
else
    $inputs['ignoreOrphan'] = true;

$inputs['preloadGalleries'] = $inputs['preloadGalleries'] !== null ? $inputs['preloadGalleries'] : true;