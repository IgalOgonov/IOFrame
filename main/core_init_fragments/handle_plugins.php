<?php

if(empty($defaultSettingsParams))
    require 'initiate_default_settings_params.php';
if(empty($settings))
    require 'initiate_local_settings.php';
if(empty($rootFolder))
    require 'initiate_path_to_root.php';

//Get the list of active plugins
$orderedPlugins = [];
$plugins = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/plugins/');
$PluginHandler = new \IOFrame\Handlers\PluginHandler($settings,$defaultSettingsParams);
$pluginList = $plugins->getSettings();
if(count($pluginList)<1)
    $pluginList =[];
//Get the order in which plugins should be included, if there is one. Get the local and global ones.
$localOrder = $PluginHandler->getOrder(['local'=>true]);
//var_dump($localOrder);
$order = $PluginHandler->getOrder(['local'=>false]);
//var_dump($order);
//If there is a mismatch, specify it so the front end knows!
$pluginMismatch = false;
//If there are no plugins, we got nothing to do
if($order != [] || $localOrder!=[]){
    if(implode(',',$order)!=implode(',',$localOrder)){
        //Since the mismatched plugins might just be in different order, but the same ones installed, check for it
        if(count(array_diff($localOrder,$order)) == 0){
            //If we are here, it means we may just use the correct order, and update the local one while we're at it
            $url = $rootFolder.'/localFiles/plugin_order/';
            $filename = 'order';
            try{
                \IOFrame\Util\FileSystemFunctions::writeFileWaitMutex($url, $filename, implode(',',$order), ['backUp' => true]);
            }
            catch (\Exception $e){
                //TODO Log
            }
        }
        //If the plugins are still mismatched, die or notify the front-end (according to settings)
        elseif($settings->getSetting('dieOnPluginMismatch')){

            \IOFrame\Util\DefaultErrorTemplatingFunctions::handleGenericHTTPError(
                $settings,
                [
                    'error'=>500,
                    'errorInMsg'=>false,
                    'errorHTTPMsg'=>'Server node plugin conflict',
                    'mainMsg'=>'Plugin Conflict',
                    'subMsg'=>'Local node plugins do not match system plugins',
                    'mainFilePath'=>$settings->getSetting('_templates_plugins_mismatch')
                ]
            );
        }
        else
            $pluginMismatch = true;
    }
    //First, require all includes that have an order
    if(is_array($order))
        foreach($order as $value){
            if(isset($pluginList[$value])){
                if(
                    ($PluginHandler->getInfo(['name'=>$value])[0]['status'] == 'active') &&
                    is_file($rootFolder.'plugins/'.$value.'/include.php')
                ){
                    require $rootFolder.'plugins/'.$value.'/include.php';
                }
                $orderedPlugins[] = $value;
                unset($pluginList[$value]);
            }
        }
    //Then, require those that are orderless
    if(is_array($pluginList))
        foreach($pluginList as $key => $value){
            if(
                $PluginHandler->getInfo(['name'=>$key])[0]['status'] == 'active'  &&
                is_file($rootFolder.'plugins/'.$key.'/include.php')
            ){
                require $rootFolder.'plugins/'.$key.'/include.php';
            }
            $orderedPlugins[] = $key;
        }
}