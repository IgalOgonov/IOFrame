<?php


//Validate normal integers
foreach(['idAtLeast','idAtMost','createdBefore','createdAfter','rankAtLeast','rankAtMost','createdBefore','createdAfter','limit','offset'] as $param){
    if($inputs[$param] !== null && !( $inputs[$param] === 0 || filter_var($inputs[$param], FILTER_VALIDATE_INT) ) ){
        if($test)
            echo $param.' has to be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//Validate params which are regex
foreach(['usernameLike','emailLike'] as $param){
    if($inputs[$param] !== null && !preg_match('/'.REGEX_REGEX.'/',$inputs[$param])){
        if($test)
            echo $param.' has to match the regex pattern '.REGEX_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    elseif($inputs[$param] !== null){
        $inputs[$param] = str_replace('.','\.',$inputs[$param]);
        $inputs[$param] = str_replace('-','\-',$inputs[$param]);
        $inputs[$param] = str_replace('_','\_',$inputs[$param]);
    }
}

//Params which can be null, true or false, but if set will become 0 or 1
foreach(['isActive','isBanned','isLocked','isSuspicious','orderType'] as $param){
    if($inputs[$param] !== null)
        $inputs[$param] = $inputs[$param]? 1 : 0;
}

//Available order columns
if($inputs['orderBy'] !== null){
    if(!in_array($inputs['orderBy'],USER_ORDER_COLUMNS,true)){
        if($test)
            echo 'orderBy must be one of the following: '.implode(',',USER_ORDER_COLUMNS).EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}