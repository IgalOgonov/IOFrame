<?php
if(!$test){
    unset($_SESSION['testRandomNumber']);
    unset($_SESSION['testSetting']);

    if(!$local){
        //Create a PDO connection
        $sqlSettings = new \IOFrame\Handlers\SettingsHandler($this->settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');
        $conn = \IOFrame\Util\FrameworkUtilFunctions::prepareCon($sqlSettings);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // INITIALIZE CORE VALUES TABLE
        /* Literally just the pivot time for now. */
        $makeTB = $conn->prepare("DROP TABLE IF EXISTS ".$this->SQLManager->getSQLPrefix()."TEST_TABLE CASCADE;");
        $makeTB->execute();
    }
}
else{
    echo 'quickUninstall activates here!'.EOL;
}