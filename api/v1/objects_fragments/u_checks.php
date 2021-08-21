<?php

if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

//Check that at least 'id' and one other real parameter to update are set
if( !isset($params['id']) ||
    !(
        isset($params['content']) ||
        isset($params['newVRank']) ||
        isset($params['newMRank']) ||
        isset($params['group']) ||
        isset($params['mainOwner']) ||
        isset($params['addOwners']) ||
        isset($params['remOwners']) ||
        (
            isset($executionParameters['extraContent']) &&
            is_array($executionParameters['extraContent']) &&
            count($executionParameters['extraContent']) > 0
        )
    )
){
    if($test)
        echo 'You must set an object ID, and at least 1 parameter to update!!';
    exit(INPUT_VALIDATION_FAILURE);
}

foreach($params as $key=>$value){
    switch($key){
        case 'id':
            if(preg_match_all('/[0-9]/',$value)<strlen($value)){
                if($test)
                    echo 'Object IDs must be numbers!';
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;

        case 'newVRank':
            if($value != '' && $value!=-1)
                if((gettype($value) == 'string' && preg_match_all('/\D|\-/',$value)>0) || $value<-1){
                    if($test)
                        echo 'newVRank has to be a number larger than -1!';
                    exit(INPUT_VALIDATION_FAILURE);
                }
            break;

        case 'newMRank':
            if($value != '')
                if((gettype($value) == 'string' && preg_match_all('/\D/',$value)>0) || $value<0){
                    if($test)
                        echo 'newMRank has to be a number larger than 0!';
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

        case 'mainOwner':
            if($value != '')
                if((gettype($value) == 'string' && preg_match_all('/\D/',$value)>0) || $value<0){
                    if($test)
                        echo 'mainOwner has to be a number larger than 0!';
                    exit(INPUT_VALIDATION_FAILURE);
                }
            break;

        case 'remOwners':
        case 'addOwners':
            if(!is_array($value)){
                if($test)
                    echo 'Contents of '.$key.' must be in an array!';
                exit(INPUT_VALIDATION_FAILURE);
            }
            if($value != []){
                foreach($value as $innerKey => $innerVal){
                    if((gettype($innerKey) == 'string' && preg_match_all('/[0-9]/',$innerKey)<strlen($innerKey)) || preg_match_all('/[0-9]/',$innerVal)<strlen($innerVal) ){
                        if($test)
                            echo 'Owner IDs must be numbers!';
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    if($innerKey < 0 || $innerVal < 0){
                        if($test)
                            echo 'Owner IDs must be positive integers!';
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                }
            }
            break;
    }

}

//Get all optional parameters
if(!isset($params['content']))
    $params['content'] = '';
if(!isset($params['group']))
    $params['group'] = null;
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
    exit(AUTHENTICATION_FAILURE);
