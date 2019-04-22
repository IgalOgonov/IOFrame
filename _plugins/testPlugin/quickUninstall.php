<?php
if(!$test){
    unset($_SESSION['testRandomNumber']);
    unset($_SESSION['testSetting']);


    //Create a PDO connection
    $sqlSettings = new IOFrame\settingsHandler($this->settings->getSetting('absPathToRoot').SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');
    $conn = IOFrame\prepareCon($sqlSettings);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // INITIALIZE CORE VALUES TABLE
    /* Literally just the pivot time for now. */
    $makeTB = $conn->prepare("DROP TABLE IF EXISTS ".$this->sqlHandler->getSQLPrefix()."TEST_TABLE CASCADE;");
    $makeTB->execute();

}
else{
    echo 'quickUninstall activates here!'.EOL;
}
?>