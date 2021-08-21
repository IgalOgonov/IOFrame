<?php

if(!defined('RateLimitHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/RateLimitHandler.php';
if(!isset($RateLimitHandler))
    $RateLimitHandler = new IOFrame\Handlers\RateLimitHandler(
        $settings,
        $defaultSettingsParams
    );

//Limit check
if(isset($identifier)){
    $rate = USERS_API_LIMITS[$action]['rate'];
    $normalLimit = $RateLimitHandler->checkAction($rate['category'],$identifier,$rate['action'],$rate['limit'],['test'=>$test]);
    if(gettype($normalLimit) === 'integer')
        die(RATE_LIMIT_REACHED.'@'.max(round($normalLimit/1000),1));
    else{
        $existingLimit = 0;
        //Check user action
        if(isset($userId) && isset(USERS_API_LIMITS[$action]['userAction'])){
            $existingLimit = $RateLimitHandler->checkActionEventLimit(1,$userId,USERS_API_LIMITS[$action]['userAction'],['test'=>$test]);
        }
        //check IP action
        if ( ($existingLimit<=0) && isset(USERS_API_LIMITS[$action]['ipAction'])){
            if(!isset($ip)){
                if(!defined('IPHandler'))
                    require __DIR__ . '/../../../IOFrame/Handlers/IPHandler.php';
                if(!isset($IPHandler))
                    $IPHandler = new \IOFrame\Handlers\IPHandler(
                        $settings,
                        array_merge($defaultSettingsParams,['siteSettings'=>$siteSettings])
                    );
                $ip = $IPHandler->directIP;
            }
            $existingLimit = $RateLimitHandler->checkActionEventLimit(0,$ip,USERS_API_LIMITS[$action]['ipAction'],['test'=>$test]);
        }
        if($existingLimit > 0)
            die(RATE_LIMIT_REACHED.'@'.$existingLimit);
    }
}
