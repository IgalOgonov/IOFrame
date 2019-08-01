<?php

if(!defined('helperFunctions'))
    require $this->settings->getSetting('absPathToRoot').'IOFrame/Util/helperFunctions.php';

//Handle the files
$filesToCopy = array();
array_push(
    $filesToCopy,
    [
        $this->settings->getSetting('absPathToRoot').'plugins/markdownCommentPlugin/files/api/commentAPI.php',
        $this->settings->getSetting('absPathToRoot').'api/commentAPI.php'
    ]
);
array_push(
    $filesToCopy,
    [
        $this->settings->getSetting('absPathToRoot').'plugins/markdownCommentPlugin/files/pages/comments.php',
        $this->settings->getSetting('absPathToRoot').'front/ioframe/pages/cp/comments.php'
    ]
);
array_push(
    $filesToCopy,
    [
        $this->settings->getSetting('absPathToRoot').'plugins/markdownCommentPlugin/files/templates/comments.php',
        $this->settings->getSetting('absPathToRoot').'front/ioframe/templates/modules/comments.php'
    ]
);
array_push(
    $filesToCopy,
    [
        $this->settings->getSetting('absPathToRoot').'plugins/markdownCommentPlugin/files/js/comments.js',
        $this->settings->getSetting('absPathToRoot').'front/ioframe/js/modules/comments.js'
    ]
);
foreach($filesToCopy as $file) {
    if (file_exists($file[0]))
        if (!$test)
            copy($file[0], $file[1]);
        else
            echo 'Copying ' . $file[0] . ' to ' . $file[1] . EOL;
}
//Handle the folders
$foldersToCopy = array();
array_push(
    $foldersToCopy,
    [
        $this->settings->getSetting('absPathToRoot').'plugins/markdownCommentPlugin/files/api/commentAPI_fragments',
        $this->settings->getSetting('absPathToRoot').'api/commentAPI_fragments'
    ]

);
foreach($foldersToCopy as $folder) {
    if (file_exists($folder[0]))
        if (!$test)
            IOFrame\Util\folder_copy($folder[0], $folder[1]);
        else
            echo 'Copying folder ' . $folder[0] . ' to ' . $folder[1] . EOL;
}



//The following changes the system state, as such it must not be executed in cli mode (which is local changes only)
if(!$local){
    if(!isset($this->SQLHandler))
        $SQLHandler = new IOFrame\Handlers\SQLHandler($this->settings);
    else
        $SQLHandler = $this->SQLHandler;

    $prefix = $SQLHandler->getSQLPrefix();

    //Create the additional Object table columns
    $tableName = $prefix.'OBJECT_CACHE';
    $query = 'ALTER TABLE '.$tableName.'
                                     ADD Trusted_Comment TINYINT(1) NOT NULL DEFAULT 0 AFTER Meta,
                                     ADD Date_Comment_Created VARCHAR(14) AFTER Trusted_Comment,
                                     ADD Date_Comment_Updated VARCHAR(14) AFTER Date_Comment_Created;';
    if(!$test)
        $SQLHandler->exeQueryBindParam($query);
    else
        echo 'Query to send: '.$query.EOL;

    //Create the auth action needed to make trusted comments
    if(!defined('safeSTR'))
        require $this->settings->getSetting('absPathToRoot').'IOFrame/Util/safeSTR.php';

    $columns = ['Auth_Action','Description'];

    $assignments = [
        ['MAKE_TRUSTED_COMMENTS',\IOFrame\Util\str2SafeStr('Action required to make trusted comments')]
    ];

    foreach($assignments as $k=>$v){
        $assignments[$k][0] = [$v[0],'STRING'];
        $assignments[$k][1] = [$v[1],'STRING'];
    }

    $res = $SQLHandler->insertIntoTable($prefix.'ACTIONS_AUTH',$columns,$assignments,['onDuplicateKey'=>true,'test'=>$test]);


}









?>