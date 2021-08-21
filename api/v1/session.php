<?php
/*Current API used to get some session details, as well as a single related global setting (time after which a session expires)*/

if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';

$resArr = array();

foreach($_REQUEST as $key => $value){

    if($key=='maxInacTime'){
        $resArr[$key]=$siteSettings->getSetting('maxInacTime');
    }
    else if($key=='CSRF_token'){
        //Remember, the only way to get a CSRF key is to get referred by the same domain the server is hosted on.
        if(!isset($_SERVER['HTTP_REFERER'])){
            $resArr[$key] = null;
            continue;
        }
        //Clean referrer of protocol prefix
        if(strpos($_SERVER['HTTP_REFERER'],'//')!==-1){
            $_SERVER['HTTP_REFERER'] = explode('//',$_SERVER['HTTP_REFERER'])[1];
        }
        //Only return this if the request was made from this site! Remember - a legitimate client will send legitimate headers.
        if(preg_match('/^'.str_replace('.','\.',$_SERVER['HTTP_HOST']).'/',$_SERVER['HTTP_REFERER']))
            $resArr[$key]=$_SESSION['CSRF_token'];
    }

    if(isset($_SESSION['logged_in'])){
        $sessionDetails = json_decode($_SESSION['details'],true);
        if(!isset($sessionDetails[$key]))
            continue;
        if($key=='Email')
            $resArr[$key]=json_decode($_SESSION['details'],true)['Email'];
        else if($key=='Username')
            $resArr[$key]=json_decode($_SESSION['details'],true)['Username'];
        else if($key=='Auth_Rank')
            $resArr[$key]=json_decode($_SESSION['details'],true)['Auth_Rank'];
        else if($key=='Active')
            $resArr[$key]=json_decode($_SESSION['details'],true)['Active'];
        else if($key=='logged_in')
            $resArr[$key]=$_SESSION[$key];

    }
    else if($key=='logged_in')
        $resArr[$key]=false;

}

if($resArr!==[])
    echo json_encode($resArr);
?>