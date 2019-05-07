<?php

if(!defined('validator'))
    require __DIR__.'/../../_util/validator.php';

//obj is required
if(!isset($params['obj'])){
    if($test)
        echo 'You must send an object parameter to create an object!';
    exit('-1');
}
foreach($params as $key=>$value){
    switch($key){
        case 'obj':
            if(strlen($value)<1){
                if($test)
                    echo 'You need a non empty object if you want to create it!';
                exit('-1');
            }
            break;

        case 'minViewRank':
            if($value != '' && $value!=-1)
                if((gettype($params[$key]) == 'string' && preg_match_all('/\D|/',$value)>0 ) || $value<-1){
                    if($test)
                        echo 'minViewRank has to be a number not smaller than -1!';
                    exit('-1');
                }
            break;

        case 'minModifyRank':
            if($value != '')
                if((gettype($params[$key]) == 'string' && preg_match_all('/\D/',$value)>0) || $value<0){
                    if($test)
                        echo 'minModifyRank has to be a number not smaller than 0!';
                    exit('-1');
                }
            break;

        case 'group':
            if($value != '')
                if(!\IOFrame\validator::validateSQLKey($value)){
                    if($test)
                        echo 'Illegal group name for the object!';
                    exit('-1');
                }
            break;
    }
}

//Object to create
$obj = $params['obj'];

//Optional parameters
isset($params['minViewRank'])?
    $minViewRank = $params['minViewRank'] : $minViewRank = -1;
isset($params['minModifyRank'])?
    $minModifyRank = $params['minModifyRank'] : $minModifyRank = 0;
isset($params['group'])?
    $group = $params['group'] : $group = '';
//If an object failed this input check, echo 1.
require_once 'checkObjectInput.php';
if(!checkObjectInput($obj,$group,$minViewRank,$minModifyRank,$sesInfo,null,'','',$siteSettings,$test))
    echo 1;
