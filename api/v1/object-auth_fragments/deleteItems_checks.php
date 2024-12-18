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

$keyColumns = match ($type) {
    'categories' => ['category'],
    'objects' => ['category', 'object'],
    'actions' => ['category', 'action'],
    'groups' => ['category', 'object', 'group'],
    'objectUsers' => ['category', 'object', 'userID', 'action'],
    'objectGroups' => ['category', 'object', 'group', 'action'],
    'userGroups' => ['category', 'object', 'userID', 'group'],
    default => [],
};

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