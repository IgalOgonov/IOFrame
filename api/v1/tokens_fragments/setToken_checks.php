<?php

if($inputs['token'] === null){
    if($test)
        echo 'Token and action must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['tags'] !== null){
    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['tags'])){
        if($test)
            echo 'tags must be a JSON array!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['tags'] = json_decode($inputs['tags'],true);
    foreach ($inputs['tags'] as $tag){
        if(!preg_match('/'.TAG_REGEX.'/',$tag)){
            if($test)
                echo 'Each tag needs to match regex '.TAG_REGEX.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}

if(!preg_match('/'.TOKEN_REGEX.'/',$inputs['token'])){
    if($test)
        echo 'Token needs to match regex '.TOKEN_REGEX.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['tokenAction'] !== null && !preg_match('/'.ACTION_REGEX.'/',$inputs['tokenAction'])){
    if($test)
        echo 'Action needs to match regex '.ACTION_REGEX.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['ttl'] !== null && !filter_var($inputs['ttl'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'ttl needs to be a positive int!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['uses'] !== null && !($inputs['uses'] === 0 || filter_var($inputs['uses'],FILTER_VALIDATE_INT)) ){
    if($test)
        echo 'uses needs to be an int!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}