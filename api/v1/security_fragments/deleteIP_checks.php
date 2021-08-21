<?php

//ip
if(!filter_var($inputs['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )){
    if($test)
        echo 'IP needs to be a valid ipv4'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}