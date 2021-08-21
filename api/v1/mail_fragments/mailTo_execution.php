<?php

$result = -1;

if($inputs['type'] == 'normal'){
    try{
        $MailHandler->sendMail( [[$inputs['mail']]], $inputs['subj'], $inputs['mBody'], [$inputs['mName1'],$inputs['mName2']]);
        $MailHandler->removeSecToken($inputs['mail']);
        $result =  0;
    }
    catch(Exception $e){
        if($test)
            echo 'Could not send mail: '.$e->getMessage();
    }
}
elseif($inputs['type'] == 'template'){
    if(!isset($inputs['varArray']))
        $varArray = '';
    else
        IOFrame\Util\is_json($inputs['varArray']) ? $varArray =$inputs['varArray'] : $varArray = '';
    try{
        $MailHandler->setWorkingTemplate($inputs['templateNum']);
        $MailHandler->sendMailTemplate([[$inputs['mail']]],$inputs['subj'],'',$varArray,
            [$inputs['mName1'],$inputs['mName2']]);
        $MailHandler->removeSecToken($inputs['mail']);
        $result =  0;
    }
    catch(Exception $e){
        if($test)
            echo 'Could not send mail: '.$e->getMessage();
    }

}