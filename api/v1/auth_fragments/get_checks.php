<?php

//Input checks
if($params == null && ($action === 'getUsersWithActions' || $action === 'getGroupActions') ){
    if($test)
        echo 'Params must be set!';
    exit('INPUT_VALIDATION_FAILURE');
}
elseif($params == null)
    $params = [];

$expectedParams = ['id','action','group','separator','includeActions','limit','offset','orderByExp'];

foreach($expectedParams as $expectedParam){

    if(isset($params[$expectedParam]))
        switch($expectedParam){
            case 'id':
            case 'action':
            case 'group':
                $allowedExpressionsID = ['<','>','<=','>='];
                $allowedExpressionsSingle = ['='];
                $allowedExpressionsArray = ['IN','NOT IN'];
                if(!is_array($params[$expectedParam][0]))
                    $params[$expectedParam] = [$params[$expectedParam]];
                foreach($params[$expectedParam] as $pair){
                    if(in_array($pair[0],$allowedExpressionsSingle) || in_array($pair[0],$allowedExpressionsID) ){
                        if(in_array($pair[0],$allowedExpressionsID) && $expectedParam!='id'){
                            if($test)
                                echo 'Filter expression only valid for an id!'.EOL;
                            exit(INPUT_VALIDATION_FAILURE);
                        }
                        if($expectedParam =='id'){
                            if(!filter_var($pair[1],FILTER_VALIDATE_INT)){
                                if($test)
                                    echo 'id expressions must be numbers!'.EOL;
                                exit(INPUT_VALIDATION_FAILURE);
                            }
                            if($pair[1]<=0){
                                if($test)
                                    echo 'id must be larger than 0!'.EOL;
                                exit(INPUT_VALIDATION_FAILURE);
                            }
                        }
                        else{
                            if(!\IOFrame\Util\ValidatorFunctions::validateSQLKey($pair[1])){
                                if($test)
                                    echo 'Action/Group names must be 1 to 256 characters!'.EOL;
                                exit(INPUT_VALIDATION_FAILURE);
                            }
                        }
                    }
                    elseif(in_array($pair[0],$allowedExpressionsArray)){
                        if(!is_array($pair[1])){
                            if($test)
                                echo 'Filter expression expects an array of values!'.EOL;
                            exit(INPUT_VALIDATION_FAILURE);
                        }
                        if($expectedParam =='id'){
                            foreach($pair[1] as $id){
                                if(!filter_var($id,FILTER_VALIDATE_INT)){
                                    if($test)
                                        echo 'id expressions must be numbers!'.EOL;
                                    exit(INPUT_VALIDATION_FAILURE);
                                }
                            }
                        }
                        else{
                            foreach($pair[1] as $target){
                                if(!\IOFrame\Util\ValidatorFunctions::validateSQLKey($target)){
                                    if($test)
                                        echo 'Action/Group names must a 1 to 256 characters string!'.EOL;
                                    exit(INPUT_VALIDATION_FAILURE);
                                }
                            }
                        }

                    }
                    else{
                        if($test)
                            echo 'Filter expression not valid!'.EOL;
                        exit(INPUT_VALIDATION_FAILURE);
                    }
                }
                break;
            case 'separator':
                $allowedSeparators = ['OR','AND','XOR'];
                if(!in_array($params[$expectedParam] ,$allowedSeparators)){
                    if($test)
                        echo 'Allowed separators are '.json_encode($allowedSeparators).'!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'includeActions':
                //No using this API for actions if it's not getUsersWithActions or getGroupActions!
                if($action == 'getUsers' || $action == 'getGroups')
                    $params[$expectedParam] = false;
                break;
            case 'limit':
                $params[$expectedParam] = (int)$params[$expectedParam];
                if(!filter_var($params[$expectedParam],FILTER_VALIDATE_INT)){
                    if($test)
                        echo 'limit expression must be a number!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'offset':
                $params[$expectedParam] = (int)$params[$expectedParam];
                if(!filter_var($params[$expectedParam],FILTER_VALIDATE_INT) && $params[$expectedParam]!=0){
                    if($test)
                        echo 'offset expression must be a number!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'orderByExp':
                $allowedExpressions = match ($action) {
                    'getUsers' => ['ID'],
                    'getGroups' => ['Auth_Group'],
                    'getUsersWithActions' => ['ID', 'Auth_Group', 'Auth_Action'],
                    'getGroupActions' => ['Auth_Group', 'Auth_Action'],
                    default => [],
                };
                if(!in_array($params[$expectedParam],$allowedExpressions)){
                    if($test)
                        echo 'Currently, only allowed custom order expressions are '.json_encode($allowedExpressions).'!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
        }
}