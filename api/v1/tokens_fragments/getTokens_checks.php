<?php
if($inputs['tokens'] !== null){
    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['tokens'])){
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
    $inputs['tokens'] = [];
}

$regexArr = ['tokenLike','actionLike'];
foreach($regexArr as $regexParam){
    if( $inputs[$regexParam] !== null && !preg_match('/'.REGEX_REGEX.'/',$inputs[$regexParam])){
        if($test)
            echo $regexParam.' needs to match regex '.REGEX_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    elseif( $inputs[$regexParam] !== null){
        $inputs[$regexParam] = str_replace('.','\.',$inputs[$regexParam]);
        $inputs[$regexParam] = str_replace('-','\-',$inputs[$regexParam]);
        $inputs[$regexParam] = str_replace('_','\_',$inputs[$regexParam]);
    }
}

$intArr = ['usesAtLeast','usesAtMost','expiresBefore','expiresAfter','limit','offset'];
foreach($intArr as $intParam){
    if( $inputs[$intParam] !== null && !($inputs[$intParam] === 0 || filter_var($inputs[$intParam],FILTER_VALIDATE_INT))){
        if($test)
            echo $intParam.' needs to be an int!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['limit'] === null)
    $inputs['limit'] = 50;
else
    $inputs['limit'] = max(min($inputs['limit'],500),1);

if($inputs['containsTags'] !== null){
    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['containsTags'])){
        if($test)
            echo 'containsTags must be a JSON array!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['containsTags'] = json_decode($inputs['containsTags'],true);
    foreach ($inputs['containsTags'] as $tag){
        if(!preg_match('/'.TAG_REGEX.'/',$tag)){
            if($test)
                echo 'Each tag needs to match regex '.TAG_REGEX.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}
