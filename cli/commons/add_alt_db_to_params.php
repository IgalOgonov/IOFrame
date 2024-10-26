<?php

if(!empty($v['altDBSettings']) && isset($params) && is_array($params)){

    $defaultLocalParamsWithoutCache = array_merge($defaultSettingsParams,['opMode'=>\IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL,'useCache'=>false,'RedisManager'=>null]);

    $validSettingsDir = null;

    if(is_dir($rootFolder.'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$v['altDBSettings']))
        $validSettingsDir = $rootFolder.'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$v['altDBSettings'];
    elseif(is_dir($v['altDBSettings']))
        $validSettingsDir = $v['altDBSettings'];
    else{
        die(json_encode(['result'=>false,'error'=>'invalid-alt-db-settings-dir'],JSON_PRETTY_PRINT));
    }

    $altDbSettings = new \IOFrame\Handlers\SettingsHandler(
        $validSettingsDir,
        $defaultLocalParamsWithoutCache
    );

    $allAltDbSettings = $altDbSettings->getSettings();
    if(
        empty($allAltDbSettings['sql_server_addr']) || empty($allAltDbSettings['sql_server_port']) ||
        empty($allAltDbSettings['sql_username']) || empty($allAltDbSettings['sql_password']) ||
        empty($allAltDbSettings['sql_db_name'])
    ){
        die(json_encode(['result'=>false,'error'=>'missing-alt-db-settings'],JSON_PRETTY_PRINT));
    }

    $defaultLocalParamsWithoutCache['sqlSettings'] = $altDbSettings;

    try{
        $AltDBManager = new \IOFrame\Managers\SQLManager($settings,array_merge($defaultLocalParamsWithoutCache));
    }
    catch (\PDOException $e){
        die(json_encode(['result'=>false,'error'=>'alt-db-unreachable'],JSON_PRETTY_PRINT));
    }

    $params['altDbSettings'] = $altDbSettings;
    $params['AltDBManager'] = $AltDBManager;
}
