<?php

if($inputs['ranges'] !== null){
    if(!\IOFrame\Util\is_json($inputs['ranges'])){
        if($test)
            echo 'ranges must be a valid JSON!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['ranges'] = json_decode($inputs['ranges'],true);
    foreach($inputs['ranges'] as $index => $arr){
        if(!isset($arr['prefix']) || !$arr['prefix'])
            $inputs['ranges'][$index]['prefix'] = '';
        elseif(!preg_match('/'.IPV4_PREFIX.'/',$arr['prefix'])){
            if($test)
                echo 'Every IP needs to be a valid ipv4 prefix'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        $params = ['from','to'];
        foreach($params as $param){
            if(!isset($arr[$param]) || (!$arr[$param] && $arr[$param] !== 0) ){
                if($test)
                    echo 'Parameter '.$param.' must be set for each ip range'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            elseif(!preg_match('/'.IPV4_SEGMENT_REGEX.'/',$arr[$param])){
                if($test)
                    echo 'Parameter '.$param.' must be 0-255'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }

    }
}
else{
    $inputs['ranges'] = [];

    if($inputs['limit'] !== null){
        if(!filter_var($inputs['limit'],FILTER_VALIDATE_INT)){
            if($test)
                echo 'limit must be an integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }

    if($inputs['offset'] !== null){
        if(!filter_var($inputs['offset'],FILTER_VALIDATE_INT)){
            if($test)
                echo 'offset must be an integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}