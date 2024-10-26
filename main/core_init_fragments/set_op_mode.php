<?php

if(!isset($settings))
    require_once 'initiate_local_settings.php';

if($settings->getSetting('opMode')!=null)
    $defaultSettingsParams['opMode'] = $settings->getSetting('opMode');
else
    $defaultSettingsParams['opMode'] = \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_MIXED;