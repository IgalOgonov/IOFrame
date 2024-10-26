<?php

/** TODO Update js/css modules/components
 *
 * Creates an IOFrame page, and all associated resources as needed, with the help of a config array
 *
 * First, familiarize yourself with how a typical IOFrame Frontend page looks (default pages are at front/ioframe/pages/cp).
 * Each page has a specific structure, and certain properties.
 *
 * Generally, a page is reached by routing. Thus by the time you're there, it is assumed the page already has everything
 * that's created in core_init.php
 *
 * Then, the typical page has a few parts:
 *
 *  a. TEMPLATE_DEFINITIONS Definitions template, defines $IOFrameJSRoot, $IOFrameCSSRoot and $IOFrameTemplateRoot
 *     * default: require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';
 *
 *  b. TEMPLATE_HEADERS_START Opens the HTML tag, typically includes ioframe_core_headers.php, and does some other system specific things.
 *     This is also where $siteConfig, $devMode, $CSS and $JS default arrays, $languageObjects, and some other things are defined.
 *     Those are used throughout the rest of the page (if your resources are local and aren't precompiled, at least).
 *     * default: require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';
 *
 *  c. TEMPLATE_RESOURCE_ARRAYS, where JS and CSS resources are pushed into arrays that were predefined in TEMPLATE_HEADERS_START.
 *     There are 2 arrays with default names ($CSS and $JS), however systems with more than one frontend root should
 *     create such arrays for each root (usually they also use a different handler, and different templates in that case).
 *     When creating a full-on page, with CSS and JS files defined, the above arrays will be automatically populated.
 *     * default: array_push($CSS, ...); array_push($JS, ...);
 *
 *  d. TEMPLATE_GET_RESOURCES Where resources from the above arrays are gotten. Depending on the system, you might
 *     want to do other stuff here, like get resource versions, or other metadata, for display later.
 *     * default: require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';
 *
 *  e. Header resources. This is by default where the title is set using TEMPLATE_TITLE, and after it, all the CSS is echo'd.
 *     Obviously, if you wish to optimize the page, you may want to change this code to only load part of the CSS, and echo the
 *     rest after the document body - but in most cases, the performance difference is minuscule enough not to care.
 *     * default: $frontEndResourceTemplateManager->printResources('CSS');
 *
 *  f. siteConfig section. Here, page information (TEMPLATE_ID, TEMPLATE_TITLE) is added to $siteConfig (which is defined inside headers_start).
 *     After that, they are loaded into the page via javascript, and the title is also set to TEMPLATE_TITLE.
 *
 *  f. TEMPLATE_HEADERS_END By default they are empty, but each system may have different uses.
 *     * default: require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_end.php';
 *
 *  i. Body > (optional - HAS_WRAPPER)Wrapper, inside which all the templates (TEMPLATE_TEMPLATES) for the modules
 *     (Vue app empty templates with only IDs, by default) are loaded to the page.
 *     * default: <body> <div class="wrapper"> %%TEMPLATE_TEMPLATES%% </div> </body>
 *
 *  j. TEMPLATE_FOOTERS_START - by default it is empty, but each system may have different uses.
 *     * default: require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';
 *
 *  k. This is where (by default) all the JS is echo'd - but you can set individual resources to be
 *     echo'd into here as well (see the JSON file).
 *     * default: $frontEndResourceTemplateManager->printResources('JS');
 *
 *  l. TEMPLATE_FOOTERS_END template - by default, this is just where ioframe_core_footers.php is called (which is empty by default).
 *
 *  Now that understand how a typical page is built, you need to understand how the JSON file (passed through $config here) helps create this.
 *  When visualizing the following, you might want to open one page for example from "cli/frontend/json".
 *  The structure of the JSON file is below, where if a key is enclosed in [square brackets] it is optional, and comments
 *  above keys of objects will be --written like this-- :
 *    {
 *       ["template"]:<string, default "pages/base.txt" - address and filename of the template relative to the root of "templates" folder>,
 *       -- Attributes of the page --
 *       ["options"]:{
 *          "override":< default false, same as this function>,
 *          "update":< default false, same as this function >
 *       }
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
 *          TEMPLATE_HEADERS_END,
 *          HAS_WRAPPER,
 *          TEMPLATE_TEMPLATES,
 *          TEMPLATE_FOOTERS_START,
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
 * @param array $errors
 * @param array $params
 *                  [REQUIRED]'templateRoot' => Absolute path to template root folder
 *                  [REQUIRED]'absPathToRoot' => Absolute path to server root
 *                  'root' => string, default null - overrides the root in the JSON file, as well as extraConfig
 *                  'extraConfig' => array, default [] - merges with, and overrides any relevant fields, in config
 *                  TODO
 *                  'override' => bool, default false - whether to allow overriding existing files
 *                  'update' => bool, default false - whether to only override existing files and not create new ones
 *
 * @return bool
 */
function createPage(array $config , array &$errors, array $params = []): bool
{

    $test = $params['test']?? false;
    $verbose = $params['verbose'] ?? $test;
    $extraConfig = $params['extraConfig'] ?? [];
    $absPathToRoot = $params['defaultParams']['absPathToRoot'];

    $config['attributes'] = $config['attributes'] ?? [];
    $config['attributes']['root'] = $config['attributes']['root'] ?? 'front/ioframe';
    $templateRoot = $absPathToRoot.'/'.( $config['attributes']['templateRoot'] ?? 'cli/frontend/templates/' );

    //First, merge config
    $config = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($config,$extraConfig);


    //Initiate all possible attributes
    //Validation - ONLY BASIC - not full correctness, and definitely not security

    if(!isset($config['attributes']) || !is_array($config['attributes'])){
        if($verbose)
            echo 'Attributes not set in JSON file, or is not an array!'.EOL;
        $errors['no-attributes'] = true;
    }

    if(!isset($config['attributes']['title']) || gettype($config['attributes']['title']) !== 'string'){
        if($verbose)
            echo 'Title not set in attributes, or is not a string!'.EOL;
        $errors['no-attribute-title'] = true;
    }

    if(!isset($config['attributes']['id']) || gettype($config['attributes']['id']) !== 'string'){
        if($verbose)
            echo 'ID not set in attributes, or is not a string!'.EOL;
        $errors['no-attribute-id'] = true;
    }

    if(!empty($errors))
        return false;

    if($verbose){
        echo EOL.'------------------------------------------'.EOL;
        echo 'Generating page '.$config['attributes']['title'].EOL;
        echo '------------------------------------------'.EOL;
    }

    //Minification Options
    if(isset($config['attributes']['minifyOptions']) && gettype($config['attributes']['minifyOptions']) !== 'array'){
        if($verbose)
            echo 'minifyOptions must be an array!'.EOL;
        $errors['minify-options-invalid'] = true;
        return false;
    }
    elseif(isset($config['attributes']['minifyOptions'])){
        foreach(['js','css'] as $type){
            if(!isset($config['attributes']['minifyOptions'][$type]))
                $config['attributes']['minifyOptions'][$type] = [
                    'name' => str_replace('/','_',$config['attributes']['id']).'_'.$type,
                    'folder' => 'min'
                ];
            elseif(gettype($config['attributes']['minifyOptions'][$type]) === 'array'){
                if(!isset($config['attributes']['minifyOptions'][$type]['name'])){
                    if($verbose)
                        echo 'minifyOptions '.$type.' must have a name!'.EOL;
                    \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['minify-object-name-missing',$type],true);
                    return false;
                }
                if(!isset($config['attributes']['minifyOptions'][$type]['folder']))
                    $config['attributes']['minifyOptions'][$type]['folder'] = 'min';
                elseif(gettype($config['attributes']['minifyOptions'][$type]['folder']) !== 'string'){
                    if($verbose)
                        echo 'minifyOptions '.$type.' folder address must have be a string!'.EOL;
                    \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['minify-object-name-invalid',$type],true);
                    return false;
                }
            }
            elseif(gettype($config['attributes']['minifyOptions'][$type]) === 'string')
                $config['attributes']['minifyOptions'][$type] = [
                    'name' => $config['attributes']['minifyOptions'][$type],
                    'folder' => 'min'
                ];
            else{
                if($verbose)
                    echo 'minifyOptions must be strings or arrays!'.EOL;
                \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['minify-option-invalid',$type],true);
                return false;
            }
        }
    }
    else{
        $config['attributes']['minifyOptions'] = null;
    }

    //Regular strings
    $optionalAttributeStrings = ['root','path','definitionsRoot','pagesRoot','definitionsFile','templatesRootVar','cssRootVar','jsRootVar',
        'cssHandler','jsHandler','cssArray','jsArray','templatesRoot','cssRoot','jsRoot'];
    foreach($optionalAttributeStrings as $key){
        if(isset($config['attributes'][$key]) && gettype($config['attributes'][$key]) !== 'string'){
            if($verbose)
                echo 'Attribute '.$key.' must be a string!'.EOL;
            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['attributes-invalid',$key],true);
            return false;
        }
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
        if(isset($config['attributes']['templates'][$key]) && gettype($config['attributes']['templates'][$key]) !== 'string'){
            if($verbose)
                echo 'Attribute template '.$key.' must be a string!'.EOL;
            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['templates-invalid',$key],true);
            return false;
        }
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
    if(!isset($config['template']) || gettype($config['template']) !== 'string'){
        if($verbose)
            echo 'Template not set, or is not a string!'.EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['template-invalid'],true);
        return false;
    }

    //Variables
    $var_regex = '[A-Z0-9_]+';
    if(isset($config['variables']) && gettype($config['variables']) !== 'array'){
        if($verbose)
            echo 'Variables must be an array if set!'.EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['variables-invalid'],true);
        return false;
    }
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

            if(!preg_match('/'.$var_regex.'/',$index)){
                if($verbose)
                    echo 'Variable '.$index.' invalid, all variables must match the regex '.$var_regex.' or be booleans!'.EOL;
                \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['variable-invalid',$index],$var_regex);
                return false;
            }
        }
    }

    //-- Items: JS, CSS and Templates --
    if(!isset($config['items']) || gettype($config['items']) !== 'array'){
        if($verbose)
            echo 'Items must be an array!'.EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['items-invalid'],true);
        return false;
    }
    if(!isset($config['items']['js']) || gettype($config['items']['js']) !== 'array'){
        if($verbose)
            echo 'Items/js must be an array!'.EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['items-js-invalid'],true);
        return false;
    }
    if(!isset($config['items']['css']) || gettype($config['items']['css']) !== 'array'){
        if($verbose)
            echo 'Items/css must be an array!'.EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['items-css-invalid'],true);
        return false;
    }
    if(!isset($config['items']['templates']) || gettype($config['items']['templates']) !== 'array'){
        if($verbose)
            echo 'Items/templates must be an array!'.EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['items-templates-invalid'],true);
        return false;
    }

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
                        if($verbose)
                            echo 'Templates must be local! Violation of template '.$index.EOL;
                        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['templates-not-local',$index],true);
                        return false;
                }
            }
            //Local items
            else{

                if($type !== 'templates'){
                    $arrType = $item['array'] ?? $config['attributes'][$type . 'Array'];
                    if(!isset($getItems[$arrType]))
                        $getItems[$arrType] = [];
                    $getItems[$arrType][] = $item['path'];
                }

                if($item['create'])
                    $createItems[$type][] = $item;

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
    $variables = $config['variables'] ?? [];
    $variables = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($variables,[
        'TEMPLATE_DEFINITIONS' =>
            'require $settings->getSetting(\'absPathToRoot\').\'front/ioframe/templates/'.$config['attributes']['definitionsFile'].
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
        'TEMPLATE_FOOTERS_END' =>
            'require $settings->getSetting(\'absPathToRoot\').'.
            $config['attributes']['templatesRootVar'].' . \''.$config['attributes']['templates']['footersEndTemplate'].
            '\';',
    ]);

    //Read template
    try{
        $template = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($templateRoot,$config['template']);
    }
    catch (\Exception $e){
        if($verbose)
            echo 'Could not read template file'.$config['template'].EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['could-not-read-template',$config['template']],$e->getMessage());
        return false;
    }
    //Generate page from template
    try{
        $pageString = \IOFrame\Util\TemplateFunctions::itemFromTemplate($template, $variables ,$params);
    }
    catch (\Exception $e){
        if($verbose)
            echo 'Could not generate file from '.$config['template'].' with variables '.json_encode($variables).EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['could-not-generate-template',$config['template']],['exception'=>$e->getMessage(),'variables'=>$variables]);
        return false;
    }
    //Create page
    $pageUrl = $absPathToRoot.($config['attributes']['root']).'/'.$config['attributes']['pagesRoot'];

    if($verbose)
        echo 'Generating page, from template '.$templateRoot.$config['template'].', to '.$pageUrl.$config['attributes']['path'].', character length: '.strlen($pageString).EOL;
    if(!$test)
        try{
            \IOFrame\Util\FileSystemFunctions::writeFileWaitMutex($pageUrl,$config['attributes']['path'],$pageString,['verbose'=>$verbose,'createFolders'=>true]);
        }
        catch (\Exception $e){
            if($verbose)
                echo 'Could not generate generate page from template to url '.$pageUrl.$config['attributes']['path'].EOL;
            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['could-not-generate-page',$config['template']],['exception'=>$e->getMessage(),'url'=>$pageUrl.$config['attributes']['path']]);
            return false;
        }

    //Create all JS, CSS and Template files that need to be created
    foreach($createItems as $itemType=>$items){
        foreach($items as $item){
            //Template
            if(!$item['template'])
                $itemString = '';
            else{
                try{
                    $template = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($templateRoot,$item['template']);
                }
                catch (\Exception $e){
                    if($verbose)
                        echo 'Could not read template for item'.$item['template'].' of type '.$itemType.EOL;
                    \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['could-not-read-item-template',$itemType,$item['template']],$e->getMessage());
                    return false;
                }
                $variables = $item['variables'] ?? [];
                //Generate item from template
                try{
                    $itemString = \IOFrame\Util\TemplateFunctions::itemFromTemplate($template, $variables ,$params);
                }
                catch (\Exception $e){
                    if($verbose)
                        echo 'Could not generate template from'.$item['template'].' of type '.$itemType.EOL;
                    \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['could-not-generate-item-template',$itemType,$item['template']],$e->getMessage());
                    return false;
                }
            }
            $itemUrl =  $absPathToRoot.
                ($item['root']?: $config['attributes']['root']).'/'.
                ($item['fileRoot']?: $config['attributes'][$itemType.'Root']);

            if($verbose)
                echo EOL.'Generating '.$itemType.( $item['template'] ? ', from template '.$templateRoot.$item['template']:'' ).
                    ', to '.$itemUrl.$item['path'].', character length: '.strlen($itemString).EOL;
            if(!$test)
                try{
                    \IOFrame\Util\FileSystemFunctions::writeFileWaitMutex($itemUrl,$item['path'],$itemString,['verbose'=>$verbose,'createFolders'=>true]);
                }
                catch (\Exception $e){
                    if($verbose)
                        echo 'Could not create file from template from'.$item['template'].' of type '.$itemType.' at '.$itemUrl.$item['path'].EOL;
                    \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['could-not-write-new-item',$itemType,$item['template']],['exception'=>$e->getMessage(),'url'=>$itemUrl.$item['path']]);
                    return false;
                }
        }
    }

    return true;

}