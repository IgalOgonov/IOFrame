<?php
$retrieveParams = [
    'test'=>$test
];

if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['items'])){
    if($test)
        echo 'items must be a valid JSON array!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
$inputs['items'] = json_decode($inputs['items'],true);

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
        $keyColumns = ['category','object','userID','action'];
        break;
    case 'objectGroups':
        $keyColumns = ['category','object','group','action'];
        break;
    case 'userGroups':
        $keyColumns = ['category','object','userID','group'];
        break;
    default:
        $keyColumns = [];
        break;
}

foreach($inputs['items'] as $index => $value){

    if(!is_array($value)){
        if($test)
            echo 'Each input must be an array!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    foreach($keyColumns as $requiredColumn){
        if(!isset($value[$requiredColumn])){
            if($test)
                echo 'Each array must contain at the columns '.implode(',',$keyColumns).EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }

    foreach($keyColumns as $keyColumn){

        switch($keyColumn){
            case 'category':
            case 'userID':
            case 'group':
                if(!filter_var($value[$keyColumn],FILTER_VALIDATE_INT)){
                    if($test)
                        echo 'Value #'.$keyColumn.' in key #'.$index.' must be a valid integer!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;

            case 'object':
                if(!preg_match('/'.OBJECT_REGEX.'/',$value[$keyColumn])){
                    if($test)
                        echo 'Value #'.$keyColumn.' in key #'.$index.' must match the pattern '.OBJECT_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;

            case 'action':
                if(!preg_match('/'.ACTION_REGEX.'/',$value[$keyColumn])){
                    if($test)
                        echo 'Value #'.$keyColumn.' in key #'.$index.' must match the pattern '.ACTION_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
        }

        $inputs['items'][$index][$columnMap[$keyColumn]] = $inputs['items'][$index][$keyColumn];
        unset($inputs['items'][$index][$keyColumn]);
    }

}



if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['inputs'])){
    if($test)
        echo 'inputs must be a valid JSON array!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
$inputs['inputs'] = json_decode($inputs['inputs'],true);
switch($type){
    case 'categories':
        if($test)
            echo 'categories cannot be moved!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    case 'objects':
        $moveColumns = ['category'];
        break;
    case 'actions':
        $moveColumns = ['category'];
        break;
    case 'groups':
        $moveColumns = ['object'];
        break;
    case 'objectUsers':
        $moveColumns = ['object'];
        break;
    case 'objectGroups':
        $moveColumns = ['object'];
        break;
    case 'userGroups':
        $moveColumns = ['object'];
        break;
    default:
        $moveColumns = [];
        break;
}

foreach($moveColumns as $requiredColumn){
    if(!isset($inputs['inputs'][$requiredColumn])){
        if($test)
            echo 'Each array must contain at the columns '.implode(',',$moveColumns).EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

foreach($moveColumns as $moveColumn){

    switch($moveColumns){
        case 'category':
            if(!filter_var($inputs['inputs'][$moveColumn],FILTER_VALIDATE_INT)){
                if($test)
                    echo 'Value '.implode(',',$moveColumns).' in inputs must be a valid integer!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;

        case 'object':
            if(!preg_match('/'.OBJECT_REGEX.'/',$inputs['inputs'][$moveColumn])){
                if($test)
                    echo 'Value '.implode(',',$moveColumns).' in inputs must match the pattern '.OBJECT_REGEX.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;
    }

    $inputs['inputs'][$columnMap[$moveColumn]] = $inputs['inputs'][$moveColumn];
    unset($inputs['items'][$index][$moveColumn]);
}