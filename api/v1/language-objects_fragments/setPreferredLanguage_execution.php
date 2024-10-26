<?php

$result = $SQLManager->updateTable(
    $SQLManager->getSQLPrefix().'USERS_EXTRA',
    ['Preferred_Language = "'.$inputs['lang'].'"'],
    [['ID',$auth->getDetail('ID'),'=']],
    ['test'=>$test]
);

if($result === true){
    $result = 0;
    $details = json_decode($_SESSION['details'],true);
    $details['Preferred_Language'] = $inputs['lang'];
    if(!$test){
        $_SESSION['details'] = json_encode($details);
    }
    else
        echo 'Setting new Preferred_Language to '.$inputs['lang'].EOL;
}
else
    $result = -1;