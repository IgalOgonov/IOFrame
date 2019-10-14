<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/FrontEndResourceHandler.php';

$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$requestParams = ['test'=>$test];

//Handle pagination, filters, etc..
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
if($inputs['includeLocal'])
    $requestParams['ignoreLocal'] = false;

$result = $FrontEndResourceHandler->getGalleries(
    [],
    $requestParams
);

//Parse results
foreach($result as $galleryName => $infoArray){
    //Ignore meta information in case of full search
    if($galleryName === '@')
        continue;

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