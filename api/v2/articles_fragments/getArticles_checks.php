<?php
$retrieveParams = [
    'test'=>$test
];

$requiredAuth = REQUIRED_AUTH_NONE;

// What to do if we are searching for general items
if(empty($inputs['keys'])){
    $inputs['keys'] = [];
    $retrieveParams['limit'] = $inputs['limit'];
    $retrieveParams['offset'] = $inputs['offset'];
    if(empty($inputs['orderBy']))
        $inputs['orderBy'] = [$articleSetColumnMap['weight'],$articleSetColumnMap['articleId']];
    else{
        $inputs['orderBy'] = explode(',',$inputs['orderBy']);
        $setWeight = false;
        foreach($inputs['orderBy'] as $orderBy){
            if($orderBy === 'weight')
                $setWeight = true;
            array_push($retrieveParams['orderBy'],$articleSetColumnMap[$orderBy]);
        }
        if($setWeight)
            $requiredAuth = REQUIRED_AUTH_ADMIN;
        else
            array_unshift($retrieveParams['orderBy'],$articleSetColumnMap['weight']);
        unset($setWeight);
    }

    if(isset($inputs['orderType']))
        $retrieveParams['orderType'] = $inputs['orderType'];
    else
        $retrieveParams['orderType'] = 1;

    //Handle the filters
    $validArray = ['titleLike','languageIs','addressIn','addressIs','createdBefore','createdAfter','changedBefore','changedAfter','authAtMost'
        ,'authIn','weightIn'];

    foreach($inputs as $potentialFilter => $value){

        if(!in_array($potentialFilter,$validArray) || $value === null)
            continue;

        switch($potentialFilter){

            case 'titleLike':
                $value = str_replace('.','\.',$value);
                $value = str_replace('-','\-',$value);
                $value = str_replace('|','\|',$value);
                break;

            case 'languageIs':
                if($value === '')
                    $value = '@';
                break;

            case 'authIn':
            case 'weightIn':
            case 'addressIn':
                if(empty($value))
                    $value = [];
                else
                    $value = explode(',',$value);
                break;

            case 'createdBefore':
            case 'createdAfter':
            case 'changedBefore':
            case 'changedAfter':
            case 'authAtMost':
            case 'addressIs':
                break;

            default:
                if($test)
                    echo 'Somehow an invalid filter got through!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
                break;
        }

        if(in_array($potentialFilter,['authIn','weightIn']))
            $requiredAuth = max($requiredAuth,REQUIRED_AUTH_ADMIN);
        elseif($potentialFilter === 'authAtMost'){
            if($value > 0 && $value < 3)
                $requiredAuth = max($requiredAuth,REQUIRED_AUTH_OWNER);
            else
                $requiredAuth = max($requiredAuth,REQUIRED_AUTH_ADMIN);
        }

        $retrieveParams[$potentialFilter] = $value;
    }
}
else{
    $retrieveParams['limit'] = null;
    $retrieveParams['offset'] = null;
    $retrieveParams['orderBy'] = null;
    $retrieveParams['orderType'] = null;

    $inputs['keys'] = explode(',',$inputs['keys']);

    //Handle the one possible filter
    if(!empty($inputs['authAtMost'])){
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
if(empty($retrieveParams['languageIs']))
    $retrieveParams['languageIs'] = '@';
elseif($retrieveParams['languageIs'] === '@')
    $retrieveParams['languageIs'] = null;