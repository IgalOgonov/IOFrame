<?php

/* You'll notice a lot of this is copy-pasted from uninstall in PluginHandler.
 * It is because quickUninstall already handles a lot of the work for you, while if you are using
 * fullUninstall, you need to do ALL of the work yourself. It is very easy to forget something, miss something,
 * create new attack vectors/vulnerabilities, and more.
 * Please, DO NOT write a fullUninstall unless you are experienced, and understand PluginHandler->uninstall() fully.
 * */


if(!$test){

    $url = $settings->getSetting('absPathToRoot').'plugins/'.$name.'/';   //Plugin folder
    $depUrl = $settings->getSetting('absPathToRoot').'localFiles/pluginDependencyMap/';
    $LockManager = new \IOFrame\Managers\LockManager($url);
    $SQLManager = new \IOFrame\Managers\SQLManager($settings);
    $plugList = new \IOFrame\Handlers\SettingsHandler($settings->getSetting('absPathToRoot').'localFiles/plugins/');
    $plugName = $PluginHandler->getInfo(['name'=>$name])[0];
    //-------Check if the plugin is absent - or if override is false while the plugin isn't listed installed.
    $goOn = ($plugName['status'] != 'absent');
    if(!$goOn){
        echo 'Plugin '.$name.' absent, can not uninstall!'.EOL;
        if($plugName['status'] == 'absent') //Only remove the plugin from the list if its actually absent
            $plugList->setSetting($name,null);
        return 1;
    }
    //-------Make sure quickUninstall exists
    if(!file_exists($url.'quickUninstall.php')){
        echo 'Plugin '.$name.' quickUninstall absent, can not uninstall!'.EOL;
        return 2;
    }
    //-------Check for dependencies
    $dep = $PluginHandler->checkDependencies($name,['validate'=>true,'test'=>$test]);
    if(!($dep === 0)){
        echo 'Plugin '.$name.' dependencies are '.$dep.', can not uninstall!'.EOL;
        return 3;
    }
    //-------Change plugin to "zombie"
    if(!$test)
        $plugList->setSetting($name,'zombie');
    //-------Call quickUninstall.php - REMEMBER - OPTIONS ARRAY MUST BE FILTERED
    try{
        unset($_SESSION['testRandomNumber']);
        unset($_SESSION['testSetting']);

        //Create a PDO connection
        $sqlSettings = new \IOFrame\Handlers\SettingsHandler($settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');
        $conn = \IOFrame\Util\FrameworkUtilFunctions::prepareCon($sqlSettings);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // INITIALIZE CORE VALUES TABLE
        /* Literally just the pivot time for now. */
        $makeTB = $conn->prepare("DROP TABLE ".$SQLManager->getSQLPrefix()."TEST_TABLE CASCADE;");
        $makeTB->execute();
    }
    catch(Exception $e){
        if($test)
            echo 'Exception during uninstall: '.$e.EOL;
        return 6;
    }
    //-------Remove plugin from order list
    $PluginHandler->removeFromOrder(
        $name,
        'name',
        ['verify'=>false,'backUp'=>true,'local'=>false,'test'=>$test]
    );
    //-------Remove dependencies
    $dep = json_decode(\IOFrame\Util\FileSystemFunctions::readFileWaitMutex($url,'dependencies.json'),true);
    if($dep != '')
        foreach($dep as $pName=>$ver){
            if(file_exists($depUrl.$pName.'/settings')){
                $depHandler = new \IOFrame\Handlers\SettingsHandler($depUrl.$pName.'/');
                $depHandler->setSetting($name,null);
                echo 'Removing '.$name.' from dependency tree of '.$pName.EOL;
            }
        }
    //-------If the definitions file exists, remove them
    if(file_exists($url.'definitions.json')){
        if(!$PluginHandler->validatePluginFile($url,'definitions',['isFile'=>false,'test'=>$test])){
            echo 'Definitions for '.$name.' are not valid!'.EOL;
            return 5;
        }
        //Now remove the definitions from the system definition file
        try{
            $gDefUrl = $settings->getSetting('absPathToRoot').'localFiles/definitions/';
            //Read definition files - and remove the matching ones
            $defFile = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($url,'definitions.json',['LockManager' => $LockManager]);
            if($defFile != null){       //If the file is empty, don't bother doing work
                $defArr = json_decode($defFile,true);
                $gDefFile = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($gDefUrl,'definitions.json',['LockManager' => $LockManager]);
                $gDefArr = json_decode($gDefFile,true);
                foreach($defArr as $def=>$val){
                    if(isset($gDefArr[$def]))
                        if($gDefArr[$def] == $val){
                            unset($gDefArr[$def]);
                        }
                }
                //Write to global definition file after backing it up
                $defLock = new \IOFrame\Managers\LockManager($gDefUrl);
                $defLock->makeMutex();
                \IOFrame\Util\FileSystemFunctions::backupFile($gDefUrl,'definitions.json');
                $gDefFile = fopen($gDefUrl.'definitions.json', "w+") or die("Unable to open definitions file!");
                fwrite($gDefFile,json_encode($gDefArr));
                fclose($gDefFile);
                $defLock->deleteMutex();
                echo 'Definitions removed from system definitions: '.$defFile.EOL;
            }
        }
        catch (\Exception $e){
            echo 'Exception during definition removal plugin '.$name.': '.$e.EOL;
            return 5;
        }
    }
    //-------Remove plugin from list
    $plugList->setSetting($name,null);

    echo '<span>Uninstall ended!</span>'.EOL.
        '<span><a href="../cp/plugins">back to plugins</a></span>';
}
else{
    echo '<span>fullUninstall activates here!!</span>'.EOL.
        '<span><a href="../cp/plugins">back to plugins</a></span>';
}















