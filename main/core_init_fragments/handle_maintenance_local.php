<?php

if(!isset($settings))
    require_once 'initiate_local_settings.php';

if($settings->getSetting('_maintenance')) {

    \IOFrame\Util\DefaultErrorTemplatingFunctions::handleGenericHTTPError(
        $settings,
        [
            'error'=>500,
            'errorInMsg'=>false,
            'errorHTTPMsg'=>'Local node maintenance',
            'mainMsg'=>'Local Node Maintenance',
            'subMsg'=>'Local server node is currently undergoing maintenance',
            'startTime'=>$settings->getSetting('_maintenance_start'),
            'eta'=>$settings->getSetting('_maintenance_eta'),
            'cssColor'=>'180,120,20',
            'mainFilePath'=>$settings->getSetting('_templates_maintenance_local')
        ]
    );
}