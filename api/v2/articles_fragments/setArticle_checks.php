<?php
$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.AllowedElements', []);
$purifier = new HTMLPurifier($config);

$requiredAuth = REQUIRED_AUTH_OWNER;

if($inputs['safe']??false){
    $requiredAuth = REQUIRED_AUTH_ADMIN;
    $config->set('HTML.AllowedElements', null);
}

$cleanInputs = [];

$setParams['test'] = $test;

if(!$auth->isLoggedIn()){
    \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE,'message'=>'Must be logged in to set articles!'],!$test);
}

$requiredParams = [];
$optionalParams = ['articleAuth','address','subtitle','caption','alt','name','resourceAddressLocal','resourceAddressDB','resourceAddressURI','blockOrder','weight','language','title'];
$metaParams = ['subtitle','caption','alt','name'];
$canBeNullParams = array_merge($metaParams,['language']);

if($inputs['create']){
    $requiredAuth = REQUIRED_AUTH_ADMIN;
    $cleanInputs[$articleSetColumnMap['creatorId']] = $auth->getDetail('ID');
    $setParams['override'] = false;
    $setParams['update'] = false;
}
else{
    $setParams['override'] = true;
    $setParams['update'] = true;
    $cleanInputs[$articleSetColumnMap['id']] = $inputs['id'];
}

$purifyParams = ['title','subtitle','caption','name','alt'];
foreach($purifyParams as $param)
    if(isset($inputs[$param])){
        $inputs[$param] = $purifier->purify($inputs[$param]);
        //Even if the HTML Characters are safe, single quotes must always be encoded, as they break the query
        $inputs[$param] = str_replace("'","&#039;",$inputs[$param]);
    }


foreach($optionalParams as $param){

    if(isset($inputs[$param]) && !(in_array($param,$canBeNullParams) && ($inputs[$param] === '@')) ){
        switch($param){
            case 'articleAuth':
            case 'weight':
                if( ($param === 'articleAuth' && $param > 2) || ($param === 'weight' && $param > 0)){
                    $requiredAuth = REQUIRED_AUTH_ADMIN;
                }
                break;
            case 'blockOrder':
                $inputs[$param] = implode(',',$inputs[$param]);
        }
        if(!in_array($param,$metaParams)){
            $targetParam = in_array($param,['resourceAddressLocal','resourceAddressDB','resourceAddressURI'])? 'Thumbnail_Address' : $articleSetColumnMap[$param] ;
            $cleanInputs[$targetParam] = $inputs[$param];
        }
        else{
            if(!isset($cleanInputs['Article_Text_Content']))
                $cleanInputs['Article_Text_Content'] = [];
            $cleanInputs['Article_Text_Content'][$param] = $inputs[$param];
        }
    }
    elseif($param === 'address' && $inputs['create'] && empty($inputs['address'])){
        $pattern = '/[\W]+/';
        $replacement = '-';
        $address = preg_replace($pattern, $replacement, $inputs['title']);
        $address = explode('-',$address);
        $temp = [];
        foreach($address as $index => $subAddr){
            if(preg_match('/^[a-zA-Z0-9\_]+$/', $subAddr))
                $temp[] = substr($subAddr, 0, 24);
        }
        $address = $temp;
        $time = date('d-m-Y');
        $address = strtolower(substr(implode('-',$address),0,ADDRESS_MAX_LENGTH-strlen($time)-1));
        $address .= '-'.$time;
        $cleanInputs[$articleSetColumnMap['address']] = $address;
    }
    elseif(in_array($param,$canBeNullParams) && isset($inputs[$param]) && ($inputs[$param] === '@')){
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
