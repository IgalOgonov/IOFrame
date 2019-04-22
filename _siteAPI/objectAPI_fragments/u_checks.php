<?php

require_once __DIR__.'/../../_util/validator.php';

//Check that at least 'id' and one other real parameter to update are set
if( !isset($params['id']) ||
    !(
        isset($params['content']) ||
        isset($params['newVRank']) ||
        isset($params['newMRank']) ||
        isset($params['group']) ||
        isset($params['mainOwner']) ||
        isset($params['addOwners']) ||
        isset($params['remOwners'])
    )
){
    echo 'You must set an object ID, and at least 1 parameter to update!!';
    return false;
}

foreach($params as $key=>$value){
    switch($key){
        case 'id':
            if(preg_match_all('/[0-9]/',$value)<strlen($value)){
                echo 'Object IDs must be numbers!';
                return false;
            }
            break;

        case 'newVRank':
            if($value != '' && $value!=-1)
                if((gettype($value) == 'string' && preg_match_all('/\D|\-/',$value)>0) || $value<-1){
                    echo 'newVRank has to be a number larger than -1!';
                    return false;
                }
            break;

        case 'newMRank':
            if($value != '')
                if((gettype($value) == 'string' && preg_match_all('/\D/',$value)>0) || $value<0){
                    echo 'newMRank has to be a number larger than 0!';
                    return false;
                }
            break;

        case 'group':
            if($value != '')
                if(!\IOFrame\validator::validateSQLKey($value)){
                    echo 'Illegal group name for the object!';
                    return false;
                }
            break;

        case 'mainOwner':
            if($value != '')
                if((gettype($value) == 'string' && preg_match_all('/\D/',$value)>0) || $value<0){
                    echo 'mainOwner has to be a number larger than 0!';
                    return false;
                }
            break;

        case 'remOwners':
        case 'addOwners':
            if(!is_array($value)){
                echo 'Contents of '.$key.' must be in an array!';
                return false;
            }
            if($value != []){
                foreach($value as $innerKey => $innerVal){
                    if((gettype($innerKey) == 'string' && preg_match_all('/[0-9]/',$innerKey)<strlen($innerKey)) || preg_match_all('/[0-9]/',$innerVal)<strlen($innerVal) ){
                        echo 'Owner IDs must be numbers!';
                        return false;
                    }
                    if($innerKey < 0 || $innerVal < 0){
                        echo 'Owner IDs must be positive integers!';
                        return false;
                    }
                }
            }
            break;

        case '?':
            if(!($value==true || $value==false)){
                echo 'Illegal test value!';
                return false;
            }
            break;
    }

}

//Get all optional parameters
if(!isset($params['content']))
    $params['content'] = '';
if(!isset($params['group']))
    $params['group'] = '';
if(!isset($params['newVRank']))
    $params['newVRank'] = null;
if(!isset($params['newMRank']))
    $params['newMRank'] = null;
if(!isset($params['mainOwner']))
    $params['mainOwner'] = null;
if(!isset($params['addOwners']))
    $params['addOwners'] = [];
if(!isset($params['remOwners']))
    $params['remOwners'] = [];

//If an object failed this input check, echo 1.
require_once 'checkObjectInput.php';
if(
!checkObjectInput(
    $params['content'],
    $params['group'],
    $params['newVRank'],
    $params['newMRank'],
    $sesInfo,
    $params['mainOwner'],
    $params['addOwners'],
    $params['remOwners'],
    $siteSettings,
    $test
)
)
    echo 1;
