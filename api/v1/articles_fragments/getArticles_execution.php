<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result = $ArticleHandler->getItems($inputs['keys'], 'articles', $retrieveParams);

foreach($result as $key=>$res){
    if($key === '@'){
        $tempResult[$key] = $res;
        continue;
    }
    if(!is_array($res))
        continue;
    foreach($res as $resKey => $resValue){
        if(!is_array($resValue)){
            if(isset($articleResultsColumnMap[$resKey])){

                if(gettype($articleResultsColumnMap[$resKey]) === 'string')
                    $articleResultsColumnMap[$resKey] = [
                        'resultName'=> $articleResultsColumnMap[$resKey]
                    ];

                if(!empty($articleResultsColumnMap[$resKey]['type']) && $articleResultsColumnMap[$resKey]['type'] === 'json'  && $resValue === null)
                    $resValue = '{}';

                if(!empty($articleResultsColumnMap[$resKey]['type']) && gettype($resValue) !== 'array' && $resValue !== null)
                    switch($articleResultsColumnMap[$resKey]['type']){
                        case 'csv':
                            $resValue = $resValue? explode($articleResultsColumnMap[$resKey]['separator']??',',$resValue):[];
                            break;
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
                            if(!\IOFrame\Util\PureUtilFunctions::is_json($resValue))
                                break;
                            else
                                $resValue = json_decode($resValue,true);
                            if(!empty($articleResultsColumnMap[$resKey]['validChildren'])){
                                $tempRes = [];
                                foreach($articleResultsColumnMap[$resKey]['validChildren'] as $validChild){
                                    $tempRes[$validChild] = $resValue[$validChild] ?? null;
                                }
                                $resValue = $tempRes;
                            }
                            break;
                    }


                if(!empty($articleResultsColumnMap[$resKey]['groupBy'])){

                    $group = $articleResultsColumnMap[$resKey]['groupBy'];
                    if(!is_array($group))
                        $group = [$group];

                    $target = &$result[$key];
                    foreach($group as $groupLevel){
                        if(empty($target[$groupLevel]))
                            $target[$groupLevel] = [];
                        $target = &$target[$groupLevel];
                    }

                    $target[$articleResultsColumnMap[$resKey]['resultName']] = $resValue;
                }
                else
                    $result[$key][$articleResultsColumnMap[$resKey]['resultName']] = $resValue;

            }
            unset($result[$key][$resKey]);
        }
    }
}

if(isset($articlesFailedAuth))
    foreach($articlesFailedAuth as $key=>$code)
        $result[$key] = $code;