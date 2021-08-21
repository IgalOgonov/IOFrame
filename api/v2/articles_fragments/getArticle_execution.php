<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

if(!empty($inputs['id']))
    $res = $ArticleHandler->getItems([[$inputs['id']]], 'articles', $retrieveParams)[$inputs['id']];
else{
    $retrieveParams['addressIs'] = $inputs['address'];
    $res = $ArticleHandler->getItems([], 'articles', $retrieveParams);
    if(is_array($res)){
        foreach($res as $key => $item)
            if($key !== '@')
                $res = $item;
        if(count($res) < 2)
            $res = 1;
    }
}

if(is_array($res)){
    //First, parse
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
                            if(!\IOFrame\Util\is_json($resValue) || empty($articleResultsColumnMap[$resKey]['validChildren']))
                                break;
                            else
                                $resValue = json_decode($resValue,true);
                            $tempRes = [];
                            foreach($articleResultsColumnMap[$resKey]['validChildren'] as $validChild){
                                $tempRes[$validChild] = isset($resValue[$validChild])? $resValue[$validChild] : null;
                            }
                            $resValue = $tempRes;
                            break;
                    }


                if(!empty($articleResultsColumnMap[$resKey]['groupBy'])){
                    if(empty($res[$articleResultsColumnMap[$resKey]['groupBy']]))
                        $res[$articleResultsColumnMap[$resKey]['groupBy']] = [];
                    $res[$articleResultsColumnMap[$resKey]['groupBy']][$articleResultsColumnMap[$resKey]['resultName']] = $resValue;
                }
                else
                    $res[$articleResultsColumnMap[$resKey]['resultName']] = $resValue;

            }
            unset($res[$resKey]);
        }
    }
    //Second, append blocks
    $blocks = $ArticleHandler->getItems([[$res['articleId']]], 'general-block', $retrieveParams)[$res['articleId']];
    $blockOrder = !empty($res['blockOrder']) ? explode(',',$res['blockOrder']) : [];
    $res['blocks'] = [];

    if(is_array($blocks)){

        //Save the galleries in case we need to preload them.
        $galleries = [];

        //Parse blocks
        foreach($blocks as $blockKey => $blockArr){
            //Don't waste time on orphan blocks if not needed
            if(!in_array($blockKey,$blockOrder) && $inputs['ignoreOrphan']){
                continue;
            }
            elseif(is_array($blockArr)){
                //Collect the required galleries in case they're needed
                if(!empty($blockArr['Collection_Name']) && $blockArr['Block_Type'] === 'gallery')
                    array_push($galleries,$blockArr['Collection_Name']);

                foreach($blockArr as $blockArrKey => $blockValue){
                    if(isset($blocksResultsColumnMap[$blockArrKey])){

                        if(gettype($blocksResultsColumnMap[$blockArrKey]) === 'string')
                            $blocksResultsColumnMap[$blockArrKey] = [
                                'resultName'=> $blocksResultsColumnMap[$blockArrKey]
                            ];

                        if(!empty($blocksResultsColumnMap[$blockArrKey]['type']) && $blocksResultsColumnMap[$blockArrKey]['type'] === 'json'  && empty($blockValue))
                            $blockValue = '{}';

                        if(!empty($blocksResultsColumnMap[$blockArrKey]['type']) && gettype($blockValue) !== 'array' && $blockValue !== null)
                            switch($blocksResultsColumnMap[$blockArrKey]['type']){
                                case 'string':
                                    $blockValue = (string)$blockValue;
                                    break;
                                case 'int':
                                    $blockValue = (int)$blockValue;
                                    break;
                                case 'bool':
                                    $blockValue = (bool)$blockValue;
                                    break;
                                case 'double':
                                    $blockValue = (double)$blockValue;
                                    break;
                                case 'json':
                                    if(!\IOFrame\Util\is_json($blockValue) || empty($blocksResultsColumnMap[$blockArrKey]['validChildren']))
                                        break;
                                    else
                                        $blockValue = json_decode($blockValue,true);
                                    $tempRes = [];
                                    foreach($blocksResultsColumnMap[$blockArrKey]['validChildren'] as $validChild){
                                        $tempRes[$validChild] = isset($blockValue[$validChild])? $blockValue[$validChild] : null;
                                    }
                                    break;
                            }


                        if(!empty($blocksResultsColumnMap[$blockArrKey]['groupBy'])){

                            $group = $blocksResultsColumnMap[$blockArrKey]['groupBy'];
                            if(!is_array($group))
                                $group = [$group];

                            $target = &$blocks[$blockKey];
                            foreach($group as $groupLevel){
                                if(empty($target[$groupLevel]))
                                    $target[$groupLevel] = [];
                                $target = &$target[$groupLevel];
                            }

                            $target[$blocksResultsColumnMap[$blockArrKey]['resultName']] = $blockValue;
                        }
                        else
                            $blocks[$blockKey][$blocksResultsColumnMap[$blockArrKey]['resultName']] = $blockValue;
                    }
                    unset($blocks[$blockKey][$blockArrKey]);
                }
            }
        }
        //Push in order
        $toUnset = [];
        foreach($blockOrder as $key){
            if(!isset($blocks[$key])){
                $blocks[$key]['exists'] = false;
                $blocks[$key]['articleId'] = $inputs['id'];
                $blocks[$key]['blockId'] = (int)$key;
            }
            else{
                $blocks[$key]['orphan'] = false;
                $blocks[$key]['exists'] = true;
            }
            array_push($res['blocks'],$blocks[$key]);
            $toUnset[$key] = true;
        }
        //Unset blocks we pushed
        foreach($toUnset as $key=>$true){
            unset($blocks[$key]);
        }
        //Add orphan items if requested
        if(!$inputs['ignoreOrphan']){
            $blocks = array_splice($blocks,0);
            foreach($blocks as $block){
                $block['orphan'] = true;
                $block['exists'] = true;
                array_push($res['blocks'],$block);
            }
        }

        //
        if($inputs['preloadGalleries'] && count($galleries)){
            if(!defined('FrontEndResourceHandler'))
                require __DIR__ . '/../../IOFrame/Handlers/FrontEndResourceHandler.php';
            if(!isset($FrontEndResourceHandler))
                $FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);
            $galleryMembers = $FrontEndResourceHandler->getGalleries($galleries,['test'=>$test,'includeGalleryInfo'=>true]);
            foreach($res['blocks'] as $index => $block){
                if(
                    !$block['exists'] ||
                    $block['type']!=='gallery' ||
                    empty($block['collection']['name']) ||
                    empty($galleryMembers['@'.$block['collection']['name']])
                )
                    continue;

                $res['blocks'][$index]['collection']['members'] = [];

                if( empty($galleryMembers['@'.$block['collection']['name']]['Collection_Order']) )
                    continue;


                $order = explode(',',$galleryMembers['@'.$block['collection']['name']]['Collection_Order']);
                foreach($order as $address){
                    if(!empty($galleryMembers[$address]))
                        array_push($res['blocks'][$index]['collection']['members'],[
                            'address' => $galleryMembers[$address]['local']? $galleryMembers[$address]['relativeAddress'] : $galleryMembers[$address]['address'],
                            'local' => $galleryMembers[$address]['local'],
                            'updated' => (int)$galleryMembers[$address]['lastChanged'],
                            'dataType' => $galleryMembers[$address]['dataType'],
                            'meta' => \IOFrame\Util\is_json($galleryMembers[$address]['meta'])? json_decode($galleryMembers[$address]['meta'],true): []
                        ]);
                }

            }
        }

    }
}


$result = $res;