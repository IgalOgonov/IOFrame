<?php
$settings = new \IOFrame\Handlers\SettingsHandler(
    IOFrame\Util\FrameworkUtilFunctions::getBaseUrl().'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/localSettings/'
);
$defaultSettingsParams['localSettings'] = $settings;