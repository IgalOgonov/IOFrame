<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/FrontEndResourceHandler.php';

$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->getImages(
    $inputs['addresses'],
    ['test'=>$test,'includeChildFolders'=>true,'includeChildFiles'=>true]
);

//Remove the root folder itself, if we got it
if(!isset($inputs['address']))
    unset($result['']);
else
    unset($result[$inputs['address']]);

//Parse results
foreach($result as $relativeAddress => $infoArray){
    //Unset absolute address
    unset($result[$relativeAddress]['address']);

    //Handle meta
    $meta = $result[$relativeAddress]['meta'];
    unset($result[$relativeAddress]['meta']);
    if(\IOFrame\Util\is_json($meta)){
        $meta = json_decode($meta,true);
        if(isset($meta['name']))
            $result[$relativeAddress]['name'] = $meta['name'];
        if(isset($meta['alt']))
            $result[$relativeAddress]['alt'] = $meta['alt'];
        if(isset($meta['caption']))
            $result[$relativeAddress]['caption'] = $meta['caption'];
    }
}