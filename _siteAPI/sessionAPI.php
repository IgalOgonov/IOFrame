<?php
/*Current API used to get some session details, as well as a single related global setting (time after which a session expires)*/
require __DIR__ . '/../_Core/coreInit.php';

$resArr = array();

foreach($_REQUEST as $key => $value){
    if(isset($_SESSION['logged_in'])){
        if($key=='Email')
            $resArr[$key]=json_decode($_SESSION['details'],true)['Email'];
        else if($key=='Username')
            $resArr[$key]=json_decode($_SESSION['details'],true)['Username'];
        else if($key=='Rank')
            $resArr[$key]=json_decode($_SESSION['details'],true)['Rank'];
        else if($key=='Active')
            $resArr[$key]=json_decode($_SESSION['details'],true)['Active'];
        else if($key=='logged_in')
            $resArr[$key]=$_SESSION[$key];
        else if($key=='maxInacTime'){
            if(!isset($siteSettings))
                $siteSettings = new IOFrame\settingsHandler(IOFrame\getAbsPath().SETTINGS_DIR_FROM_ROOT.'/siteSettings/');
            $resArr[$key]=$siteSettings->getSetting('maxInacTime');
        }

    }

    else if($key=='logged_in')
        $resArr[$key]=false;
    else if($key=='maxInacTime'){
        if(!isset($siteSettings))
            $siteSettings = new IOFrame\settingsHandler(IOFrame\getAbsPath().SETTINGS_DIR_FROM_ROOT.'/siteSettings/');
        $resArr[$key]=$siteSettings->getSetting('maxInacTime');
    }

}

echo json_encode($resArr);
?>