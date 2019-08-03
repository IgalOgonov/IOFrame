<?php
if(!defined('UploadHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/UploadHandler.php';
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$UploadHandler = new \IOFrame\Handlers\UploadHandler($settings,$defaultSettingsParams);
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$uploadNames = [];
foreach($inputs['items'] as $uploadName => $item){
    if(isset($item['filename']))
        array_push($uploadNames,['uploadName'=>$uploadName,'requestedName'=>$item['filename']]);
    else
        array_push($uploadNames,['uploadName'=>$uploadName]);
}

$result = $UploadHandler->handleUploadedImage(
    $uploadNames,
    [
        'test'=>false,
        'verbose'=>$test,
        'overwrite'=>$inputs['overwrite'] && !$test,
        'imageQualityPercentage'=>$inputs['imageQualityPercentage'],
        'resourceTargetPath'=>$resourceSettings->getSetting('imagePathLocal').$inputs['address'],
        'maxImageSize' => $siteSettings->getSetting('maxUploadSize'),
    ]);

$imagesToUpdate = [];
$imagesToAddToGallery = [];
$deleteLocalFiles = [];
$errorCodes = [-1,0,1,2,3];
$dbToUploadMap = [];
$uploadToDBMap = [];

//Parse the results
foreach($result as $uploadName => $res){

    //If we are testing and couldn't overwrite an existing file, assume we created it
    $fakeFile = false;
    if( ($res === 2) && $inputs['overwrite'] && $test){
        $fakeFile = true;
        $res = $resourceSettings->getSetting('imagePathLocal').$inputs['address'].$uploadName.'_TEST.test';
        $result[$uploadName] = $res;
    }

    //The DB name wont have the image path in it
    $DBName = substr($res,strlen($resourceSettings->getSetting('imagePathLocal')));
    $dbToUploadMap[$DBName] = $uploadName;
    $uploadToDBMap[$uploadName] = $DBName;

    //Only do this if the result isn't an error code
    if(!in_array($res,$errorCodes, true)){
        //Push local files to delete if we fail the DB update or if we're testing
        if(!$fakeFile)
            $deleteLocalFiles[$uploadName] = $rootFolder.$res;
        //Meta information
        $alt  = isset($inputs['items'][$uploadName]['alt'])? $inputs['items'][$uploadName]['alt'] : null;
        $name  = isset($inputs['items'][$uploadName]['name'])? $inputs['items'][$uploadName]['name'] : null;
        $caption  = isset($inputs['items'][$uploadName]['caption'])? $inputs['items'][$uploadName]['caption'] : null;
        if($alt || $name || $caption){
            $meta = [];
            if($alt)
                $meta['alt'] = $alt;
            if($name)
                $meta['name'] = $name;
            if($caption)
                $meta['caption'] = $caption;
            $meta = [json_encode($meta),'STRING'];
        }
            else
                $meta = null;
        //Add resources to be updated
        array_push(
            $imagesToUpdate,
            [
                $DBName,
                true,
                false,
                $meta
            ]
        );
    }

}

//Update the resources
if($imagesToUpdate != [])
    $updateDB =  $FrontEndResourceHandler->setResources(
        $imagesToUpdate,
        'img',
        ['test'=>$test]
    );
else
    foreach($result as $uploadName => $res){
        $updateDB[$uploadToDBMap[$uploadName]] = 0;
    }

foreach($updateDB as $DBName => $resultCode){
    if($resultCode === 0 ){
        //If gallery is not null, we remove the deletion request there. If this is a test, always delete
        if(!$test && $inputs['gallery'] === null)
            unset($deleteLocalFiles[$dbToUploadMap[$DBName]]);
        //Add each image to gallery, if requested
        if($inputs['gallery'] !== null){
            array_push(
                $imagesToAddToGallery,
                $DBName
            );
        }
    }
    else{
        //Update results
        if($resultCode === -1)
            $result[$dbToUploadMap[$DBName]] = -1;
        else
            $result[$dbToUploadMap[$DBName]] = 4;
    }
}

//Update the gallery if requested
if($imagesToAddToGallery != []){

    $updateDB =  $FrontEndResourceHandler->addImagesToGallery(
        $imagesToAddToGallery,
        $inputs['gallery'],
        ['test'=>$test,]
    );

    foreach($updateDB as $DBName => $resultCode){
        if($resultCode === 0 ){
            //If this is a test, always delete
            if(!$test)
                unset($deleteLocalFiles[$dbToUploadMap[$DBName]]);
        }
        else
            $result[$dbToUploadMap[$DBName]] = 5;
    }

}

//Delete files that we still need to delete
foreach($deleteLocalFiles as $uploadName => $addr)
    unlink($addr);

