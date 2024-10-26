<?php
if(!isset($settings))
    require_once 'initiate_local_settings.php';

$rootFolder = $settings->getSetting('absPathToRoot');
$defaultSettingsParams['absPathToRoot'] = $rootFolder;