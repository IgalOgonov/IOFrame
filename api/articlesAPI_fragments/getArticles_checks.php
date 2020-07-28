<?php
$retrieveParams = [
    'test'=>$test
];

$requiredAuth = REQUIRED_AUTH_NONE;

// What to do if we are searching for general items
if($inputs['keys'] === null){
    $inputs['keys'] = [];

    if($inputs['limit'] !== null){
        if(!filter_var($inputs['limit'],FILTER_VALIDATE_INT)){
            if($test)
                echo 'limit must be a valid integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $retrieveParams['limit'] = $inputs['limit'];
    }
    else
        $retrieveParams['limit'] = 50;

    if($inputs['offset'] !== null){
        if(!filter_var($inputs['offset'],FILTER_VALIDATE_INT)){
            if($test)
                echo 'offset must be a valid integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $retrieveParams['offset'] = $inputs['offset'];
    }

    if($inputs['orderBy'] !== null){

        if(!\IOFrame\Util\is_json($inputs['orderBy'])){
            if($test)
                echo 'orderBy must be a valid json!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $inputs['orderBy'] = json_decode($inputs['orderBy'],true);

        $validArray = ['articleId','created','updated','weight'];

        if(count(array_diff($inputs['orderBy'],$validArray)) > 0) {
            if ($test)
                echo 'orderBy must contain only of the following: ' . implode(',', $validArray) . EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        $retrieveParams['orderBy'] = [];
        foreach($inputs['orderBy'] as $orderBy){
            array_push($retrieveParams['orderBy'],$articleColumnMap[$orderBy]);
        }
    }
    else
        $inputs['orderBy'] = [];

    if($inputs['orderType'] !== null){
        if(!in_array($inputs['orderType'],[0,1])){
            if($test)
                echo 'orderType must be 0 or 1!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $retrieveParams['orderType'] = $inputs['orderType'];
    }

    //Handle the filters
    $validArray = ['titleLike','languageIs','addressIn','addressIs','createdBefore','createdAfter','changedBefore','changedAfter','authAtMost'
        ,'authIn','weightIn'];

    foreach($inputs as $potentialFilter => $value){

        if(!in_array($potentialFilter,$validArray) || $value === null)
            continue;

        switch($potentialFilter){
            case 'titleLike':
                if(!preg_match('/'.REGEX_REGEX.'/',$value)){
                    if($test)
                        echo $potentialFilter.' must match '.REGEX_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                $value = str_replace('.','\.',$value);
                $value = str_replace('-','\-',$value);
                $value = str_replace('|','\|',$value);
                break;
            case 'addressIs':
                if(strlen($inputs['addressIs']) > ADDRESS_MAX_LENGTH){
                    if($test)
                        echo 'Each addressIs must be at most '.ADDRESS_MAX_LENGTH.' characters long!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                $address = explode('-', $inputs['addressIs']);
                foreach( $address as $subValue)
                    if(!preg_match('/'.ADDRESS_SUB_VALUE_REGEX.'/',$subValue)){
                        if($test)
                            echo 'Each value in addressIs must be a sequence of low-case characters
                                and numbers separated by "-", each sequence no longer than 24 characters long'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                break;
            case 'languageIs':
                if(!preg_match('/'.LANGUAGE_REGEX.'/',$value)){
                    if($test)
                        echo $potentialFilter.' must match '.LANGUAGE_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;

            case 'authIn':
            case 'weightIn':
            case 'addressIn':
                if(!\IOFrame\Util\is_json($value)){
                    if($test)
                        echo 'addressIn must be a valid json arrays if set!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                $value = json_decode($value,true);

                if($potentialFilter !== 'addressIn')
                    foreach($value as $val){
                        if(!filter_var($val,FILTER_VALIDATE_INT) && $val !== 0){
                            var_dump($val);
                            if($test)
                                echo $potentialFilter.' must be a valid integer (or 0)!'.EOL;
                            exit(INPUT_VALIDATION_FAILURE);
                        }
                    }
                else
                    foreach($value as $val){
                        if(strlen($val) > ADDRESS_MAX_LENGTH){
                            if($test)
                                echo 'Each value in '.$potentialFilter.' must be at most '.ADDRESS_MAX_LENGTH.' characters long!'.EOL;
                            exit(INPUT_VALIDATION_FAILURE);
                        }
                        $val = explode('-',$val);
                        foreach($val as $subValue)
                            if(!preg_match('/'.ADDRESS_SUB_VALUE_REGEX.'/',$subValue)){
                                if($test)
                                    echo 'Each value in '.$potentialFilter.' must be a sequence of low-case characters
                                        and numbers separated by "-", each sequence no longer than 24 characters long'.EOL;
                                exit(INPUT_VALIDATION_FAILURE);
                            }
                    }
                break;

            case 'createdBefore':
            case 'createdAfter':
            case 'changedBefore':
            case 'changedAfter':
            case 'authAtMost':
                if(!($value === 0 || filter_var($value,FILTER_VALIDATE_INT))){
                    if($test)
                        echo $potentialFilter.' must be a valid integer!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }

                if(in_array($potentialFilter,['authIn','weightIn']))
                    $requiredAuth = max($requiredAuth,REQUIRED_AUTH_ADMIN);
                elseif($potentialFilter === 'authAtMost'){
                    if($value > 0 && $value < 3)
                        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_OWNER);
                    else
                        $requiredAuth = max($requiredAuth,REQUIRED_AUTH_ADMIN);
                }
                break;

            default:
                if($test)
                    echo 'Somehow an invalid filter got through!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
                break;
        }

        $retrieveParams[$potentialFilter] = $value;
    }
}
else{
    $retrieveParams['limit'] = null;
    $retrieveParams['offset'] = null;
    $retrieveParams['orderBy'] = null;
    $retrieveParams['orderType'] = null;

    if(!\IOFrame\Util\is_json($inputs['keys'])){
        if($test)
            echo 'keys must be a valid JSON array!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['keys'] = json_decode($inputs['keys'],true);
    foreach($inputs['keys'] as $index => $value){
        if(!filter_var($value,FILTER_VALIDATE_INT)){
            if($test)
                echo 'Value #'.$index.' must be a valid integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }

    //Handle the one possible filter
    if($inputs['authAtMost'] !== null){

        if(!($inputs['authAtMost'] === 0 || filter_var($inputs['authAtMost'],FILTER_VALIDATE_INT))){
            if($test)
                echo 'authAtMost must be a valid integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if($inputs['authAtMost'] === 0){
            //Do nothing
        }
        if($inputs['authAtMost'] === 1)
            $requiredAuth = max($requiredAuth,REQUIRED_AUTH_RESTRICTED);
        elseif($inputs['authAtMost'] === 2)
            $requiredAuth = max($requiredAuth,REQUIRED_AUTH_OWNER);
        elseif($inputs['authAtMost'] > 2)
            $requiredAuth = max($requiredAuth,REQUIRED_AUTH_ADMIN);

        $retrieveParams['authAtMost'] = $inputs['authAtMost'];
    };
}

//Set 'authAtMost' if not requested by the user
if(!isset($retrieveParams['authAtMost']))
    $retrieveParams['authAtMost'] = 0;

//Set 'languageIs' if not requested by the user
if(!isset($retrieveParams['languageIs']))
    $retrieveParams['languageIs'] = null;