<?php

require_once __DIR__.'/../tags_fragments/definitions.php';

$requiredAuth = REQUIRED_AUTH_OWNER;

$setParams = [];

$setParams['test'] = $test;

if(!$auth->isLoggedIn()){
    if($test)
        echo 'Must be logged in to remove article tags!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}


$requiredParams = ['articleId','tags'];

foreach($requiredParams as $param){
    if($inputs[$param] === null){
        if($test)
            echo $param.' must be set!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
$inputs['type'] = $inputs['type'] ?? 'default-article-tags';

foreach($requiredParams as $param){
    switch($param){
        case 'articleId':
            if(!filter_var($inputs[$param],FILTER_VALIDATE_INT)){
                if($test)
                    echo $param.' needs to be a valid int!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;
        case 'type':
            if(!in_array($inputs[$param],$TagHandlerBaseTagTypes)){
                if($test)
                    echo $param.' types must be valid'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;
        case 'tags':
            if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs[$param])){
                if($test)
                    echo $param.' must be a valid json array if set!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            $inputs[$param] = json_decode($inputs[$param],true);
            foreach($inputs[$param] as $key => $val){
                if(!preg_match('/'.$TAGS_API_DEFINITIONS['validation']['tagRegex'].'/',$val)){
                    if($test)
                        echo $potentialFilter.' tag names must all be valid'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
            }
            break;
    }

}