<?php

if($inputs['tokens'] !== null){
    if(!\IOFrame\Util\is_json($inputs['tokens'])){
        if($test)
            echo 'tokens must be a valid JSON!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['tokens'] = json_decode($inputs['tokens'],true);
    foreach($inputs['tokens'] as $token){
        if(!preg_match('/'.TOKEN_REGEX.'/',$token)){
            if($test)
                echo 'Every token needs to match regex '.TOKEN_REGEX.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}
else{
    if($test)
        echo 'tokens must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
