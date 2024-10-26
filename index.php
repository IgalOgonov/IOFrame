<?php /** @noinspection ALL */

if(!file_exists(__DIR__.'/localFiles/_installComplete')){
    $installationFileURI = explode('/',$_SERVER['SCRIPT_NAME']);
    array_pop($installationFileURI);
    array_push($installationFileURI,'_install.php');
    $redirectionAddress = '';
    $requestScheme =  empty($_SERVER['REQUEST_SCHEME'])? 'https://' : $_SERVER['REQUEST_SCHEME'].'://';
    $redirectionAddress = $requestScheme . $_SERVER['HTTP_HOST'] . implode('/',$installationFileURI);
    header('Location: ' . $redirectionAddress);
    exit();
}

require 'main/core_init.php';
require 'vendor/autoload.php';
Use IOFrame\Handlers\RouteHandler;

define('REQUEST_PASSED_THROUGH_ROUTER',true);

$pageSettings = new \IOFrame\Handlers\SettingsHandler(\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/pageSettings/',$defaultSettingsParams);

//Allow for a custom routing script at a different location. The "preRoutingScript" page setting should be the RELATIVE location
//of the custom routing script from here (framework root)
if($pageSettings->getSetting('preRoutingScript')){
    $url = __DIR__.'/'.$pageSettings->getSetting('preRoutingScript').'.php';
    if(is_file($url))
        require $url;
    unset($url);
}

$router = new AltoRouter();

//Thankfully, we have the base path created by default at installation
$router->setBasePath($settings->getSetting('pathToRoot'));

//RouteHandler stuff
$RouteHandler = new RouteHandler($settings,$defaultSettingsParams);

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
}

//Allow custom mapping via include
if($pageSettings->getSetting('preMappingScript')){
    $url = __DIR__.'/'.$pageSettings->getSetting('preMappingScript').'.php';
    if(is_file($url))
        require $url;
    unset($url);
}

//Get URI, and possible fix it
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = preg_replace('/\/\/+/','/',$uri);
$requestStringPos = strpos($uri,'?');
if($requestStringPos !== false)
    $uri = substr($uri,0,$requestStringPos);

//Match and set parameters/target if we got a match
$match = @$router->match($uri);

if( is_array($match)) {
    $routeTarget = $match['target'];
    $routeParams = $match['params'];
} else {
    $routeTarget = null;
    $routeParams = null;
}

//Get required matches from the db/cache
$matches = $RouteHandler->getMatches($matchNames);

//Allow custom matching via include
if($pageSettings->getSetting('preMatchingScript')){
    $url = __DIR__.'/'.$pageSettings->getSetting('preMatchingScript').'.php';
    if(is_file($url))
        require $url;
    unset($url);
}

//If the correct match is found, process it
if(isset($matches[$routeTarget]) && is_array($matches[$routeTarget])){
    //DB array
    $matchArray = $matches[$routeTarget];
    //Get DB extensions, or set default ones
    $extensions = ($matchArray['Extensions']!==null)? explode(',',$matchArray['Extensions']): ['php','html','htm','js','css'];
    //Edge case - API routing
    if($routeTarget === 'api'){
        $defaultAPIVersion = 'v'.($siteSettings->getSetting('apiVersion')?$siteSettings->getSetting('apiVersion'):'1');
        $originalURL = $matchArray['URL'];
        $versionURL = explode('/',$matchArray['URL']);
        array_splice($versionURL,1,0,$defaultAPIVersion);
        $versionURL = implode('/',$versionURL);
        $fallbackVersionURL = str_replace($defaultAPIVersion,'v1',$versionURL);

        $matchArray['URL'] = [
            ['include' => $versionURL, 'exclude'=>[]]
        ];
        if($defaultAPIVersion !== 'v1')
            array_push($matchArray['URL'],
                ['include' => $fallbackVersionURL, 'exclude'=>[]]
            );
        array_push($matchArray['URL'],
            ['include' => $originalURL, 'exclude'=>[]]
        );
        unset($defaultAPIVersion,$originalURL);
    }
    //If the match is a string, enclose it in a JSON object
    elseif(!\IOFrame\Util\PureUtilFunctions::is_json($matchArray['URL']))
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
        $filename = $ruleArray['include'];

        //Replace the relevant parts with route parameters.
        foreach($routeParams as $paramName => $paramValue){
            $filename = preg_replace('/\['.$paramName.'\]/',$paramValue,$filename);
        }

        //Whether this specific match allows partial URL matching
        if($matchArray['Match_Partial_URL']){
            $filename = explode('/',$filename);
            $temp = [];
            $filenames = [];
            foreach ($filename as $index => $urlPart){
                array_push($temp, $urlPart);
                array_push($filenames, __DIR__.'/'.implode('/',$temp));
            }
            $filenames = array_reverse($filenames);
        }
        else
            $filenames = [__DIR__.'/'.$filename];

        //Check whether the file exists, for each extension
        foreach ($filenames as $filename){
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
}

/*  If we had an API match but couldn't find the endpoint, it means it doesnt exist. */
if ($routeTarget === 'api'){
    \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>'No API']);
}
/*  If we are here, we have to get our page without the match rules.
    If the homepage was requested, and is defined in the settings, try to require the homepage
    If SPA app, automatically redirect every unmatched request to the homepage, and let the frontend handle invalid routes */
elseif(
    (
        ($uri === '/') ||
        ($uri === '') ||
        ($uri === $settings->getSetting('pathToRoot')) ||
        ($uri === $settings->getSetting('pathToRoot').'/') ||
        $pageSettings->getSetting('isSPA')
    ) &&
    (gettype($pageSettings->getSetting('homepage')) === 'string')
){
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

//Here, we can redirect to a custom routing script that executes AFTER the regular matches. Can be used for a different 404 page, too.
if($pageSettings->getSetting('postRoutingScript')){
    $url = __DIR__.'/'.$pageSettings->getSetting('postRoutingScript').'.php';
    if(is_file($url))
        require $url;
    unset($url);
}

//If page was not found, return the generic 404 error

\IOFrame\Util\DefaultErrorTemplatingFunctions::handleGenericHTTPError(
    $settings,
    [
        'error'=>404,
        'errorInMsg'=>false,
        'errorHTTPMsg'=>'Not Found',
        'mainMsg'=>'Page Not Found',
        'cssColor'=>'54,145,160',
        'mainFilePath'=>$pageSettings->getSetting('_templates_page_not_found')
    ]
);
