<?php

/* You'll notice a lot of this is copy-pasted from install in PluginHandler.
 * It is because quickInstall already handles a lot of the work for you, while if you are using
 * fullInstall, you need to do ALL of the work yourself. It is very easy to forget something, miss something,
 * create new attack vectors/vulnerabilities, and more.
 * Please, DO NOT write a fullInstall unless you are experienced, and understand PluginHandler->install() fully.
 * */

if(!$test){
    if(isset($_REQUEST['stage']) && isset($_REQUEST['testOption']))
        $stage = $_REQUEST['stage'];
    else
        $stage = 0;
    $SQLManager = new \IOFrame\Managers\SQLManager($settings);
    switch($stage){
        case 0:
            echo '<form method="post" action="">
                    <input type="text" name="name" value="'.$name;
            echo '" hidden="" required>
                    <input type="text" name="action" value="fullInstall" hidden="" required>
                    <input type="text" name="stage" value="1" hidden="" required>
                    <input type="text" name="testOption" placeholder="test option" required>
                    <input type="submit">
                    </form>';
            break;
        case 1:
            $url = $settings->getSetting('absPathToRoot').'plugins/'.$name.'/';   //Plugin folder
            $LockManager = new \IOFrame\Managers\LockManager($url);
            $plugList = new \IOFrame\Handlers\SettingsHandler($SQLManager->getSQLPrefix().'localFiles/plugins/');
            $plugName = $PluginHandler->getInfo(['name'=>$name])[0];
            //-------Check if the plugin is installed
            if($plugList->getSetting($name) == 'installed' || $plugList->getSetting($name) == 'zombie' || $plugList->getSetting($name) == 'installing'){
                echo 'Plugin '.$name.' is either installed, installing or zombie!'.EOL;
                return 1;
            }
            //-------Check if the plugin is illegal with override off, or the install is missing
            if($plugName['status'] != 'legal'){
                if($override && $plugName['status'] == 'illegal' &&
                    file_exists($url.'fullInstall.php'))
                    $goOn = true;
                else
                    $goOn = false;
            }
            else
                $goOn = true;
            if(!$goOn){
                echo 'fullInstall for '.$name.' is either missing, or plugin illegal!'.EOL;
                return 2;
            }

            //-------Validate dependencies
            isset($plugName['dependencies'])?
                $dependencies = $plugName['dependencies']:$dependencies = [];

            foreach($dependencies as $dep => $versions){
                //Remember - $versions[0] is min version, [1] max version.
                $dep = $PluginHandler->getInfo(['name'=>$dep])[0];
                ( $dep['status']=='active' && isset($dep['version']) && filter_var((int)$dep['version'],FILTER_VALIDATE_INT) )?
                    $ver = (int)$dep['version']:
                    $ver = -1;
                if( $ver < $versions['minVersion'] ||  (isset($versions['maxVersion']) && $ver > $versions['maxVersion'])){
                    echo 'Plugin '.$name.' is missing a correct version of the dependency '.$dep['name'].'!'.EOL;
                    return 3;
                }
            }
            //-------Change plugin to "installing"
            if(!$test)
                $plugList->setSetting($name,'installing',['createNew'=>true]);

            //-------Time to validate (then update) definitions if the exist
            if(file_exists($url.'definitions.json')){
                if(!$PluginHandler->validatePluginFile($url,'definitions',['isFile'=>false,'test'=>$test])){
                    echo 'Definitions for '.$name.' are not valid!'.EOL;
                    return 5;
                }
                //Now add the definitions to the system definition file
                try{
                    $gDefUrl = $settings->getSetting('absPathToRoot').'localFiles/definitions/';
                    //Read definition files - and merge them
                    $defFile = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($url,'definitions.json',['LockManager' => $LockManager]);
                    if($defFile != null){       //If the file is empty, don't bother doing work
                        $defArr = json_decode($defFile,true);
                        $gDefFile = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($gDefUrl,'definitions.json',['LockManager' => $LockManager]);
                        $gDefArr = json_decode($gDefFile,true);
                        $newDef = array_merge($defArr,$gDefArr);
                        //Write to global definition file after backing it up
                        $defLock = new \IOFrame\Managers\LockManager($gDefUrl);
                        $defLock->makeMutex();
                        \IOFrame\Util\FileSystemFunctions::backupFile($gDefUrl,'definitions.json');
                        $gDefFile = fopen($gDefUrl.'definitions.json', "w+") or die("Unable to open definitions file!");
                        fwrite($gDefFile,json_encode($newDef));
                        fclose($gDefFile);
                        $defLock->deleteMutex();
                        echo 'New definitions added to system definitions: '.$defFile.EOL;

                    }
                }
                catch (\Exception $e){
                    echo 'Exception :'.$e.EOL;
                    try{
                        $options = [];
                        require $url.'quickUninstall.php';
                        $plugList->setSetting($name,null,['createNew'=>true]);
                    }
                    catch (\Exception $e){
                        echo 'Exception during definition inclusion of plugin '.$name.': '.$e.EOL;
                    }
                    return 5;
                }
            }
            //-------Finally, install
            $_SESSION['testRandomNumber'] = 0;
            if(preg_match('/\W| /',$_REQUEST['testOption'])==0)
                $_SESSION['testSetting'] = $_REQUEST['testOption'];
            else
                echo $_REQUEST['testOption'].' is illegal!'.EOL;

            //Create a PDO connection
            $sqlSettings = new \IOFrame\Handlers\SettingsHandler($settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');
            $conn = \IOFrame\Util\FrameworkUtilFunctions::prepareCon($sqlSettings);
            // set the PDO error mode to exception
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // INITIALIZE CORE VALUES TABLE
            /* Literally just the pivot time for now. */
            $makeTB = $conn->prepare("CREATE TABLE ".$SQLManager->getSQLPrefix()."TEST_TABLE(
                                                              tableKey varchar(255) NOT NULL,
                                                              tableValue varchar(255) NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo 'Installed test db table!: '.EOL;
            }
            catch (\Exception $e){
                echo 'Failed to install testPlugin db table: '.$e.EOL;
            }

            //-------Populate dependency map
            $PluginHandler->populateDependencies($name,$dependencies,['test'=>$test]);
            if(!$test){
                //-------Change plugin to "installed"
                $plugList->setSetting($name,'installed',['createNew'=>true]);
                //-------Add to order list
                $PluginHandler->pushToOrder($name);
            }

            echo '<span><a href="../cp/plugins">back to plugins</a></span>';
        break;
        default:
            echo 'wrong stage number!';
    }

}
else{
    echo '<span>fullInstall activates here!!</span>'.EOL.
        '<span><a href="../cp/plugins">back to plugins</a></span>';
}














