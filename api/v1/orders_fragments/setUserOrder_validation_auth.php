<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

//User ID
if($inputs['userID'] === null){
    if($test)
        echo 'User ID must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
else{
    if(!filter_var($inputs['userID'],FILTER_VALIDATE_INT)){
        if($test)
            echo 'user ID must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//Order ID
if($inputs['orderID'] === null){
    if($test)
        echo 'Order ID must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
else{
    if(!filter_var($inputs['orderID'],FILTER_VALIDATE_INT)){
        if($test)
            echo 'order ID must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//relationType
if($inputs['relationType'] !== null) {
    if (!\IOFrame\Util\validator::validateSQLKey($inputs['relationType'])) {
        if ($test)
            echo 'Invalid type!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}


//meta
if($inputs['meta'] !== null) {
    if (!\IOFrame\Util\is_json($inputs['meta'])){
        if ($test)
            echo 'meta must be a json object!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}