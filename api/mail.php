<?php
/* Current API for the frontEnd to use to see its authentication details, as well as some global server settings
 * As for sending mails, it is used ONLY to send the first mail to a user asynchronously - meaning no ccs, bccs, replies
 * and certainly no attachments are allowed.
*/
if(!defined('coreInit'))
    require __DIR__ . '/../main/coreInit.php';
require __DIR__ . '/../IOFrame/Handlers/MailHandler.php';

require 'defaultInputChecks.php';
require 'defaultInputResults.php';

if($test){
    echo 'Testing mode!'.EOL;
    foreach($_REQUEST as $key=>$value)
        echo htmlspecialchars($key.': '.$value).EOL;
}

function checkInput(){

    if(isset($_REQUEST['action']))
        $ac = $_REQUEST['action'];
    else
        exit('No action specified');

    if(isset($_REQUEST['secToken']))
        $st = $_REQUEST['secToken'];
    else
        exit('No security token specified');

    if($ac == 'mailTo'){
        if(isset($_REQUEST['type']))
            $mType = $_REQUEST['type'];
        else
            $mType = 'normal';

        if(!isset($_REQUEST['mail'])||!isset($_REQUEST['subj']))
            exit('The mail must have a subject and recipients.');
        else
            $m = $_REQUEST['mail'];

        if($mType=='template'){
            if(!isset($_REQUEST['templateNum']))
                exit('Cannot send template without a number');
            else
                $tNum = $_REQUEST['templateNum'];
        }
        else if($mType = 'normal'){
            if(!isset($_REQUEST['mBody']))
                exit('You need a mail body to send a mail');
        }


    }


    //create mClean - mail without the first @, to see if it has an illegal characters
    //Validate Mail
    if( !filter_var($m,FILTER_VALIDATE_EMAIL)){
        exit('Illegal recipient mail!');
    }
    //Validate template number
    if( (preg_match('/\D/',$tNum)!=0)){
        exit('Illegal template number!');
    }

    if( (preg_match_all('/[0-9]||[a-z]||[A-Z]/',$st)<strlen($st))){
        exit('Illegal security token!');
    }

}


checkInput();

$MailHandler = new IOFrame\Handlers\MailHandler(
    $settings,
    $defaultSettingsParams
);

switch($_REQUEST['action']){
    case 'mailTo':
        if(!isset($_REQUEST['mail']))
            exit('No recipient mails specified.');
        if(!isset($_REQUEST['secToken']))
            exit('No security token provided, action not authorized.');
        else{
            $res = $MailHandler->verfySecToken($_REQUEST['secToken'],$_REQUEST['mail']);
            if($res !=0)
                exit('Security token does not allow this action, error '.$res);
            else{
                //This is where the fun begins
                if(!isset($_REQUEST['type']))
                    $type = 'normal';
                else
                    $type = $_REQUEST['type'];

                if(isset($_REQUEST['mName1']))
                    $mName1=$_REQUEST['mName1'];
                else
                    $mName1='';

                if(isset($_REQUEST['mName2']))
                    $mName2=$_REQUEST['mName2'];
                else
                    $mName2='';

                if($type == 'normal'){
                    try{
                        $MailHandler->sendMail( [[$_REQUEST['mail']]], $_REQUEST['subj'], $_REQUEST['mBody'], [$mName1,$mName2]);
                        if(!isset($_REQUEST['keepAuth']))
                            $MailHandler->removeSecToken($_REQUEST['mail']);
                        echo 0;
                    }
                    catch(Exception $e){
                        echo 'Could not send mail: '.$e->getMessage();
                    }
                }
                else if($type == 'template'){
                    if(!isset($_REQUEST['varArray']))
                        $varArray = '';
                    else
                        IOFrame\Util\is_json($_REQUEST['varArray']) ? $varArray =$_REQUEST['varArray'] : $varArray = '';
                    try{
                        $MailHandler->setTemplate($_REQUEST['templateNum']);
                        $MailHandler->sendMailTemplate([[$_REQUEST['mail']]],$_REQUEST['subj'],'',$varArray,
                            [$mName1,$mName2]);
                        if(!isset($_REQUEST['keepAuth']))
                            $MailHandler->removeSecToken($_REQUEST['mail']);
                        echo 0;
                    }
                    catch(Exception $e){
                        echo 'Could not send mail: '.$e->getMessage();
                    }

                }
                else
                    exit('Unknown mailing action type!');
            }
        }
        break;
    default:
        exit('Specified action is not recognized');
}