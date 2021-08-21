<?php
$retrieveParams = [
    'test'=>$test
];

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
        switch($type){
            case 'categories':
                $validArray = ['category'];
                break;
            case 'objects':
                $validArray = ['category','object'];
                break;
            case 'actions':
                $validArray = ['category','action'];
                break;
            case 'groups':
                $validArray = ['category','object','group'];
                break;
            case 'objectUsers':
                $validArray = ['category','object','userID','action'];
                break;
            case 'objectGroups':
                $validArray = ['category','object','group','action'];
                break;
            case 'userGroups':
                $validArray = ['category','object','userID','group'];
                break;
            default:
                $validArray = [];
                break;
        }
        $validArray = array_merge($validArray,['created','updated']);
        if(!in_array($inputs['orderBy'],$validArray)){
            if($test)
                echo 'orderBy must be one of the following: '.implode(',',$validArray).EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $retrieveParams['orderBy'] = $columnMap[$inputs['orderBy']];
    }

    if($inputs['orderType'] !== null){
        if(!in_array($inputs['orderType'],[0,1])){
            if($test)
                echo 'orderType must be 0 or 1!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $retrieveParams['orderType'] = $inputs['orderType'];
    }

    //Handle the filters
    if($inputs['filters'] !== null){
        if(!\IOFrame\Util\is_json($inputs['filters'])){
            if($test)
                echo 'filters must be a valid JSON array!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $inputs['filters'] = json_decode($inputs['filters'],true);

        switch($type){
            case 'categories':
                $validArray = ['titleLike','categoryIs','categoryIn'];
                break;
            case 'objects':
                $validArray = ['titleLike','categoryIs','categoryIn','objectLike','objectIn'];
                break;
            case 'actions':
                $validArray = ['titleLike','categoryIs','categoryIn','actionLike','actionIn'];
                break;
            case 'groups':
                $validArray = ['titleLike','categoryIs','categoryIn','objectLike','objectIn','groupIs','groupIn'];
                break;
            case 'objectUsers':
                $validArray = ['categoryIs','categoryIn','objectLike','objectIn','userIDIs','userIDIn','actionLike','actionIn'];
                break;
            case 'objectGroups':
                $validArray = ['categoryIs','categoryIn','objectLike','objectIn','groupIs','groupIn','actionLike','actionIn'];
                break;
            case 'userGroups':
                $validArray = ['categoryIs','categoryIn','objectLike','objectIn','userIDIs','userIDIn','groupIs','groupIn'];
                break;
            default:
                $validArray = [];
                break;
        }

        foreach($inputs['filters'] as $potentialFilter => $value){

            if(!in_array($potentialFilter,$validArray))
                continue;

            switch($potentialFilter){
                case 'titleLike':
                case 'actionLike':
                case 'objectLike':
                    if(!preg_match('/'.REGEX_REGEX.'/',$value)){
                        if($test)
                            echo $potentialFilter.' must match '.REGEX_REGEX.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    $value = str_replace('.','\.',$value);
                    $value = str_replace('-','\-',$value);
                    $value = str_replace('|','\|',$value);
                    break;

                case 'objectIn':
                case 'actionIn':
                    if(!is_array($value)){
                        if($test)
                            echo 'Each of '.$potentialFilter.' must be a valid arrays if set!'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }

                    foreach($value as $val){
                        if(!preg_match('/'.($potentialFilter === 'objectIn' ? OBJECT_REGEX : ACTION_REGEX).'/',$val)){
                            if($test)
                                echo 'Each value in '.$potentialFilter.' must be a valid integer!'.EOL;
                            exit(INPUT_VALIDATION_FAILURE);
                        }
                    }
                    break;

                case 'categoryIs':
                case 'groupIs':
                case 'userIDIs':
                    if(!filter_var($value,FILTER_VALIDATE_INT)){
                        if($test)
                            echo $potentialFilter.' must be a valid integer!'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    break;

                case 'categoryIn':
                case 'groupIn':
                case 'userIDIn':
                    if(!is_array($value)){
                        if($test)
                            echo 'Each of '.$potentialFilter.' must be a valid arrays if set!'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }

                    foreach($value as $val){
                        if(!filter_var($val,FILTER_VALIDATE_INT)){
                            if($test)
                                echo 'Each value in '.$potentialFilter.' must be a valid integer!'.EOL;
                            exit(INPUT_VALIDATION_FAILURE);
                        }
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

    switch($type){
        case 'categories':
            $keyColumns = ['category'];
            break;
        case 'objects':
            $keyColumns = ['category','object'];
            break;
        case 'actions':
            $keyColumns = ['category','action'];
            break;
        case 'groups':
            $keyColumns = ['category','object','group'];
            break;
        case 'objectUsers':
            $keyColumns = ['category','object','userID'];
            break;
        case 'objectGroups':
            $keyColumns = ['category','object','group'];
            break;
        case 'userGroups':
            $keyColumns = ['category','object','userID'];
            break;
        default:
            $keyColumns = [];
            break;
    }
    $keyCount = count($keyColumns);

    foreach($inputs['keys'] as $index => $value){

        if(!is_array($value)){
            if($test)
                echo 'Each key must be an array!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(count($value) !== $keyCount){
            if($test)
                echo 'Each array must be of length '.$keyCount.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        foreach($keyColumns as $index2 => $keyColumn){

            switch($keyColumn){
                case 'category':
                case 'userID':
                case 'group':
                    if(!filter_var($value[$index2],FILTER_VALIDATE_INT)){
                        if($test)
                            echo 'Value #'.$index2.' in key #'.$index.' must be a valid integer!'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    break;

                case 'object':
                    if(!preg_match('/'.OBJECT_REGEX.'/',$value[$index2])){
                        if($test)
                            echo 'Value #'.$index2.' in key #'.$index.' must match the pattern '.OBJECT_REGEX.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    break;

                case 'action':
                    if(!preg_match('/'.ACTION_REGEX.'/',$value[$index2])){
                        if($test)
                            echo 'Value #'.$index2.' in key #'.$index.' must match the pattern '.ACTION_REGEX.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    break;
            }
        }

    }
}