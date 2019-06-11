<?php

//We still don't know if the user is updating trusted comments or not
$extraColumnsToSet = [];
$extraContentAdd = [];
//If the user wanted his comment to be trusted and passed the checks, mark it as trusted
if(isset($params['trusted'])){
    array_push($extraColumnsToSet,['Trusted_Comment',0]);
    $extraContentAdd[$params['id']] = $params['trusted'];
}
//Only update this field if the user updated the comment itself, not some meta-data
if(isset($params['content'])){
    array_push($extraColumnsToSet,['Date_Comment_Updated',(string)time()]);
}

//Softly initiate this stuff in case of another extension using this fragment.
if(!isset($executionParameters))
    $executionParameters = [];
if(!isset($executionParameters['extraColumns']))
    $executionParameters['extraColumns'] = [];
if(!isset($executionParameters['extraContent']))
    $executionParameters['extraContent'] = [];
if(!isset($executionParameters['extraContent'][$params['id']]))
    $executionParameters['extraContent'][$params['id']] = [];

$executionParameters['extraColumns'] = array_merge($executionParameters['extraColumns'],$extraColumnsToSet);
$executionParameters['extraContent'][$params['id']] = array_merge($executionParameters['extraContent'][$params['id']],$extraContentAdd);


