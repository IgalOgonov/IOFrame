<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$requestParams = ['test'=>$test,'includeChildFolders'=>true,'includeChildFiles'=>true];

//If we are getting all images, handle the pagination and filters
if($inputs['getDB']){
    $requestParams['limit'] = min($inputs['limit'],500);
    if($inputs['offset'] !== null)
        $requestParams['offset'] = $inputs['offset'];
    if($inputs['createdAfter'] !== null)
        $requestParams['createdAfter'] = $inputs['createdAfter'];
    if($inputs['createdBefore'] !== null)
        $requestParams['createdBefore'] = $inputs['createdBefore'];
    if($inputs['changedAfter'] !== null)
        $requestParams['changedAfter'] = $inputs['changedAfter'];
    if($inputs['changedBefore'] !== null)
        $requestParams['changedBefore'] = $inputs['changedBefore'];
    if($inputs['includeRegex'] !== null)
        $requestParams['includeRegex'] = $inputs['includeRegex'];
    if($inputs['excludeRegex'] !== null)
        $requestParams['excludeRegex'] = $inputs['excludeRegex'];
    if($inputs['dataType'] !== null){
        $requestParams['dataType'] = $inputs['dataType'];
    }
    $requestParams['ignoreLocal'] = $inputs['includeLocal']? false : true;
}

$result = (
    $action === 'getImages' ?
        $FrontEndResourceHandler->getImages(
            $inputs['addresses'],
            $requestParams
        )
        :
        $FrontEndResourceHandler->getVideos(
            $inputs['addresses'],
            $requestParams
        )
);

//Remove the root folder itself, if we got it
if($inputs['address'] === '')
    unset($result['']);
else
    unset($result[$inputs['address']]);

//Parse results
foreach($result as $address => $infoArray){

    //Unset absolute address
    unset($result[$address]['address']);

    //Ignore meta information in case of full search
    if($address === '@')
        continue;

    //Handle meta
    $meta = $result[$address]['meta'];
    unset($result[$address]['meta']);
    if(\IOFrame\Util\is_json($meta)){
        $meta = json_decode($meta,true);

        $expected = ['name','caption','size'];
        foreach($languages as $lang){
            array_push($expected,$lang.'_name');
            array_push($expected,$lang.'_caption');
        }
        if($action === 'getImages'){
            array_push($expected,'alt');
        }
        elseif($action === 'getVideos'){
            array_push($expected,'autoplay','loop','mute','controls','autoplay','poster','preload');
        }

        foreach($expected as $attr){
            if(isset($meta[$attr]))
                $result[$address][$attr] = $meta[$attr];
        }
    }
}