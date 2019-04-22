<?php

$sqlHandler = new IOFrame\sqlHandler($this->settings);
$sqlSettings = new IOFrame\settingsHandler($this->settings->getSetting('absPathToRoot').SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');
$conn = IOFrame\prepareCon($sqlSettings);

if($test){
    echo 'quickUninstall of objects activates here! Options are '.json_encode($options).EOL;
}
else{
//Handle the files
    $urlsToRemove = array();
    array_push($urlsToRemove,$this->settings->getSetting('absPathToRoot').'js/plugins/objects.js');
    foreach($urlsToRemove as $url){
        if(file_exists($url))
            if(!$test)
                unlink($url);
            else
                echo 'Unlinking '.$url.EOL;
    }
}

?>