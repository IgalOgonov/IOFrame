<?php

require 'IOFrame/Util/ext/AltoRouter.php';
require 'IOFrame/Handlers/RouteHandler.php';
require 'main/coreInit.php';
define('REQUEST_PASSED_THROUGH_ROUTER',true);

$router = new AltoRouter();

//Thankfully, we have the base path created by default at installation
$router->setBasePath($settings->getSetting('pathToRoot'));

//RouteHandler stuff
$RouteHandler = new IOFrame\Handlers\RouteHandler($settings,$defaultSettingsParams);

//Get routes
$routes = $RouteHandler->getActiveRoutes();

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
$matches = $RouteHandler->getMatches($matchNames);

//If the correct match is found, proccess it
if(isset($matches[$routeTarget]) && is_array($matches[$routeTarget])){

    //DB array
    $matchArray = $matches[$routeTarget];
    //Get DB extensions, or set default ones
    $extensions = ($matchArray['Extensions']!==null)? explode(',',$matchArray['Extensions']): ['php','html','htm','js','css'];

    //If the match is a string, enclose it in a JSON object
    if(!\IOFrame\Util\is_json($matchArray['URL']))
        $matchArray['URL'] = [
            ['include' => $matchArray['URL'], 'exclude'=>[]]
        ];
    //If the match is an array, see if it's an array of rules or just one include/exclude pair
    else{
        $matchArray['URL'] = json_decode($matchArray['URL'], true);
        //In case it's just one include/exclude, envelope it in an array
        if(isset($matchArray['URL']['include']))
            $matchArray['URL'] = [$matchArray['URL']];
        //In this case, check for each element if it's a string or an object
        else{
            foreach($matchArray['URL'] as $key => $object){
                if(gettype($object) == 'string')
                    $matchArray['URL'][$key] = ['include' => $object, 'exclude'=>[]];
            }
        }
    }

    //Check the rules
    foreach($matchArray['URL'] as $ruleArray){

        //The include file
        $filename = __DIR__.'/'.$ruleArray['include'];

        //Replace the relevant parts with route parameters.
        foreach($routeParams as $paramName => $paramValue){
            $filename = preg_replace('/\['.$paramName.'\]/',$paramValue,$filename);
        }

        //Check whether the file exists, for each extension
        foreach($extensions as $extension){
            if((file_exists($filename.'.'.$extension))){

                //If the file does exist, make sure it does not violate the exclusion regex.
                $shouldBeExcluded = false;
                if(isset($ruleArray['exclude']))
                    foreach($ruleArray['exclude'] as $exclusionRegex){
                        if(preg_match('/'.$exclusionRegex.'/',$filename.'.'.$extension)){
                            $shouldBeExcluded = true;
                            break;
                        }
                    }

                if(!$shouldBeExcluded){
                    require $filename.'.'.$extension;
                    die();
                }

            }
        }
    }
}

//If we are here, we have to get our page without the match rules
$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$pageSettings = new IOFrame\Handlers\SettingsHandler(SETTINGS_DIR_FROM_ROOT.'/pageSettings/',$defaultSettingsParams);

//If the homepage was requested, and is defined in the settings, try to require the homepage
if( $uri == '/' || $uri == '' || $uri == $settings->getSetting('pathToRoot') || $uri == $settings->getSetting('pathToRoot').'/'
&& (gettype($pageSettings->getSetting('homepage')) == 'string') ){
    $extensions = ['php','html','htm'];
    //The homepage resides at the address defined at 'homepage'
    $url = __DIR__.'/'.$pageSettings->getSetting('homepage');
    foreach($extensions as $extension){
        if((file_exists($url.'.'.$extension))){
            require $url.'.'.$extension;
            die();
        }
    }
}

//The default is to require a 404 page - but we might have a dynamic one!
if(gettype($pageSettings->getSetting('404')) == 'string' ){
    $extensions = ['php','html','htm','png','jpg','gif'];
    //The homepage resides at the address defined at 'homepage'
    $url = __DIR__.'/'.$pageSettings->getSetting('404');
    foreach($extensions as $extension){
        if((file_exists($url.'.'.$extension))){
            require $url.'.'.$extension;
            die();
        }
    }
}
else{
    require '404.html';
    die();
}