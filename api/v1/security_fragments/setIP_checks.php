<?php
//ip
if(!filter_var($inputs['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )){
    if($test)
        echo 'IP needs to be a valid ipv4'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
//ttl
if($inputs['ttl'] === null){
    if($action === 'addIP')
        $inputs['ttl'] = 0;
}
elseif(!filter_var($inputs['ttl'], FILTER_VALIDATE_INT) && $inputs['ttl']!==0){
    if($test)
        echo 'ttl needs to be a valid int'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}