<?php
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
    die(json_encode([
        'error'=>'AuthenticationLoggedIn',
        'message'=>'Not logged in!'
    ]));
}

$inputs['type'] = $inputs['create']? substr($APIAction,strlen('post/articles/{id}/block/')) : substr($APIAction,strlen('put/articles/{id}/block/{blockId}/'));

$params = ['id','orderIndex'];

switch($inputs['type']){
    case 'markdown':
        array_push($params,'text');
        break;
    case 'image':
    case 'cover':
        array_push($params,'alt','caption','name','resourceAddressLocal','resourceAddressDB','resourceAddressURI');
        break;
    case 'gallery':
        array_push($params,'caption','name','autoplay','loop','center','preview','fullScreenOnClick','slider','blockCollectionName');
        break;
    case 'youtube':
        array_push($params,'caption','name','height','width','autoplay','mute','loop','embed','controls','text');
        break;
    case 'video':
        array_push($params,'caption','name','height','width','autoplay','mute','loop','controls','resourceAddressLocal','resourceAddressDB','resourceAddressURI');
        break;
    case 'article':
        array_push($params,'caption','otherArticleId');
        break;
}

$cleanInputs[$blocksSetColumnMap['type']] = $inputs['type'];

if($inputs['create']){
    $setParams['override'] = false;
    $setParams['update'] = false;
}
else{
    $setParams['override'] = true;
    $setParams['update'] = true;
    array_push($params,'blockId');
}

$purifyParams = ['caption','name','alt','text'];
$metaParams = $metaMap['blockMeta'];
foreach($purifyParams as $param)
    if(isset($inputs[$param]))
        $inputs[$param] = $purifier->purify($inputs[$param]);

foreach($params as $param){
    if(isset($inputs[$param])){
        switch($param){
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
            case 'resourceAddressLocal':
            case 'resourceAddressDB':
            case 'resourceAddressURI':
                $inputs['blockResourceAddress'] = $inputs[$param];
                $param = 'blockResourceAddress';
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