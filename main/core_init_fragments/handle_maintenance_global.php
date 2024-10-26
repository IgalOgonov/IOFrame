<?php

if(!isset($settings))
    require_once 'initiate_local_settings.php';
if(!isset($siteSettings))
    require_once 'initiate_combined_settings.php';

if($siteSettings->getSetting('_maintenance')) {

    \IOFrame\Util\DefaultErrorTemplatingFunctions::handleGenericHTTPError(
        $settings,
        [
            'error'=>500,
            'errorInMsg'=>false,
            'errorHTTPMsg'=>'System under maintenance',
            'mainMsg'=>'System Maintenance',
            'subMsg'=>'System is currently undergoing maintenance',
            'startTime'=>$siteSettings->getSetting('_maintenance_start'),
            'eta'=>$siteSettings->getSetting('_maintenance_eta'),
            'cssColor'=>'180,120,20',
            'mainFilePath'=>$settings->getSetting('_templates_maintenance_global')
        ]
    );
}