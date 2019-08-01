<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/FrontEndResourceHandler.php';

$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->getGalleries(
    [],
    ['test'=>$test,'includeChildFolders'=>true,'includeChildFiles'=>true]
);

//Parse results
foreach($result as $galleryName => $infoArray){
    $infoArray = $infoArray['@'];
    $parsedResults = [];

    //Populate results that always exist
    $parsedResults['order'] = $infoArray['Collection_Order'];
    $parsedResults['created'] = $infoArray['Created'];
    $parsedResults['lastChanged'] = $infoArray['Last_Changed'];

    //Handle meta
    $meta = $infoArray['Meta'];
    if(\IOFrame\Util\is_json($meta)){
        $meta = json_decode($meta,true);
        if(isset($meta['name']))
            $parsedResults['name'] = $meta['name'];
    }

    $result[$galleryName] = $parsedResults;
}