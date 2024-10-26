<?php

if(!isset($SecurityHandler))
    $SecurityHandler = new \IOFrame\Handlers\SecurityHandler(
        $settings,
        $defaultSettingsParams
    );

$apiSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/apiSettings/',$defaultSettingsParams);

function checkApiEnabled($name,$apiSettings,$SecurityHandler,$specificAction = null,$params = []): bool {
    $checkAction = (!isset($params['checkAction']) || $params['checkAction']) && $specificAction;

    $relevantSetting = $apiSettings->getSetting($name);

    if(!\IOFrame\Util\PureUtilFunctions::is_json($relevantSetting))
        return false;
    else
        $relevantSetting = json_decode($relevantSetting,true);

    //Check that the API is active, and (if we're checking provided a specific action!) the action is allowed
    if(
        !(
            (!isset($relevantSetting['active']) || $relevantSetting['active']) &&
            (!$checkAction || !isset($relevantSetting[$specificAction]) || $relevantSetting[$specificAction] )
        )
    )
        return false;

    //If the user is banned, check that the specific action is allowed
    if(
        $checkAction && $SecurityHandler->checkBanned() &&
        isset($relevantSetting['allowUserBannedActions']) && is_array($relevantSetting['allowUserBannedActions'])
    ){
        if(!in_array($specificAction,$relevantSetting['allowUserBannedActions']))
            return false;
    }

    return true;
}