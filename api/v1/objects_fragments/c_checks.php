<?php

if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

//obj is required
if(!isset($params['obj'])){
    if($test)
        echo 'You must send an object parameter to create an object!';
    exit(INPUT_VALIDATION_FAILURE);
}
foreach($params as $key=>$value){
    switch($key){
        case 'obj':
            if(strlen($value)<1){
                if($test)
                    echo 'You need a non empty object if you want to create it!';
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;

        case 'minViewRank':
            if($value != '' && $value!=-1)
                if((gettype($params[$key]) == 'string' && preg_match_all('/\D|/',$value)>0 ) || $value<-1){
                    if($test)
                        echo 'minViewRank has to be a number not smaller than -1!';
                    exit(INPUT_VALIDATION_FAILURE);
                }
            break;

        case 'minModifyRank':
            if($value != '')
                if((gettype($params[$key]) == 'string' && preg_match_all('/\D/',$value)>0) || $value<0){
                    if($test)
                        echo 'minModifyRank has to be a number not smaller than 0!';
                    exit(INPUT_VALIDATION_FAILURE);
                }
            break;

        case 'group':
            if($value != '')
                if(!\IOFrame\Util\validator::validateSQLKey($value)){
                    if($test)
                        echo 'Illegal group name for the object!';
                    exit(INPUT_VALIDATION_FAILURE);
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

require_once 'checkObjectInput.php';
if(!checkObjectInput($obj,$group,$minViewRank,$minModifyRank,$sesInfo,null,'','',$siteSettings,$test))
    exit(AUTHENTICATION_FAILURE);
