<?php

//Comments are not trusted by default
$extraColumnsToSet = [
    ['Trusted_Comment',0],
    ['Date_Comment_Created',(string)time()],
    ['Date_Comment_Updated',(string)time()]
];

//If the user wanted his comment to be trusted and passed the checks, mark it as trusted
if(isset($params['trusted']))
    $extraContentAdd = [
        [$params['trusted']]
    ];
else
    $extraContentAdd= [];

//Softly initiate this stuff in case of another extension using this fragment.
if(!isset($executionParameters))
    $executionParameters = [];
if(!isset($executionParameters['extraColumns']))
    $executionParameters['extraColumns'] = [];
if(!isset($executionParameters['extraContent']))
    $executionParameters['extraContent'] = [];

$executionParameters['extraColumns'] = array_merge($executionParameters['extraColumns'],$extraColumnsToSet);
$executionParameters['extraContent'] = array_merge($executionParameters['extraContent'],$extraContentAdd);


