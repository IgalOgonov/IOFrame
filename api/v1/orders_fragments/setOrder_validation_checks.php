<?php


//id - only if we are updating an order
if($action === 'updateOrder'){
    if($inputs['id'] !== null){
        if(!filter_var($inputs['id'],FILTER_VALIDATE_INT)){
            if($test)
                echo 'id must be an integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    else{
        if($test)
            echo 'id must be set!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//Info
if($inputs['orderInfo'] !== null) {
    if (!\IOFrame\Util\PureUtilFunctions::is_json($inputs['orderInfo'])){
        if ($test)
            echo 'orderInfo must be a json object!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//type
if($inputs['orderType'] !== null) {
    if (!\IOFrame\Util\ValidatorFunctions::validateSQLKey($inputs['orderType'])) {
        if ($test)
            echo 'Invalid type!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//status
if($inputs['orderStatus'] !== null) {
    if (!\IOFrame\Util\ValidatorFunctions::validateSQLKey($inputs['orderStatus'])) {
        if ($test)
            echo 'Invalid status!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}





