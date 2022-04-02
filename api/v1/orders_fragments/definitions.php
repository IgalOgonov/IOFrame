<?php

/* AUTH */
//Allows viewing any orders
CONST ORDERS_VIEW_AUTH = 'ORDERS_VIEW_AUTH';
//Allows modifying any orders
CONST ORDERS_MODIFY_AUTH = 'ORDERS_MODIFY_AUTH';
//Allows adjudicating in any refund cases
CONST ORDERS_ARCHIVE_AUTH = 'ORDERS_ARCHIVE_AUTH';

/*Object Auth object type*/
CONST OBJECT_AUTH_TYPE = 'orders';
/*Object Auth object view auth*/
CONST OBJECT_AUTH_VIEW_ACTION = ORDERS_VIEW_AUTH;
/*Object Auth object modify auth*/
CONST OBJECT_AUTH_MODIFY_ACTION = ORDERS_MODIFY_AUTH;

/* Validation */
CONST VALID_ORDER_ORDER_BY = ['ID','Created','Last_Updated','Order_Status','Order_Type'];
CONST VALID_ORDER_ORDER_TYPE = [0,1];

//Base validation function
function baseValidation(&$inputs,$filters,&$externalOutput = null,$test=false){
    foreach($filters as $input=>$filterArr){
        $isException = false;
        if(!empty($filterArr['exceptions']))
            foreach ($filterArr['exceptions'] as $exception)
                if(($inputs[$input]??null) === $exception)
                    $isException = true;

        if(!isset($inputs[$input]) || $inputs[$input] === null){
            if(empty($filterArr['ignoreNull']))
                $inputs[$input] = $filterArr['default'] ?? null;
        }
        elseif(!$isException){
            switch($filterArr['type']){
                case 'string':
                case 'int':
                    if(!empty($filterArr['valid']) && is_array($filterArr['valid']) && !in_array($inputs[$input],$filterArr['valid'])){
                        if($test)
                            echo $input.' must be in '.implode(',',$filterArr['valid']).EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    elseif(!empty($filterArr['valid']) && !is_array($filterArr['valid']) && ($filterArr['type'] === 'string') &&!preg_match('/'.$filterArr['valid'].'/',$inputs[$input])){
                        if($test)
                            echo $input.' must match '.$filterArr['valid'].EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    elseif(($filterArr['type'] === 'int') && !filter_var($inputs[$input],FILTER_VALIDATE_INT)){
                        if($test)
                            echo $input.' must be a valid integer!'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    break;
                case 'string[]':
                case 'int[]':
                case 'json':
                    if(!is_array($inputs[$input])){
                        if(!\IOFrame\Util\is_json($inputs[$input])){
                            if($test)
                                echo $input.' must be a valid json!'.EOL;
                            exit(INPUT_VALIDATION_FAILURE);
                        }
                        $inputs[$input] = json_decode($inputs[$input],true);
                    }
                    if(empty($filterArr['valid']) && ($filterArr['type'] === 'int[]')){
                        foreach ($inputs[$input] as $integer){
                            if(!filter_var($integer,FILTER_VALIDATE_INT)){
                                if($test)
                                    echo $input.' must be a valid integer array!'.EOL;
                                exit(INPUT_VALIDATION_FAILURE);
                            }
                        }
                    }
                    elseif(!empty($filterArr['valid']) && !is_array($filterArr['valid']) && ($filterArr['type'] !== 'int[]')){
                        foreach ($inputs[$input] as $string){
                            if(!preg_match('/'.$filterArr['valid'].'/',$string)){
                                if($test)
                                    echo $input.' must all match '.$filterArr['valid'].EOL;
                                exit(INPUT_VALIDATION_FAILURE);
                            }
                        }
                    }
                    elseif(!empty($filterArr['valid']) && is_array($filterArr['valid']) && (count(array_diff($inputs[$input],$filterArr['valid'])) > 0)){
                        if($test)
                            echo $input.' must be in '.$filterArr['valid'].EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                    if(!empty($filterArr['keepJson']))
                        $inputs[$input] = json_encode($inputs[$input]);
                    break;
            }
        }

        if($externalOutput)
            $externalOutput[$input] = $inputs[$input];
    }
}

/* Parsing */
/** Parses an order
 * @param array $order
 * @returns array
 */
function parseOrder($order,$params = []){
    $tempRes = [];

    $tempRes['type'] = $order['Order_Type'];
    $tempRes['status'] = $order['Order_Status'];

    $tempRes['created'] = (int)$order['Created'];
    $tempRes['updated'] = (int)$order['Last_Updated'];
    //TODO Decide which information is removed when not specific auth
    $tempRes['history'] = json_decode($order['Order_History'],true);
    $tempRes['info'] = json_decode($order['Order_Info'],true);

    //users
    $tempRes['users'] = [];
    if(!empty($order['users'])){
        foreach($order['users'] as $user){
            $key = $user['User_ID'];
            $tempRes['users'][$key] = [];
            $tempRes['users'][$key]['relation'] = $user['Relation_Type'];
            $tempRes['users'][$key]['meta'] = $user['Meta'];
            $tempRes['users'][$key]['created'] = $user['Created'];
            $tempRes['users'][$key]['updated'] = $user['Last_Updated'];
        }
    }
    elseif(!empty($order['Order_Users'])){
        $temp = explode(',',$order['Order_Users']);
        foreach ($temp as $val){
            $val = explode('/',$val);
            $tempRes['users'][$val[0]] = [
                'relation'=>$val[1]
            ];
        }
    }

    return $tempRes;
}
/** Parses orders
 * @param array $orders
 * @returns array
 */
function parseOrders($orders,$params = []){
    $res = [];
    foreach ($orders as $id => $order){
        if($id === '@')
            $res[$id] =  $order;
        else
            $res[$id] = is_array($order) ? parseOrder($order,$params) : $order;
    }
    return $res;
}





