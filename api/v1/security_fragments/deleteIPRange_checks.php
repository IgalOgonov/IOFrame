<?php

//ip
if(!isset($inputs['prefix']) || !$inputs['prefix'])
    $inputs['prefix'] = '';
elseif(!preg_match('/'.IPV4_PREFIX.'/',$inputs['prefix'])){
    if($test)
        echo 'Every IP needs to be a valid ipv4 prefix'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

$params = ['from','to'];
foreach($params as $param){
    if($inputs[$param] === null){
        if(!in_array($param,['newFrom','newTo'])){
            if($test)
                echo 'Parameter '.$param.' must be set'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    elseif(!preg_match('/'.IPV4_SEGMENT_REGEX.'/',$inputs[$param])){
        if($test)
            echo 'Parameter '.$param.' must be 0-255'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}