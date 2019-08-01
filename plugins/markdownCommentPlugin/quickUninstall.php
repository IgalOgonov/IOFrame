<?php

if(!defined('helperFunctions'))
    require $this->settings->getSetting('absPathToRoot').'IOFrame/Util/helperFunctions.php';

//Handle the files
$urlsToRemove = array();
$foldersToRemove = array();
array_push($urlsToRemove,$this->settings->getSetting('absPathToRoot').'api/commentAPI.php');
array_push($urlsToRemove,$this->settings->getSetting('absPathToRoot').'front/ioframe/pages/cp/comments.php');
array_push($urlsToRemove,$this->settings->getSetting('absPathToRoot').'front/ioframe/templates/modules/comments.php');
array_push($urlsToRemove,$this->settings->getSetting('absPathToRoot').'front/ioframe/js/modules/comments.js');
array_push($foldersToRemove,$this->settings->getSetting('absPathToRoot').'api/commentAPI_fragments');

foreach($urlsToRemove as $url){
    if(file_exists($url))
        if(!$test)
            unlink($url);
        else
            echo 'Deleting file '.$url.EOL;
}

foreach($foldersToRemove as $url){
    if(file_exists($url))
        if(!$test)
            IOFrame\Util\folder_delete($url);
        else
            echo 'Deleting folder '.$url.EOL;
}

//The following changes the system state, as such it must not be executed in cli mode (which is local changes only)
if(!$local){

    if(!isset($this->SQLHandler))
        $SQLHandler = new IOFrame\Handlers\SQLHandler($this->settings);
    else
        $SQLHandler = $this->SQLHandler;

    $prefix = $SQLHandler->getSQLPrefix();

    //Drop the additional Object table columns
    $tableName = $prefix.'OBJECT_CACHE';
    $query = 'ALTER TABLE '.$tableName.'
              DROP Trusted_Comment,
              DROP Date_Comment_Created,
              DROP Date_Comment_Updated;';
    if(!$test)
        $SQLHandler->exeQueryBindParam($query);
    else
        echo 'Query to send: '.$query.EOL;


    //Delete the auth action needed to make trusted comments
    $res = $SQLHandler->deleteFromTable($prefix.'ACTIONS_AUTH',['Auth_Action','MAKE_TRUSTED_COMMENTS','='],['test'=>$test]);
}

?>