<?php

require_once $settings->getSetting('absPathToRoot').'/_siteHandlers/userHandler.php';

if(!isset($userHandler))
    $userHandler = new IOFrame\userHandler(
        $settings,
        $defaultSettingsParams
    );

$result = $userHandler->banUser($_REQUEST['minutes'],$_REQUEST['id'],$test);







