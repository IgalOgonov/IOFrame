<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->getFrontendResourceCollections(
    [$inputs['gallery']],
    ($action === 'getGallery' ? 'img':'vid'),
    ['test'=>$test,'includeGalleryInfo'=>true]
);


//Parse results
$parsedResults = [];
foreach($result as $resultName => $infoArray){
    if($resultName === '@'.$inputs['gallery']){
        $parsedResults['@'] = [];
        //Populate results that always exist
        $parsedResults['@']['order'] = $infoArray['Collection_Order'];
        $parsedResults['@']['created'] = $infoArray['Created'];
        $parsedResults['@']['lastChanged'] = $infoArray['Last_Updated'];
        //Handle meta
        $meta = $infoArray['Meta'];
        if(\IOFrame\Util\is_json($meta)){
            $meta = json_decode($meta,true);

            $expected = ['name'];

            foreach($languages as $lang){
                array_push($expected,$lang.'_name');
            }

            foreach($expected as $attr){
                if(isset($meta[$attr]))
                    $parsedResults['@'][$attr] = $meta[$attr];
            }

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
            $expected = ['name','caption','size'];
            if($action === 'getGallery')
                array_push($expected,'alt');
            else
                array_push($expected,'autoplay','loop','mute','controls','poster','preload');
            foreach($languages as $lang){
                array_push($expected,$lang.'_name');
                array_push($expected,$lang.'_caption');
            }
            foreach($expected as $attr){
                if(isset($meta[$attr]))
                    $parsedResults[$resultName][$attr] = $meta[$attr];
            }
        }
    }
}

$result = $parsedResults;