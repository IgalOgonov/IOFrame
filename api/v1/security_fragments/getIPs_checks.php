<?php
if($inputs['ips'] !== null){
    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['ips'])){
        if($test)
            echo 'ips must be a valid JSON!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['ips'] = json_decode($inputs['ips'],true);
    foreach($inputs['ips'] as $ip){
        if(!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )){
            if($test)
                echo 'Every IP needs to be a valid ipv4'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}
else{
    $inputs['ips'] = [];

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