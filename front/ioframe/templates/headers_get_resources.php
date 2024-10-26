<?php
$JSOptions = ['rootFolder'=>$IOFrameJSRoot,'forceMinify'=>(!$devMode)];
$CSSOptions = ['rootFolder'=>$IOFrameCSSRoot,'forceMinify'=>(!$devMode)];

//Some defaults
if(!isset($minifyOptions))
    $minifyOptions = [];
if(!isset($minifyOptions['js']))
    $minifyOptions['js'] = null;
if(!isset($minifyOptions['css']))
    $minifyOptions['css'] = null;

//Defined to be used by frontEndResourceTemplateManager later - can also changed if we minify resources into a single file
$JSOrder = $JS;
$CSSOrder = $CSS;

foreach(['js','css'] as $resourceType){
    if($minifyOptions[$resourceType] && is_array($minifyOptions[$resourceType])){
        //Minified file name
        if($minifyOptions[$resourceType]['name']){
            if($resourceType === 'js'){
                $JSOptions['minifyName'] = $minifyOptions[$resourceType]['name'];
                $JSOrder = [$JSOptions['minifyName']];
            }
            else{
                $CSSOptions['minifyName'] = $minifyOptions[$resourceType]['name'];
                $CSSOrder = [$CSSOptions['minifyName']];
            }

            //Minified file folder - defaults to 'min'
            if($minifyOptions[$resourceType]['folder']){
                if($resourceType === 'js')
                    $JSOptions['minifyToFolder'] = $minifyOptions[$resourceType]['folder'];
                else
                    $CSSOptions['minifyToFolder'] = $minifyOptions[$resourceType]['folder'];
            }
            else{
                if($resourceType === 'js')
                    $JSOptions['minifyToFolder'] = 'min';
                else
                    $CSSOptions['minifyToFolder'] = 'min';
            }
        }
    }
}

$JSResources = $FrontEndResources->getJS($JS,$JSOptions);
$CSSResources = $FrontEndResources->getCSS($CSS,$CSSOptions);

$_packageAndAddToResources = function (array &$resources, array &$order, string $name, array $package, array $params = []) use($FrontEndResources){
    $params['type'] = $params['type'] ?? 'js';
    $params['minifyToFolder'] = $params['minifyToFolder'] ?? 'min';
    $items = $package['items'] ?? [];
    $itemInOrder = $package['order'] ?? -1;
    $minified = $params['type'] === 'js' ?
        $FrontEndResources->minifyJSFiles(
            $items,
            ['minifyToFolder'=>$params['minifyToFolder'],'minifyName'=>$name]
        ) :
        $FrontEndResources->minifyCSSFiles(
            $items,
            ['minifyToFolder'=>$params['minifyToFolder'],'minifyName'=>$name]
        );
    if(is_array($minified) && !empty($minified[$name])){
        $new = $params['minifyToFolder'].'/'.$name.'.min.'.$params['type'];
        $resources[$new] = $minified[$name];
        if($itemInOrder < 0)
            $order[] = $new;
        else
            array_splice($order,$itemInOrder,0,$new);
        return true;
    }
    else
        return false;
};

if(!empty($JSPackages)){
    foreach ($JSPackages as $name=>$package)
        $_packageAndAddToResources($JSResources,$JSOrder,$name,$package,['type'=>'js']);
}

if(!empty($CSSPackages)){
    foreach ($CSSPackages as $name=>$package)
        $_packageAndAddToResources($CSSResources,$CSSOrder,$name,$package,['type'=>'css']);
}

$frontEndResourceTemplateManager = new \IOFrame\Managers\FrontEndResourceTemplateManager(
    [
        'JSResources' => $JSResources,
        'CSSResources' => $CSSResources,
        'JSOrder' => $JSOrder,
        'CSSOrder' => $CSSOrder,
        'dirToRoot' => $dirToRoot,
        'JSResourceRoot' => $IOFrameJSRoot,
        'CSSResourceRoot' => $IOFrameCSSRoot
    ]
);