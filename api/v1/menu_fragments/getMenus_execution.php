<?php
if(!defined('MenuHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/MenuHandler.php';
$MenuHandler = new IOFrame\Handlers\MenuHandler($settings,$defaultSettingsParams);

$result = $MenuHandler->getItems(
    [],
    'menus',
    $retrieveParams
);

if(is_array($result))
    foreach($result as $key=>$res){
        if($key === '@'){
            $tempResult[$key] = $res;
            continue;
        }
        if(!is_array($res))
            continue;
        foreach($res as $resKey => $resValue){
            if(!is_array($resValue)){
                if(isset($resultsColumnMap[$resKey])){

                    if(gettype($resultsColumnMap[$resKey]) === 'string')
                        $resultsColumnMap[$resKey] = [
                            'resultName'=> $resultsColumnMap[$resKey]
                        ];

                    if(!empty($resultsColumnMap[$resKey]['type']) && $resultsColumnMap[$resKey]['type'] === 'json'  && $resValue === null)
                        $resValue = '{}';

                    if(!empty($resultsColumnMap[$resKey]['type']) && gettype($resValue) !== 'array' && $resValue !== null)
                        switch($resultsColumnMap[$resKey]['type']){
                            case 'string':
                                $resValue = (string)$resValue;
                                break;
                            case 'int':
                                $resValue = (int)$resValue;
                                break;
                            case 'bool':
                                $resValue = (bool)$resValue;
                                break;
                            case 'double':
                                $resValue = (double)$resValue;
                                break;
                            case 'json':
                                if(!\IOFrame\Util\is_json($resValue))
                                    break;
                                else
                                    $resValue = json_decode($resValue,true);
                                if(!empty($resultsColumnMap[$resKey]['validChildren'])){
                                    $tempRes = [];
                                    foreach($resultsColumnMap[$resKey]['validChildren'] as $validChild){
                                        $tempRes[$validChild] = isset($resValue[$validChild])? $resValue[$validChild] : null;
                                    }
                                    $resValue = $tempRes;
                                }
                                break;
                        }


                    if(!empty($resultsColumnMap[$resKey]['groupBy'])){

                        $group = $resultsColumnMap[$resKey]['groupBy'];
                        if(!is_array($group))
                            $group = [$group];

                        $target = &$result[$key];
                        foreach($group as $groupLevel){
                            if(empty($target[$groupLevel]))
                                $target[$groupLevel] = [];
                            $target = &$target[$groupLevel];
                        }

                        $target[$resultsColumnMap[$resKey]['resultName']] = $resValue;
                    }
                    else
                        $result[$key][$resultsColumnMap[$resKey]['resultName']] = $resValue;

                    if($resultsColumnMap[$resKey]['resultName'] === 'menu')
                        parseMenuItems($result[$key][$resultsColumnMap[$resKey]['resultName']]);

                }
                unset($result[$key][$resKey]);
            }
        }
    }