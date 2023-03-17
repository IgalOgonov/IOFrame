<?php

require_once __DIR__ . '/../../main/definitions.php';
if(!defined('helperFunctions'))
    require __DIR__.'/../../IOFrame/Util/helperFunctions.php';

/**
 * Creates an IOFrame page, and all associated resources as needed, with the help of a config array
 *
 * First, familiarize yourself with how a typical IOFrame Frontend page looks (default pages are at front/ioframe/pages/cp).
 * Each page has a specific structure, and certain properties.
 *
 * Generally, a page is reached by routing. Thus by the time you're there, it is assumed the page already has everything
 * that's created in coreInit.php
 *
 * Then, the typical page has a few parts:
 *
 *  a. Definitions template
 *
 *  b. headers_start template - typically includes ioframe_core_headers.php, and does some other system specific things.
 *     This is also where CSS and JS resource handlers are defined - those are used throughout the rest of the page (if your
 *     resources are local and aren't precompiled, at least).
 *
 *  c. Resource arrays section, where JS and CSS resources are pushed into arrays that were predefined in headers_start.
 *     There are 2 arrays with default names, however if you define (in the JSON file, explained a bit later) that specific
 *     resources use a different array (usually they also use a different handler in that case), there can be more than 2.
 *
 *  d. headers_get_resources template - where resources from the above arrays are gotten. Depending on the system, you might
 *     want to do other stuff here, like get resource versions, or other metadata, for display later.
 *
 *  e. Header resources. This is where (by default) all the CSS is echo'd - but you can set individual resources to be
 *     echo'd into here as well (see the JSON file).
 *
 *  f. Adding specific page information to $siteConfig (which is meant to be a dycamic page configuration that's gotten earlier,
 *     but is by default an empty object).
 *
 *  g. Giving the frontend siteConfig (document.siteConfig).
 *
 *  h. headers_end template - by default, this is just where the global site CSS is outputted, but each system may have
 *     different uses.
 *
 *  i. Wrapper, inside which all the templates  for the modules (Vue app templates by default) on the page are.
 *
 *  j. footers_start template - by default, this is just where the common mixins JS file is outputted, but each system may have
 *     different uses.
 *
 *  k. Footer resources. This is where (by default) all the JS is echo'd - but you can set individual resources to be
 *     echo'd into here as well (see the JSON file).
 *
 *  l. footers_end template - by default, this is just where ioframe_core_footers.php is called (which is empty by default).
 *
 *  Now that understand how a typical page is built, you need to understand how the JSON file (passed through $config here) helps create this.
 *  When visualizing the following, you might want to open one page for example from the default pages in another window,
 *  as well as "example.json" in the json folder.
 *  The structure of the JSON file is bellow, where if a key is enclosed in [square brackets] it is optional, and comments
 *  above keys of objects will be --written like this-- :
 *    {
 *       ["template"]:<string, default "pages/base.txt" - address and filename of the template relative to the root of "templates" folder>,
 *       -- Attributes of the page --
 *       "attributes":{
 *           "title":<string, readable title of the page>,
 *           "id":<string, id of the page - should match either the filename, or relative path from pages root + filename (depends on your system)>,
 *           ["path"]:<string, id of the page - should match either the filename, or relative path from pages root + filename (depends on your system)>,
 *           -- Options for combining minified local JS/CSS resources, and where to output minimized is placed;
 *              By default, each local file is minimized on the fly separably, and placed in the same folder as the original.
 *              Each option inside can also be a string, then it's the name, and the folder is 'min' --
 *           ['attributes']["minifyOptions"]:{ TODO
 *               ["js"]: {
 *                   "name":<string, name of the file all the JS files will be minified into>,
 *                   ["folder"]:<string, default 'min' - address the minified file will be placed into (relative to JS folder root)>
 *               },
 *               ["css"]: Same as JS
 *           },
 *           ["root"]:<string, default "front/ioframe/". frontend root>,
 *           ["definitionsRoot"]:<string, default "templates/". folder (relative to frontend root) where the templates definition file resides>,
 *           ["pagesRoot"]:<string, default "pages/". folder (relative to frontend root) where the templates definition file resides>,
 *           ["definitionsFile"]:<string, default "definitions.php". Name of the definition file for above address>,
 *           ["templatesRootVar"]:<string, default "$IOFrameTemplateRoot". Name of the variable that was defined in definitions.php
 *                                as the Templates folder root, relative to "root">,
 *           ["cssRootVar"]::<string, default "$IOFrameCSSRoot". Name of the variable that was defined in definitions.php
 *                                as the CSS folder root, relative to "root">,
 *           ["jsRootVar"]::<string, default "$IOFrameJSRoot". Name of the variable that was defined in definitions.php
 *                                as the JS folder root, relative to "root">,
 *           ["templatesRoot"]:<string, default "templates/".>,
 *           ["cssRoot"]::<string, default "css/".,
 *           ["jsRoot"]::<string, default "js/".>,
 *           ["cssHandler"]::<string, default "$CSSResources". Name of the ResourceHandler instance that handles CSS files>,
 *           ["jsHandler"]::<string, default "$JSResources". Name of the ResourceHandler instance that handles JS files>,
 *           ["cssArray"]::<string, default "$CSS". Name of an array variable that by default stores all the CSS files for the ResourceHandler to get>,
 *           ["jsArray"]::<string, default "$JS". Name of an array variable that by default stores all the JS files for the ResourceHandler to get>,
 *           ["templates"]:{
 *               ["headersStartTemplate"]:<string/object, default "headers_start.php". TODO If passed as an object, its of the form:
 *                   {
 *                       ["name"]:<string, name of the template file, defaults to the above value>,
 *                       "root":<string, relative address from frontend root to be used instead of attributes.templatesRootVar>,
 *                       OR
 *                       "rootVar":<string, variable name to be used instead of attributes.templatesRootVar - overrides root>,
 *                   }
 *               >,
 *               ["headersGetResourcesTemplate"]:<string/object, default "headers_get_resources.php">,
 *               ["headersEndTemplate"]:<string/object, default "headers_end.php">,
 *               ["footersStartTemplate"]:<string/object, default "footers_start.php">,
 *               ["footersEndTemplate"]:<string/object, default "footers_end.php">,
 *           }
 *       },
 *      "template":<string, template of the page itself, relative to templates root>,
 *       -- Variables, to apply to the page template, read templateFunctions and see the specific template.
 *          Must be UNDERSCORE_SEPARATED_UPPER_CASE_LETTERS.
 *          Overridden by the following hardcoded variables:
 *          TEMPLATE_DEFINITIONS,
 *          TEMPLATE_HEADERS_START,
 *          TEMPLATE_RESOURCE_ARRAYS,
 *          TEMPLATE_GET_RESOURCES,
 *          TEMPLATE_ID,
 *          TEMPLATE_TITLE,
 *          TEMPLATE_HEADER_RESOURCES,
 *          TEMPLATE_HEADERS_END,
 *          HAS_WRAPPER,
 *          TEMPLATE_TEMPLATES,
 *          TEMPLATE_FOOTERS_START,
 *          TEMPLATE_FOOTER_RESOURCES,
 *          TEMPLATE_FOOTERS_END
 *          (others may be added later)
 *       --
 *       ["variables"]:{
 *       }
 *       "items":{
 *           -- Array of strings or Objects that represent your resources --
 *           "js"/"css"/"templates":[
 *               <string, address of an existing local resource relative to JS/CSS/Templates root>,
 *               OR
 *               <ANY object of the form:
 *                   {
 *                       "path":<string, address of a local resource relative to JS/CSS/Templates root>,
 *                       ["pageLocation"]: <string, "footers" or "headers" - where you want the item to appear. "headers"
 *                                          default for CSS, "footers" default for JS. Templates are always placed inside a predefined slot in between>
 *                       ["rootVar"]: <string, defaults to "attributes"."(js/css/templates)RootVar" - used to override it for a specific item>
 *                       ["root"]: <string, defaults to "attributes"."root" - used to override it for a specific item>
 *                       ["fileRoot"]: <string, defaults to "attributes"."(js/css/templates)Root" - used to override it for a specific item>
 *                       ["handler"]: <string, defaults to "attributes"."(js/css)Handler" - used to override it for a specific item>
 *                       ["array"]: <string, defaults to "attributes"."(js/css)Array" - used to override it for a specific item>
 *                       ["create"]:<bool, default false - whether to create the resource if it doesn't exist>,
 *                       ["template"]:<string, template of the page itself, relative to templates root - defaults to an empty file>,
 *                       -- Same as the params for the page itself, but for this resource template --
 *                       "params":{
 *                       }
 *                   }
 *               >
 *               OR
 *               <JS/CSS object of the form:
 *                   {
 *                   "local":false,
 *                   "path":<string, absolute path such as "https://www.example.com/example.css">,
 *                   ["pageLocation"]: same as before
 *                   }
 *               >
 *       }
 *   }
 *
 * @param array $config Explained above
 *
 * @param array $params
 *                  [REQUIRED]'templateRoot' => Absolute path to template root folder
 *                  [REQUIRED]'absPathToRoot' => Absolute path to server root
 *                  'root' => string, default null - overrides the root in the JSON file, as well as extraConfig
 *                  'extraConfig' => array, default [] - merges with, and overrides any relevant fields, in config
 *                  'override' => bool, default false - whether to allow overriding existing files
 *                  'update' => bool, default false - whether to only override existing files and not create new ones
 *
 * @throws \Exception In cases:
 *                      Illegal attribute of item (for example, a template having "local":false)
 *                      Missing required attribute (for example, a template missing "path")
 * @return string
 */
function createPage(array $config ,$params = []){

    $test = $params['test']?? false;
    $verbose = isset($params['verbose'])?
        $params['verbose'] : $test ?
            true : false;
    $root = isset($params['root'])? $params['root'] : null;
    $extraConfig = isset($params['extraConfig'])? $params['extraConfig'] : [];
    $override = isset($params['override'])? $params['override'] : false;
    $update = isset($params['update'])? $params['update'] : false;

    if(isset($params['templateRoot']))
        $templateRoot = $params['templateRoot'];
    else
        throw new Exception('templateRoot url must be provided!');

    if(isset($params['absPathToRoot']))
        $absPathToRoot = $params['absPathToRoot'];
    else
        throw new Exception('absPathToRoot url must be provided!');


    $FileHandler = new IOFrame\Handlers\FileHandler();

    //First, merge config
    $config = IOFrame\Util\array_merge_recursive_distinct($config,$extraConfig);

    //Override root if needed
    if($root !== null)
        $config['attributes']['root'] = $root;

    //Initiate all possible attributes
    //Validation - ONLY BASIC - not full correctness, and definitely not security

    if(!isset($config['attributes']) || !is_array($config['attributes']))
        throw new Exception('Attributes not set in JSON file, or is not an array!');

    if(!isset($config['attributes']['title']) || gettype($config['attributes']['title']) !== 'string')
        throw new Exception('Title not set in attributes, or is not a string!');

    if(!isset($config['attributes']['id']) || gettype($config['attributes']['id']) !== 'string')
        throw new Exception('ID not set in attributes, or is not a string!');

    if($verbose){
        echo EOL.'------------------------------------------'.EOL;
        echo 'Generating page '.$config['attributes']['title'].EOL;
        echo '------------------------------------------'.EOL;
    }

    //Minification Options
    if(isset($config['attributes']['minifyOptions']) && gettype($config['attributes']['minifyOptions']) !== 'array')
        throw new Exception('minifyOptions must be an array!');
    elseif(isset($config['attributes']['minifyOptions'])){
        foreach(['js','css'] as $type){
            if(!isset($config['attributes']['minifyOptions'][$type]))
                $config['attributes']['minifyOptions'][$type] = [
                    'name' => str_replace('/','_',$config['attributes']['id']).'_'.$type,
                    'folder' => 'min'
                ];
            elseif(gettype($config['attributes']['minifyOptions'][$type]) === 'array'){
                if(!isset($config['attributes']['minifyOptions'][$type]['name']))
                    throw new Exception('minifyOptions '.$type.' must have a name!');
                if(!isset($config['attributes']['minifyOptions'][$type]['folder']))
                    $config['attributes']['minifyOptions'][$type]['folder'] = 'min';
                elseif(gettype($config['attributes']['minifyOptions'][$type]['folder']) !== 'string')
                    throw new Exception('minifyOptions '.$type.' folder address must have be a string!');
            }
            elseif(gettype($config['attributes']['minifyOptions'][$type]) === 'string')
                $config['attributes']['minifyOptions'][$type] = [
                    'name' => $config['attributes']['minifyOptions'][$type],
                    'folder' => 'min'
                ];
            else
                throw new Exception('minifyOptions must be strings or arrays!');
        }
    }
    else{
        $config['attributes']['minifyOptions'] = null;
    }

    //Regular strings
    $optionalAttributeStrings = ['root','path','definitionsRoot','pagesRoot','definitionsFile','templatesRootVar','cssRootVar','jsRootVar',
        'cssHandler','jsHandler','cssArray','jsArray','templatesRoot','cssRoot','jsRoot'];
    foreach($optionalAttributeStrings as $key){
        if(isset($config['attributes'][$key]) && gettype($config['attributes'][$key]) !== 'string')
            throw new Exception('Attribute '.$key.' must be a string!');
        elseif(!isset($config['attributes'][$key])){
            switch($key){
                case 'root':
                    $config['attributes'][$key] = 'front/ioframe/';
                    break;
                case 'path':
                    $config['attributes'][$key] = $config['attributes']['id'].'.php';
                    break;
                case 'definitionsRoot':
                    $config['attributes'][$key] = 'templates/';
                    break;
                case 'pagesRoot':
                    $config['attributes'][$key] = 'pages/';
                    break;
                case 'definitionsFile':
                    $config['attributes'][$key] = 'definitions.php';
                    break;
                case 'templatesRootVar':
                    $config['attributes'][$key] = '$IOFrameTemplateRoot';
                    break;
                case 'cssRootVar':
                    $config['attributes'][$key] = '$IOFrameCSSRoot';
                    break;
                case 'jsRootVar':
                    $config['attributes'][$key] = '$IOFrameJSRoot';
                    break;
                case 'templatesRoot':
                    $config['attributes'][$key] = 'templates/';
                    break;
                case 'cssRoot':
                    $config['attributes'][$key] = 'css/';
                    break;
                case 'jsRoot':
                    $config['attributes'][$key] = 'js/';
                    break;
                case 'cssHandler':
                    $config['attributes'][$key] = '$CSSResources';
                    break;
                case 'jsHandler':
                    $config['attributes'][$key] = '$JSResources';
                    break;
                case 'cssArray':
                    $config['attributes'][$key] = '$CSS';
                    break;
                case 'jsArray':
                    $config['attributes'][$key] = '$JS';
                    break;
            }
        }
    }

    //default templates
    $optionalDefaultTemplates = ['headersStartTemplate','headersGetResourcesTemplate','headersEndTemplate','footersStartTemplate','footersEndTemplate'];
    foreach($optionalDefaultTemplates as $key){
        if(isset($config['attributes']['templates'][$key]) && gettype($config['attributes']['templates'][$key]) !== 'string')
            throw new Exception('Attribute template '.$key.' must be a string!');
        elseif(!isset($config['attributes']['templates'][$key])){
            switch($key){
                case 'headersStartTemplate':
                    $config['attributes']['templates'][$key] = 'headers_start.php';
                    break;
                case 'headersGetResourcesTemplate':
                    $config['attributes']['templates'][$key] = 'headers_get_resources.php';
                    break;
                case 'headersEndTemplate':
                    $config['attributes']['templates'][$key] = 'headers_end.php';
                    break;
                case 'footersStartTemplate':
                    $config['attributes']['templates'][$key] = 'footers_start.php';
                    break;
                case 'footersEndTemplate':
                    $config['attributes']['templates'][$key] = 'footers_end.php';
                    break;
            }
        }
    }

    //main template
    if(!isset($config['template']) || gettype($config['template']) !== 'string')
        throw new Exception('Template not set, or is not a string!');

    //Variables
    $var_regex = '[A-Z0-9_]+';
    if(isset($config['variables']) && gettype($config['variables']) !== 'array')
        throw new Exception('Variables must be an array if set!');
    elseif(isset($config['variables'])){
        foreach($config['variables'] as $index => $value){
            if($value === 1 || $value === true){
                $config['variables'][$index] = true;
                continue;
            }
            if($value === 0 || $value === false){
                unset($config['variables'][$index]);
                continue;
            }

            if(!preg_match('/'.$var_regex.'/',$index))
                throw new Exception('Variable '.$index.' invalid, all variables must match the regex '.$var_regex.' or be booleans!');
        }
    }

    //-- Items: JS, CSS and Templates --
    if(!isset($config['items']) || gettype($config['items']) !== 'array')
        throw new Exception('Items must be an array!');
    if(!isset($config['items']['js']) || gettype($config['items']['js']) !== 'array')
        throw new Exception('Items/js must be an array!');
    if(!isset($config['items']['css']) || gettype($config['items']['css']) !== 'array')
        throw new Exception('Items/css must be an array!');
    if(!isset($config['items']['templates']) || gettype($config['items']['templates']) !== 'array')
        throw new Exception('Items/templates must be an array!');

    //Those templates are dynamic, and based on the items
    $header = '';
    $footer = '';
    $templates = '';
    $arrays = '';

    //Handle creation of items - JS, CSS and Templates
    $createItems = [
        'js'=>[],
        'css'=>[],
        'templates'=>[]
    ];
    $getItems = [
    ];

    foreach(['css','js','templates'] as $type){
        //Parse all relevant items
        foreach($config['items'][$type] as $index => $item){
            //Strings
            if(gettype($item) === 'string'){
                $item = [
                  'path'=>$item
                ];
            }

            //Defaults
            if(!isset($item['pageLocation']))
                $item['pageLocation'] = $type === 'js'? 'footers' : 'headers';

            if(!isset($item['rootVar']))
                $item['rootVar'] = $config['attributes'][$type.'RootVar'];

            if(!isset($item['handler']) &&  $type !== 'templates')
                $item['handler'] = $config['attributes'][$type.'Handler'];

            if(!isset($item['array']) &&  $type !== 'templates')
                $item['array'] = $config['attributes'][$type.'Array'];

            //Mete information for creation
            if(!isset($item['create']))
                $item['create'] = false;

            if(!isset($item['template']))
                $item['template'] = null;

            if(!isset($item['fileRoot']))
                $item['fileRoot'] = null;

            if(!isset($item['root']))
                $item['root'] = null;

            //String to add
            $string = '';
            if(!defined('TAB'))
                define('TAB','    ');

            //Absolute path
            if(isset($item['local']) && $item['local'] === false){
                switch($type){
                    case 'css':
                        $string = 'echo \'<link rel="stylesheet" href="'.$item['path'].'">\';';
                        break;
                    case 'js':
                        $string = 'echo \'<script src="'.$item['path'].'"></script>\';';
                        break;
                    case 'templates':
                        throw new Exception('Templates must be local! Violation of template '.$index.EOL);
                }
            }
            //Local items
            else{

                if($type !== 'templates'){
                    $arrType = isset($item['array'])?  $item['array'] : $config['attributes'][$type.'Array'];
                    if(!isset($getItems[$arrType]))
                        $getItems[$arrType] = [];
                    array_push($getItems[$arrType],$item['path']);
                }

                if($item['create'])
                    array_push($createItems[$type],$item);

                switch($type){
                    case 'css':
                        $string = 'echo \'<link rel="stylesheet" href="\' . $dirToRoot . '.
                            ($item['rootVar'] === false?  $item['rootVar'] : $config['attributes']['cssRootVar']).
                            ' . '.
                            ($item['handler'] === false?  $item['handler'] : $config['attributes']['cssHandler']).
                            '[\''.$item['path'].'\'][\'relativeAddress\'] . \'"">\';';
                        break;
                    case 'js':
                        $footer .= 'echo \'<script src="\'.$dirToRoot.'.
                            ($item['rootVar'] === false?  $item['rootVar'] : $config['attributes']['jsRootVar']).
                            ' . '.
                            ($item['handler'] === false?  $item['handler'] : $config['attributes']['jsHandler']).
                            '[\''.$item['path'].'\'][\'relativeAddress\'].\'"></script>\';';
                        break;
                    case 'templates':
                        $templates .= '<?php require $settings->getSetting(\'absPathToRoot\').'.
                            ($item['rootVar'] === false?  $item['rootVar'] : $config['attributes']['templatesRootVar']).
                            '.\''.$item['path'].'\';?>';
                        break;
                }

            }

            if($type !== 'templates')
                ($item['pageLocation'] == 'headers')?
                    $header .= $string.EOL_FILE : $footer .= $string.EOL_FILE;
            else
                $templates .= $string.EOL_FILE;
        }

    }
    //Generate js and css arrays
    foreach($getItems as $arrType => $items){
        $string = 'array_push('.$arrType.', ';
        foreach($items as $item)
            $string .='\''.$item.'\', ';
        $string = substr($string,0,strlen($string)-2);
        $string .= ');';
        $arrays .= $string.EOL_FILE;
    }

    //Get provided variables and merge them with the hardcoded ones
    $variables = isset($config['variables'])? $config['variables'] : [];
    $variables = IOFrame\Util\array_merge_recursive_distinct($variables,[
        'TEMPLATE_DEFINITIONS' =>
            'require $settings->getSetting(\'absPathToRoot\').\''.
            $config['attributes']['root'].$config['attributes']['definitionsRoot'].$config['attributes']['definitionsFile'].
            '\';',
        'TEMPLATE_HEADERS_START' =>
            'require $settings->getSetting(\'absPathToRoot\').'.
            $config['attributes']['templatesRootVar'].' . \''.$config['attributes']['templates']['headersStartTemplate'].
            '\';',
        'TEMPLATE_RESOURCE_ARRAYS' => $arrays,
        'TEMPLATE_GET_RESOURCES' =>
            'require $settings->getSetting(\'absPathToRoot\').'.
            $config['attributes']['templatesRootVar'].' . \''.$config['attributes']['templates']['headersGetResourcesTemplate'].
            '\';',
        'TEMPLATE_ID' => $config['attributes']['id'],
        'TEMPLATE_TITLE' => $config['attributes']['title'],
        //'TEMPLATE_HEADER_RESOURCES' => $header,
        'TEMPLATE_HEADERS_END' =>
            'require $settings->getSetting(\'absPathToRoot\').'.
            $config['attributes']['templatesRootVar'].' . \''.$config['attributes']['templates']['headersEndTemplate'].
            '\';',
        'HAS_WRAPPER' => true,
        'TEMPLATE_TEMPLATES' => $templates,
        'TEMPLATE_FOOTERS_START' =>
            'require $settings->getSetting(\'absPathToRoot\').'.
            $config['attributes']['templatesRootVar'].' . \''.$config['attributes']['templates']['footersStartTemplate'].
            '\';',
        //'TEMPLATE_FOOTER_RESOURCES' => $footer,
        'TEMPLATE_FOOTERS_END' =>
            'require $settings->getSetting(\'absPathToRoot\').'.
            $config['attributes']['templatesRootVar'].' . \''.$config['attributes']['templates']['footersEndTemplate'].
            '\';',
    ]);

    //Read template
    $template = $FileHandler->readFileWaitMutex($templateRoot,$config['template'],[]);
    //Generate page from template
    $pageString = \IOFrame\Util\itemFromTemplate($template, $variables ,$params);
    //Create page
    $pageUrl = $absPathToRoot.$config['attributes']['root'].$config['attributes']['pagesRoot'];

    if($verbose)
        echo 'Generating page, from template '.$templateRoot.$config['template'].', to '.$pageUrl.$config['attributes']['path'].', character length: '.strlen($pageString).EOL;
    if(!$test)
        $FileHandler->writeFileWaitMutex($pageUrl,$config['attributes']['path'],$pageString,['verbose'=>$verbose,'createFolders'=>true]);

    //Create all JS, CSS and Template files that need to be created
    foreach($createItems as $itemType=>$items){
        foreach($items as $item){
            //Template
            if(!$item['template'])
                $itemString = '';
            else{
                $template = $FileHandler->readFileWaitMutex($templateRoot,$item['template'],[]);
                $variables = isset($item['variables'])? $item['variables'] : [];
                //Generate item from template
                $itemString = \IOFrame\Util\itemFromTemplate($template, $variables ,$params);
            }
            $itemUrl =  $absPathToRoot.
                ($item['root']? $item['root'] : $config['attributes']['root']).
                ($item['fileRoot']? $item['fileRoot'] : $config['attributes'][$itemType.'Root']);

            if($verbose)
                echo EOL.'Generating '.$itemType.( $item['template'] ? ', from template '.$templateRoot.$item['template']:'' ).
                    ', to '.$itemUrl.$item['path'].', character length: '.strlen($itemString).EOL;
            if(!$test)
                $FileHandler->writeFileWaitMutex($itemUrl,$item['path'],$itemString,['verbose'=>$verbose,'createFolders'=>true]);
        }
    }


}