<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/FrontEndResourceHandler.php';

$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->getGallery(
    $inputs['gallery'],
    ['test'=>$test,'includeGalleryInfo'=>true]
);

if(isset($result['fake Gallery']))


//Parse results

$parsedResults = [];

foreach($result as $resultName => $infoArray){
    if($resultName === '@'.$inputs['gallery']){
        $parsedResults['@'] = [];
        //Populate results that always exist
        $parsedResults['@']['order'] = $infoArray['Collection_Order'];
        $parsedResults['@']['created'] = $infoArray['Created'];
        $parsedResults['@']['lastChanged'] = $infoArray['Last_Changed'];
        //Handle meta
        $meta = $infoArray['Meta'];
        if(\IOFrame\Util\is_json($meta)){
            $meta = json_decode($meta,true);
            if(isset($meta['name']))
                $parsedResults['@']['name'] = $meta['name'];
        }
    }
    else{
        $parsedResults[$resultName] = $infoArray;
        //Unset absolute address
        unset($parsedResults[$resultName]['address']);

        //Handle meta
        $meta = $parsedResults[$resultName]['meta'];
        unset($parsedResults[$resultName]['meta']);
        if(\IOFrame\Util\is_json($meta)){
            $meta = json_decode($meta,true);
            if(isset($meta['name']))
                $parsedResults[$resultName]['name'] = $meta['name'];
            if(isset($meta['alt']))
                $parsedResults[$resultName]['alt'] = $meta['alt'];
        }
    }
}

$result = $parsedResults;