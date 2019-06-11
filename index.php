<?php

require 'util/AltoRouter.php';
require 'handlers/routeHandler.php';
require 'main/coreInit.php';
define('REQUEST_PASSED_THROUGH_ROUTER',true);

$routeSettings = new IOFrame\settingsHandler(SETTINGS_DIR_FROM_ROOT.'/routeSettings/');
$router = new AltoRouter();

//Thankfully, we have the base path created by default at installation
$router->setBasePath($settings->getSetting('pathToRoot'));

//RouteHandler stuff
$routeHandler = new IOFrame\routeHandler($settings,$defaultSettingsParams);

//Get routes
$routes = $routeHandler->getActiveRoutes();

//Save the existing match names that we need to get from the cache/db
$matchNames = [];

foreach($routes as $index => $routeArray){
    //Could be done more efficiently using a map, but since in_array is a native method this should be faster than the php alternative
    if(!in_array($routeArray['Match_Name'],$matchNames))
        array_push($matchNames,$routeArray['Match_Name']);
    //Map routes
    $router->map( $routeArray['Method'], $routeArray['Route'], $routeArray['Match_Name'], $routeArray['Map_Name']);
};

//Match and set parameters/target if we got a match
$match = $router->match();

if( is_array($match)) {
    $routeTarget = $match['target'];
    $routeParams = $match['params'];
} else {
    $routeTarget = null;
    $routeParams = null;
}

//Get required matches from the db/cache
$matches = $routeHandler->getMatches($matchNames);

if(isset($matches[$routeTarget]) && is_array($matches[$routeTarget])){
    $matchArray = $matches[$routeTarget];
    $extensions = ($matchArray['Extensions']!==null)? explode(',',$matchArray['Extensions']): ['php','html','htm','js','css'];

    $filename = __DIR__.'/'.$matchArray['URL'];
    foreach($routeParams as $paramName => $paramValue){
        $filename = preg_replace('/\['.$paramName.'\]/',$paramValue,$filename);
    }
    foreach($extensions as $extension){
        if((file_exists($filename.'.'.$extension))){
            require $filename.'.'.$extension;
            die();
        }
    }
}

//The default is to require a 404 page
if(gettype($routeSettings->getSetting('404')) == 'string' && is_file(__DIR__.'/'.$routeSettings->getSetting('404')))
    require __DIR__.'/'.$routeSettings->getSetting('404');
else
    require '404.html';
die();