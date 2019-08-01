<?php
namespace IOFrame\Handlers{
    use IOFrame;
    use MatthiasMullie\Minify;
    define('FrontEndResourceHandler',true);
    if(!defined('ResourceHandler'))
        require 'ResourceHandler.php';
    if(!defined('helperFunctions'))
        require __DIR__.'/../Util/helperFunctions.php';

    /*  This class manages front-end resources.
     *  Specifically, JS and CSS (for now).
     *  Also used for optional on-the-fly minification.
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class FrontEndResourceHandler extends ResourceHandler
    {

        /** Standard constructor
         *
         * @param SettingsHandler $settings The standard settings object
         * @param array $params - All parameters share the name/type of the class variables
         * */
        function __construct(SettingsHandler $settings, array $params = []){

            parent::__construct($settings,$params);

            if(isset($params['FileHandler']))
                $this->FileHandler = $params['FileHandler'];
            else
                $this->FileHandler = new FileHandler();
        }

        /** Gets all resources available, by type.
         * Can also get by specific addresses
         *
         * @param array $addresses defaults to [], if not empty will only get specific resources by addresses
         * @param array $type 'js' or 'css'
         * @param array $params of the form:
         *
         *          --- From Parent ---
         *
         *          <getResources Parameters>
         *
         *          -- Special to this Handler ---
         *
         *          'rootFolder'        - string, Root folder for the local resources (relative to server root!).
         *                                Defaults to resource settings 'jsPathLocal' and 'cssPathLocal'
         *          'existingAddresses' - Array, potential existing addresses if we already got them earlier.
         *
         *          'updateDBIfExists'  - bool, default true - Whether to update the db when a local file exists that isn't
         *                                in the DB.
         *          'includeChildFiles'   -  bool, default true - If a resource is a folder, whether to include its children
         *                                 that are NOT FOLDERS (still has to be of the correct extension).
         *          'includeChildFolders'    -  bool, default false - If a resource is a folder, whether to include its children
         *                                  THAT ARE FOLDERS.
         *          'includeSubFolders' -  bool, default false - If a resource is a folder, whether to include its children,
         *                                  FOLDERS AND FILES, recursively.
         *          'forceMinify'       - bool, default false - will attempt to minify local js files. Minified file
         *                                  is always saved with a 'min.js' prefix instead of '.js'.
         *          'minifyName'        - string, default '' - If set, will group all minified files under this name,
         *                                and returns the url of the newly minified resource.
         *          'minifyToFolder'    - string, default '' - If set, will place all minified js files into a subfolder
         *                                named after this setting.
         *                                If minifyName isn't set, offset is relative to each file (or folder).
         *                                Else, offset is relative to the JS root set in settings.
         *          'scss'              - bool, default true - whether to compile scss to css on the fly.
         *
         * @returns array Array of the form:
         *      [
         *       <Address> =>   [
         *                                              'address' => Absolute address.
         *                                              'relativeAddress' => Relative address, or '' if resource is not local.
         *                                              'folder' => bool, whether the file is a folder
         *                                              'meta' => string, possible meta information about resource
         *                                              'lastChanged' => int, unix timestamp of the local file
         *                        ],
         *      ...
         *      ]
         *      *important note - identifier names are by default relative addresses from the relevant
         *      setting root.
         *      For example, if the file is a JS file address is <$serverRoot>/front/ioframe/js/test folder/test.js,
         *      the identifier is "test folder/test.js" (always 'address'.'name').
         *      What keeps different files in similar addresses unique is the file extension.
         */
        protected function getFrontendResources(array $addresses = [], string $type, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            //If we are getting all addresses, enforce restrictions
            if($addresses == []){
                $params['ignoreLocal'] = true;
                $params['includeChildFiles'] = false;
                $params['includeChildFolders'] = false;
                $params['includeSubFolders'] = false;
                $params['updateDBIfExists'] = false;
                $params['forceMinify'] = false;
                $params['updateCache'] = false;
                $params['useCache'] = false;
            }

            if(isset($params['rootFolder']))
                $rootFolder = $this->settings->getSetting('absPathToRoot').$params['rootFolder'];
            else{
                if($type === 'js')
                    $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
                elseif($type === 'css')
                    $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('cssPathLocal');
                else
                    $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('imagePathLocal');
            }

            $updateDBIfExists = isset($params['updateDBIfExists'])? $params['updateDBIfExists'] : true;

            $includeChildFiles = isset($params['includeChildFiles'])? $params['includeChildFiles'] : true;

            $includeChildFolders = isset($params['includeChildFolders'])? $params['includeChildFolders'] : false;

            $includeSubFolders = isset($params['includeSubFolders'])? $params['includeSubFolders'] : false;

            $minifyName = isset($params['minifyName'])? $params['minifyName'] : false;

            $minifyToFolder = isset($params['minifyToFolder'])? $params['minifyToFolder'] : '';

            if(isset($params['forceMinify']) && ($type === 'js' || $type === 'css'))
                $forceMinify = $params['forceMinify'];
            else
                if($type === 'js')
                    $forceMinify = $this->resourceSettings->getSetting('autoMinifyJS') == 1;
                elseif($type === 'css')
                    $forceMinify = $this->resourceSettings->getSetting('autoMinifyCSS') == 1;
                else
                    $forceMinify = false;
            $scss = isset($params['scss'])? $params['scss'] : true;

            $resources =  isset($params['existingAddresses'])?
                $params['existingAddresses'] : $this->getResources($addresses,$type,$params);

            //Get all local resources in addition to the DB - but do not modify the params!
            if($addresses == []){
                $resources = array_merge($resources,[''=>1]);
                $includeChildFiles = true;
                $includeChildFolders = true;
            }


            //What we return
            $resourcesToReturn = [];
            //What we update the DB with
            $resourcesToAdd = [];
            $existing = [];
            //In case we need to minify all resources under one name, this is it.
            $resourcesToMinify = [];

            foreach($resources as $address=>$resource){
                //Get expected local file locations
                $resourcePath = explode('/',$address);
                $resourceName = array_pop($resourcePath);
                $resourcePath = $rootFolder.implode('/',$resourcePath);
                if($resourcePath[-1] === '/')
                    $resourcePath = substr($resourcePath,0,-1);
                $fullAddress = ($addresses !== [])? $resourcePath.'/'.$resourceName : substr($rootFolder,0,-1);
                $isDir = is_dir($fullAddress);
                $isFile = is_file($fullAddress);
                $resourceNameTemp = explode('.',$resourceName);
                $resourceExtension = $isFile? array_pop($resourceNameTemp) : '';
                $newAddress = '';

                //Make sure the type matches the extension in case of js/css
                if( $isFile && ($type !== $resourceExtension)  && ($type !== 'img') ){
                    //In case of a CSS file that's an SCSS file, compile it to CSS on the fly
                    if($type === 'css' && $resourceExtension === 'scss' && $scss){
                        $compilation = $this->compileSCSS($address,$params);
                        $newAddress = $compilation['newName'];
                        $fullAddress = $compilation['newAddress'];
                        if(!$fullAddress)
                            continue;
                    }
                    //Else just continue
                    else
                        continue;
                }

                //Only deal with locally existing resources - but update DB for them if they do
                if(!is_array($resource)){
                    //Has to be either a directory or a file.
                    if(!$isDir && !$isFile)
                        continue;
                    $resource = [];
                    //If we are dealing with an SCSS the address is different
                    if($type === 'css' && $resourceExtension === 'scss')
                        $resource['Address'] = $newAddress;
                    else
                        $resource['Address'] = $address;
                    $fullAddress = ($addresses !== [])? $rootFolder.$resource['Address'] : substr($rootFolder,0,-1);
                    $resource['Resource_Local'] = true;
                    $resource['Minified_Version'] = false;
                    $resource['Text_Content'] = null;
                    $existing[$address] = 1;
                    //Add the resource if requested
                    if($updateDBIfExists){
                        array_push($resourcesToAdd,[$address]);
                    }
                }

                //If the file was a directory, add all of its children.
                if($isDir && $includeChildFiles){
                    $contents = scandir($fullAddress);
                    $moreResourcesToReturn = [];
                    foreach($contents as $content){
                        //Dont add this trash
                        if($content === '.' || $content === '..')
                            continue;
                        //Add a file, or a folder if $includeSubFolders is true - also, include subfolders at root level
                        if(is_file($fullAddress.'/'.$content) || $includeChildFolders){
                            if(!isset($resources[$address.'/'.$content]))
                                if($address!=='')
                                    array_push($moreResourcesToReturn,$address.'/'.$content);
                                else
                                    array_push($moreResourcesToReturn,$content);
                        }
                    }
                    //Handle recursive return
                    $folderParams = $params;
                    if(!$includeSubFolders){
                        $folderParams['includeChildFiles'] = false;
                        $folderParams['includeChildFolders'] = false;
                    }
                    else{
                        $folderParams['includeChildFiles'] = true;
                        $folderParams['includeChildFolders'] = true;
                    }
                    //Add all the extra resources to return
                    $resourcesToReturn = array_merge(
                        $resourcesToReturn,
                        $this->getFrontendResources($moreResourcesToReturn,$type,$folderParams)
                    );
                }

                //If the resource isn't local, just return it as is.
                if(!$resource['Resource_Local']){
                    $resourcesToReturn[$address] = [
                        'address' => $resource['Address'],
                        'relativeAddress' => '',
                        'folder' => false,
                        'meta' =>  $resource['Text_Content'],
                        'lastChanged' => $resource['Last_Changed']
                    ];
                    if($verbose)
                        echo 'Returning remote resource '.$address.' as is!'.EOL;
                    continue;
                }

                $changeTime = 0;
                //Minify or add to minified list
                if($forceMinify && !($resource['Minified_Version'] || $isDir)){
                    if($minifyName){
                        if($newAddress == '')
                            array_push($resourcesToMinify,$address);
                        else
                            array_push($resourcesToMinify,$newAddress);

                    }
                    else{
                        if($newAddress == '')
                            $minified = $this->minifyFrontendResource($address,$type,$params);
                        else
                            $minified = $this->minifyFrontendResource($newAddress,$type,$params);

                        if(!is_array($minified))
                            continue;
                        else{
                            $fullAddress = $minified['address'];
                            $changeTime = $minified['changeTime'];
                        }
                    }
                }
                else{
                    //Update the file change time if requested, plus check that the file exists
                    if($changeTime == 0)
                        $changeTime = @filemtime($fullAddress);

                    if(!$changeTime){
                        if($verbose)
                            echo 'File '.$fullAddress.' does not exist'.EOL;
                        continue;
                    }
                }

                //If we are not minifying all under one name, return the resource
                if(!($forceMinify && $minifyName)){

                    $resourcesToReturn[$address] = [
                        'address' => $fullAddress,
                        'relativeAddress' => substr($fullAddress,strlen($rootFolder)),
                        'folder' => $isDir,
                        'meta' =>  $resource['Text_Content'],
                        'lastChanged' => $changeTime
                    ];
                }
            }

            //If we are here, it means we are minifying multiple files into one and returning it
            if($resourcesToMinify!=[]){

                $minified = $this->minifyFrontendResources($resourcesToMinify,$type,$params)[$minifyName];

                //Only return anything if there is something to return (aka resources were minified)
                if(is_array($minified)){

                    $fullAddress = $minified['address'];
                    $changeTime = $minified['changeTime'];

                    $resourcesToReturn[$minifyName] = [
                        'address' => $fullAddress,
                        'relativeAddress' => substr($fullAddress,strlen($rootFolder)),
                        'folder' => false,
                        'meta' =>  null,
                        'lastChanged' => $changeTime
                    ];

                }

            }

            //If there are any new resources to add to the DB, do it now
            if($resourcesToAdd!=[])
                $this->setResources($resourcesToAdd,$type,array_merge($params, ['existing'=>$existing]));

            return $resourcesToReturn;
        }

        /** Moves a frontend resource, then updates the DB.
         * Can also get by specific addresses
         *
         * @param string $address original address
         * @param string $dest destination address
         * @param array $type 'js' or 'css'
         * @param array $params
         *          'updateDBIfExists'  - bool, default true - Whether to update the db when a local file exists that isn't
         *                                in the DB.
         *          'local'             - bool, default false - only does the operation locally, ignoring the db
         * @returns int Code of the form:
         *         -1 - Could not connect to db
         *          0 - All good
         */
        function moveFrontendResourceFile(string $address, string $dest, string $type, array $params = []){
            return $this->moveFrontendResourceFiles([[$address,$dest]],$type,$params);
        }

        /** Moves frontend resources, then updates the DB.
         * Can also get by specific addresses
         *
         * @param string[] $inputs Same order as first 2 moveFrontendResourceFile inputs
         * @param array $type 'js' or 'css'
         * @param array $params
         *          'updateDBIfExists'  - bool, default true - Whether to update the db when a local file exists that isn't
         *                                in the DB.
         *          'local'             - bool, default false - only does the operation locally, ignoring the db
         * @returns int Code of the form:
         *         -1 - Could not connect to db
         *          0 - All good
         */
        function moveFrontendResourceFiles(array $inputs, string $type,  array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $updateDBIfExists = isset($params['updateDBIfExists'])? $params['updateDBIfExists'] : true;
            $local = isset($params['local'])? $params['local'] : false;
            if($type === 'js')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
            elseif($type === 'css')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
            else
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('imagePathLocal');

            $existing = [];

            if($updateDBIfExists){
                foreach($inputs as $inputArray){
                    array_push($existing,$inputArray[0]);
                }
                $existing = $this->getResources($existing,$type,array_merge($params,['updateCache'=>false,'type'=>$type]));
            }

            $needToAdd = [];
            $needToMove = [];


            //Move the files
            foreach($inputs as $inputArray){
                $src = $inputArray[0];
                $dest = $inputArray[1];
                if(is_file($rootFolder.$src) || is_dir($rootFolder.$src)){
                    if($updateDBIfExists && !is_array($existing[$src]) ){
                        array_push($needToAdd,[$src]);
                    }
                    $needToMove[$src] = $dest;
                }
            }

            //If there is a need to update the DB, do it. Check that the query succeded
            if($needToAdd!=[] && !$local)
                if($this->setResources($needToAdd,$type,array_merge($params,['existing'=>$existing]))[$inputs[0][0]]===-1)
                    return -1;

            //Rename each resource
            foreach($needToMove as $src=>$dest){
                $continue = (!$local) ? $this->renameResource( $src, $dest, $type, $params) !== -1 : true;
                if($continue){
                    if(!$test){
                        IOFrame\Util\force_rename($rootFolder.$src, $rootFolder.$dest);
                    }
                    if($verbose)
                        echo 'Moving '.$rootFolder.$src.' to '.$rootFolder.$dest.EOL;
                }
                else
                    return -1;
            }

            return 0;
        }

        /** Deletes local frontend file
         *
         * @param string $address address
         * @param array $type 'js' or 'css'
         * @param array $params of the form:
         *          'updateDBIfExists' - bool, default false - will only delete DB items that do not exist locally.
         *          'local'       - bool, default false - only does the operation locally, ignoring the db
         * @returns int Code of the form:
         *         -1 - Could not connect to db
         *          0 - All good
         */
        function deleteFrontendResourceFile(string $address, string $type, array $params = []){
            return $this->deleteJSFiles([$address],$params)[$address];
        }

        /** Deletes local frontend file
         *
         * @param string $address address
         * @param array $type 'js' or 'css'
         * @param array $params of the form:
         *          'updateDBIfNotExists' - bool, default false - will only delete DB items that do not exist locally.
         *          'local'       - bool, default false - only does the operation locally, ignoring the db
         * @returns int Code of the form:
         *         -1 - Could not connect to db
         *          0 - All good
         */
        function deleteFrontendResourceFiles(array $addresses, string $type,  array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $updateDBIfNotExists = isset($params['updateDBIfNotExists'])? $params['updateDBIfNotExists'] : true;
            $local = isset($params['local'])? $params['local'] : false;
            if($type === 'js')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
            elseif($type === 'css')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
            else
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('imagePathLocal');

            $needToDeleteDB = [];
            $needToDelete = [];

            foreach($addresses as $address){
                if(is_file($rootFolder.$address) || is_dir($rootFolder.$address)){
                    $needToDelete[$address] = true;
                    if(!$local)
                        array_push($needToDeleteDB,$address);
                }
                else
                    if(!$updateDBIfNotExists && !$local)
                        array_push($needToDeleteDB,$address);
            }

            //Update the db if needed
            $continue = ($needToDeleteDB !== [])?
                $this->deleteResources($addresses,$type,array_merge($params,['checkExisting'=>false])) : true;
            if(!$continue)
                return -1;

            //Delete the files
            foreach($needToDelete as $address=>$true){
                if(!$test)
                    unlink($rootFolder.$address);
                if($verbose)
                    echo 'Deleting '.$rootFolder.$address.EOL;
            }
            return 0;
        }

        /** Same as incrementResourceVersion
         */
        function incrementFrontendResourceVersion(string $address, string $type, array $params = []){
            return $this->incrementResourceVersion($address, $type, $params);
        }

        /** Same as incrementResourcesVersions
         */
        function incrementFrontendResourceVersions(array $addresses, string $type, array $params = []){
            return $this->incrementResourcesVersions($addresses, $type, $params);
        }


        /** Minifies a local resource
         *
         * @param string $address Relative address of resource to minify
         * @param array $type 'js' or 'css'
         * @param array $params of the form:
         *          'minifyToFolder'    - string, default '' - If set, will place all minified js files into a subfolder
         *                                named after this setting.
         *
         * @returns int|Array
         * Codes:
         *      1 - Address does not exist
         * Array:
         *      [
         *       'address'=>New address
         *       'relativeAddress'=>New address relative to resource root folder
         *       'changeTime'=>New "Last Modified" time
         *      ]
         */
        function minifyFrontendResource( string $address, string $type, array $params = []){
            return $this->minifyFrontendResources([$address],$type, $params)[$address];
        }


        /** Minifies local resources.
         *
         * @param array $addresses Relative addresses of resources to minify
         * @param array $type 'js' or 'css'
         * @param array $params of the form
         *          'minifyName'        - string, default '' - If set, will group all minified files under this name,
         *                                and returns the url of the newly minified resource.
         *          'minifyToFolder'    - string, default '' - If set, will place all minified js files into a subfolder
         *                                named after this setting.
         *                                If minifyName isn't set, offset is relative to each file (or folder).
         *                                Else, offset is relative to the JS root set in settings.
         *
         * @returns array|int
         *      If not minifying to a single file, array of the form:
         *      [
         *       <Address> =>   <code|array from minifyFrontendResource>,
         *      ...
         *      ]
         *      If minifying to a single file the address/changeTime will be of that one file, and only it will be returned,
         *      and minifyName
         */
        function minifyFrontendResources( array $addresses, string $type, array $params = []){
            require_once 'ext/minifyPHP/vendor/autoload.php';
            if(!defined('LockHandler'))
                require 'LockHandler.php';
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            $minifyName = isset($params['minifyName'])? $params['minifyName'] : false;

            $minifyToFolder = isset($params['minifyToFolder'])? $params['minifyToFolder'] : '';

            if($type === 'js')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
            else
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('cssPathLocal');

            //Minifier
            if($type === 'js')
                $minifier = new Minify\JS();
            else
                $minifier = new Minify\CSS();

            if(!$minifyName){
                $results = [];
                foreach($addresses as $address){
                    //Get expected local file locations
                    $resourcePath = explode('/',$address);
                    $resourceName = array_pop($resourcePath);
                    $resourcePath = $rootFolder.implode('/',$resourcePath);
                    if($resourcePath[-1] === '/')
                        $resourcePath = substr($resourcePath,0,-1);
                    $fullAddress = $resourcePath.'/'.$resourceName;
                    $resourceNameTemp = explode('.',$resourceName);
                    $resourceExtension = array_pop($resourceNameTemp);
                    $resourceMinifiedName = implode('.',array_merge($resourceNameTemp,['min',$resourceExtension]));
                    $minifiedAddress = $resourcePath.'/'.$resourceMinifiedName;
                    //Minify into a folder if asked
                    if($minifyToFolder!=''){
                        if(!is_dir($resourcePath.'/'.$minifyToFolder)){
                            if(!$test)
                                mkdir($resourcePath.'/'.$minifyToFolder);
                            if($verbose)
                                echo 'Creating folder '.$resourcePath.'/'.$minifyToFolder.'/'.EOL;
                        }
                        $minifiedAddress = $resourcePath.'/'.$resourceMinifiedName;
                    }

                    //If a mutex already exists, or we cannot make one, return
                    $mutex = new LockHandler($minifiedAddress);
                    if(is_file($minifiedAddress.'_mutex') || !$mutex->makeMutex()){
                        $results[$address] = [
                            'address' => $minifiedAddress,
                            'changeTime' => time()
                        ];
                        $mutex->deleteMutex();
                        continue;
                    }

                    //Remember - either of those may not exist
                    $timeMinifiedChanged = @filemtime($minifiedAddress);
                    $timeOriginalChanged = @filemtime($fullAddress);
                    //If the file exists, and was changed later than the minified version, minify it again
                    if($timeOriginalChanged && $timeOriginalChanged > $timeMinifiedChanged){
                        $minifier->add($fullAddress);
                        if(!$test){
                            $minifier->minify($minifiedAddress);
                        }
                        if($verbose)
                            echo 'Minifying file '.$fullAddress.' to '.$minifiedAddress.EOL;
                        $results[$address] = [
                            'address' => $minifiedAddress,
                            'changeTime' => (!$test) ? @filemtime($minifiedAddress) : time()
                        ];
                    }
                    else{
                        if($timeOriginalChanged){
                            if($verbose)
                                echo 'Minified address '.$minifiedAddress.' is up to date!'.EOL;
                            $results[$address] = [
                                'address' => $minifiedAddress,
                                'changeTime' => $timeMinifiedChanged
                            ];
                        }
                        else{
                            if($verbose)
                                echo 'File '.$fullAddress.' does not exist'.EOL;
                            $results[$address] = 2;
                        }
                    }
                    //Finally delete the mutex
                    $mutex->deleteMutex();
                }
            }
            else{
                $results = [];

                //Check if any of the original files have changed after the minified one
                $originalChangeTime = 0;

                foreach($addresses as $address){
                    //Get expected local file locations
                    $resourcePath = explode('/',$address);
                    $resourceName = array_pop($resourcePath);
                    $resourcePath = $rootFolder.implode('/',$resourcePath);
                    if($resourcePath[-1] === '/')
                        $resourcePath = substr($resourcePath,0,-1);
                    $fullAddress = $resourcePath.'/'.$resourceName;
                    $changeTime = @filemtime($fullAddress);
                    $originalChangeTime = max($changeTime,$originalChangeTime);
                    //Remember - only minify files that exist!
                    if($changeTime){
                        if(!$test)
                            $minifier->add($fullAddress);
                        if($verbose)
                            echo 'File '.$fullAddress.' added to minification'.EOL;
                    }
                    else{
                        if($verbose)
                            echo 'File '.$fullAddress.' does not exist'.EOL;
                    }
                }

                $resourceMinifiedName = $minifyName.'.'.$type;
                $minifyPath = $rootFolder;
                //Minify into a folder if asked
                if($minifyToFolder!=''){
                    if(!is_dir($rootFolder.$minifyToFolder)){
                        if(!$test){
                            mkdir($rootFolder.$minifyToFolder);
                        }
                        if($verbose)
                            echo 'Creating folder '.$resourcePath.'/'.$minifyToFolder.'/'.EOL;
                    }
                }
                $resourceMinifiedName = $minifyToFolder.'/'.$resourceMinifiedName;
                $minifiedAddress = $rootFolder.$resourceMinifiedName;


                //If a mutex already exists, or we cannot make one, return
                $mutex = new LockHandler($minifiedAddress);
                if(is_file($minifiedAddress.'_mutex') || !$mutex->makeMutex()){
                    $results[$minifyName] = [
                        'address' => $minifiedAddress,
                        'changeTime' => time()
                    ];
                    $mutex->deleteMutex();
                    return $results;
                }

                //Remember - the minified version may not exist
                $timeMinifiedChanged = @filemtime($minifiedAddress);

                //If any of the original files was changed alter than the minified version, and some of them exist, minify
                if($originalChangeTime && $originalChangeTime > $timeMinifiedChanged){
                    if(!$test)
                        $minifier->minify($minifiedAddress);
                    if($verbose)
                        echo 'Files '.implode(',',$addresses).' minified to '.$minifiedAddress.EOL;
                    $timeMinifiedChanged = @filemtime($minifiedAddress);
                    $results[$minifyName] = [
                        'address' => $minifiedAddress,
                        'changeTime' => $timeMinifiedChanged
                    ];
                }
                else{
                    if($timeMinifiedChanged){
                        if($verbose)
                            echo 'Minified address '.$minifiedAddress.' is up to date!'.EOL;
                        $results[$minifyName] = [
                            'address' => $minifiedAddress,
                            'changeTime' => $timeMinifiedChanged
                        ];
                    }
                    else{
                        if($verbose)
                            echo 'File '.$minifiedAddress.' does not exist, and neither do the sources!'.EOL;
                        $mutex->deleteMutex();
                        return 1;
                    }
                }
            }

            $mutex->deleteMutex();
            return $results;
        }

        /** Returns frontend resource collections and minifies them while at it.
         *
         * @param string $name Collection name
         * @param array $type 'js' or 'css'
         * @param array $params
         *              -- Params of GetFrontendResource and minifyFrontendResource--
         *
         * @returns int|Array
         * Codes:
         *      1 - Collection does not exist
         * Array:
         *      [
         *       'address'=>New address
         *       'changeTime'=>New "Last Modified" time
         *      ]
         */
        function getFrontendResourceCollection(string $name, string $type, array $params = []){
            return $this->getFrontendResourceCollections([$name],$type,$params)[$name];
        }

        /**Returns frontend resource collections and minifies them while at it.
         *
         * @param string[] $names  Collection names
         * @param array $type 'js' or 'css'
         * @param array $params
         *              -- Params of GetFrontendResources and minifyFrontendResources--
         *              'includeGalleryInfo' - false, whether to include gallery info
         * @returns array|int
         *      [
         *       <Name> =>   <code|array from getJS/GetCSS/etc>,
         *      ...
         *      ]
         *      where the name is the name of the collection and the possible code is 1.
         *      Note - A COLLECTION OF ANY RESOURCES THAT ARE LOCAL WILL BE MINIFIED regardless of settings.
         */
        function getFrontendResourceCollections(array $names, string $type, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $includeGalleryInfo = isset($params['includeGalleryInfo'])? $params['includeGalleryInfo'] : false;
            $minifyToFolder = isset($params['minifyToFolder'])? $params['minifyToFolder'] : 'min';
            //Else get and minify all resources
            $params['getMembers'] = true;
            $results = [];
            $collections = $this->getResourceCollections($names,$type,$params);

            //If we were just getting collection info, nothing to do here
            if($names === [])
                return $collections;

            //For each collection..
            foreach($collections as $name => $collectionArray){
                //If it doesn't exist, it doesn't exist..
                if(!is_array($collectionArray))
                    $results[$name] = $collectionArray;
                else{
                    $addresses = [];
                    $existing = [];

                    foreach($collectionArray as $address => $member){
                        if($address != '@'){
                            array_push($addresses,$address);
                            $existing[$address] = $member;
                        }
                        elseif($includeGalleryInfo){
                            $results['@'.$name] = $member;
                        }
                    }
                    $frontEndResults = $this->getFrontendResources(
                        $addresses,
                        $type,
                        array_merge(
                            $params,
                            [
                                'minifyName' => $name,
                                'minifyToFolder' => $minifyToFolder,
                                'forceMinify' => true,
                                'existingAddresses' => $existing
                            ]
                        )
                    );

                    foreach($frontEndResults as $name => $resultArray){
                        $results[$name] = $resultArray;
                    }
                }
            }

            return $results;
        }

        /** Same as setResourceCollections
         */
        function setFrontendResourceCollection(string $name, string $type, string $meta = null, array $params = []){
            return $this->setResourceCollection($name,$type,$meta,$params);
        }

        /** Same as setResourceCollections
         */
        function deleteFrontendResourceCollection(string $name, string $type, array $params = []){
            return $this->deleteResourceCollection($name,$type,$params);
        }

        /** Same as addResourceToCollection but always ordered
         */
        function addFrontendResourceToCollection( string $address, string $collection, string $type, array $params = []){
            return $this->addFrontendResourcesToCollection([$address], $collection, $type, $params);
        }

        /** Same as addResourcesToCollection but always ordered
         */
        function addFrontendResourcesToCollection( array $addresses, string $collection, string $type, array $params = []){
            $params['pushToOrder'] = true;
            $params['existingAddresses'] = $this->getFrontendResources($addresses,$type,$params);
            $results = [];
            foreach($addresses as $index=>$address){
                if(!isset($params['existingAddresses'][$address])){
                    unset($addresses[$index]);
                    $results[$address] = 1;
                }
            }
            return array_merge($results,$this->addResourcesToCollection($addresses, $collection, $type, $params));
        }

        /** Same as removeResourceFromCollection
         */
        function removeFrontendResourceFromCollection( string $address, string $collection, string $type, array $params = []){
            return $this->removeFrontendResourcesFromCollection([$address], $collection, $type, $params);
        }

        /** Same as removeResourcesFromCollection
         */
        function removeFrontendResourcesFromCollection( array $addresses, string $collection, string $type, array $params = []){
            $params['removeFromOrder'] = true;
            return $this->removeResourcesFromCollection($addresses, $collection, $type, $params);
        }

        /** Same as moveCollectionOrder
         */
        function moveFrontendResourceCollectionOrder(int $from, int $to, string $collection, string $type, array $params){
            return $this->moveCollectionOrder($from, $to, $collection, $type, $params);
        }

        /** Same as swapCollectionOrder
         */
        function swapFrontendResourceCollectionOrder(int $num1,int $num2, string $collection, string $type, array $params){
            return $this->swapCollectionOrder($num1, $num2, $collection, $type, $params);
        }

        /*------------------------------------------------ HERE BE JS ------------------------------------------------*/

        /**  getFrontendResources with type 'js'
         */
        function getJS(array $addresses = [], array $params = []){
            return $this->getFrontendResources($addresses,'js',$params);
        }

        /**  moveFrontendResourceFiles with type 'js'
         */
        function moveJSFile(string $src, string $dest,  array $params = []){
            return $this->moveFrontendResourceFile($src, $dest,'js',$params);
        }

        /**  moveFrontendResourceFiles with type 'js'
         */
        function moveJSFiles(array $inputs,  array $params = []){
            return $this->moveFrontendResourceFiles($inputs,'js',$params);
        }

        /**  deleteFrontendResourceFile with type 'js'
         */
        function deleteJSFile(string $address,  array $params = []){
            return $this->deleteFrontendResourceFile($address,'js',$params);
        }

        /**  deleteFrontendResourceFiles with type 'js'
         */
        function deleteJSFiles(array $addresses,  array $params = []){
            return $this->deleteFrontendResourceFiles($addresses,'js',$params);
        }

        /** Same as incrementResourceVersion
         */
        function incrementJSVersion(string $address, array $params = []){
            return $this->incrementFrontendResourceVersion($address, 'js', $params);

        }

        /** Same as incrementResourcesVersions
         */
        function incrementJSVersions(array $addresses, array $params = []){
            return $this->incrementFrontendResourceVersions($addresses, 'js', $params);

        }

        /** Same as minifyFrontendResource with type 'js'
         */
        function minifyJSFile( string $address, array $params = []){
            return $this->minifyFrontendResource($address, 'js',$params);
        }

        /** Same as minifyFrontendResources with type 'js'
         */
        function minifyJSFiles( array $addresses, array $params = [] ){
            return $this->minifyFrontendResources($addresses, 'js',$params);
        }

        /** Same as getFrontendResourceCollection
         */
        function getJSCollection(string $name, array $params = [] ){
            return $this->getFrontendResourceCollection($name, 'js', $params);
        }
        /** Same as getFrontendResourceCollections;
         */
        function getJSCollections(array $names, array $params = [] ){
            return $this->getFrontendResourceCollections($names, 'js', $params);
        }

        /** Same as setFrontendResourceCollection
         */
        function setJSCollection(string $name, string $meta = null, array $params = []){
            return $this->setFrontendResourceCollection($name,'js',$meta,$params);
        }

        /** Same as deleteFrontendResourceCollection
         */
        function deleteJSCollection(string $name, array $params = []){
            return $this->deleteFrontendResourceCollection($name,'js',$params);
        }

        /** addFrontendResourceToCollection wrapper
         */
        function addJSFileToCollection( string $address, string $collection, array $params = []){
            return $this->addFrontendResourceToCollection($address,$collection, 'js', $params);
        }


        /** addFrontendResourcesToCollection wrapper
         */
        function addJSFilesToCollection( array $addresses, string $collection, array $params = []){
            return $this->addFrontendResourcesToCollection($addresses,$collection, 'js', $params);
        }

        /**  removeFrontendResourceFromCollection wrapper
         */
        function removeJSFileFromCollection( string $address, string $collection, array $params = []){
            return $this->removeFrontendResourceFromCollection($address, $collection, 'js', $params);
        }

        /**  removeFrontendResourcesFromCollection wrapper
         */
        function removeJSFilesFromCollection( array $addresses, string $collection, array $params = []){
            return $this->removeFrontendResourcesFromCollection($addresses, $collection, 'js', $params);
        }

        /** moveFrontendResourceCollectionOrder wrapper
         */
        function moveJSCollectionOrder(int $from, int $to, string $collection, array $params = [] ){
            return $this->moveFrontendResourceCollectionOrder($from, $to, $collection, 'js', $params);
        }

        /** swapFrontendResourceCollectionOrder wrapper
         */
        function swapJSCollectionOrder(int $num1,int $num2, string $collection, array $params = [] ){
            return $this->swapFrontendResourceCollectionOrder($num1, $num2, $collection, 'js', $params);
        }

        /*------------------------------------------------ HERE BE CSS ------------------------------------------------*/

        /** getFrontendResources with type 'css'
         */
        function getCSS(array $addresses = [], array $params = []){
            return $this->getFrontendResources($addresses,'css',$params);

        }

        /**  moveFrontendResourceFiles with type 'css'
         */
        function moveCSSFile(string $src, string $dest,  array $params = []){
            return $this->moveFrontendResourceFile($src, $dest,'css',$params);
        }

        /**  moveFrontendResourceFiles with type 'css'
         */
        function moveCSSFiles(array $inputs,  array $params = []){
            return $this->moveFrontendResourceFiles($inputs,'css',$params);
        }

        /**  deleteFrontendResourceFile with type 'css'
         */
        function deleteCSSFile(string $address,  array $params = []){
            return $this->deleteFrontendResourceFile($address,'css',$params);
        }

        /**  deleteFrontendResourceFiles with type 'css'
         */
        function deleteCSSFiles(array $addresses,  array $params = []){
            return $this->deleteFrontendResourceFiles($addresses,'css',$params);
        }

        /** Same as incrementResourceVersion
         */
        function incrementCSSVersion(string $address, array $params = []){
            return $this->incrementFrontendResourceVersion($address, 'css', $params);

        }

        /** Same as incrementResourcesVersions
         */
        function incrementCSSVersions(array $addresses, array $params = []){
            return $this->incrementFrontendResourceVersions($addresses,'css', $params);

        }

        /** Same as minifyFrontendResource with type 'css'
         */
        function minifyCSSFile( string $address, array $params = []){
            return $this->minifyFrontendResource($address, 'css',$params);
        }

        /** Same as minifyFrontendResources with type 'css'
         */
        function minifyCSSFiles( array $addresses, array $params = [] ){
            return $this->minifyFrontendResources($addresses, 'css',$params);
        }

        /** Same as getFrontendResourceCollection
         */
        function getCSSCollection(string $name, array $params = [] ){
            return $this->getFrontendResourceCollection($name, 'css', $params);
        }
        /** Same as getFrontendResourceCollections;
         */
        function getCSSCollections(array $names, array $params = [] ){
            return $this->getFrontendResourceCollections($names, 'css', $params);
        }


        /** Same as the JS version but with CSS
         */
        function setCSSCollection(string $name, string $meta = null , array $params = []){
            return $this->setFrontendResourceCollection($name,'css',$meta,$params)[$name];
        }

        /** Same as deleteFrontendResourceCollection
         */
        function deleteCSSCollection(string $name, array $params = []){
            return $this->deleteFrontendResourceCollection($name,'css',$params);
        }

        /** addFrontendResourceToCollection wrapper
         */
        function addCSSFileToCollection( string $address, string $collection, array $params = []){
            return $this->addFrontendResourceToCollection($address,$collection, 'css', $params);
        }

        /** addFrontendResourcesToCollection wrapper
         */
        function addCSSFilesToCollection( array $addresses, string $collection, array $params = []){
            return $this->addFrontendResourcesToCollection($addresses,$collection, 'css', $params);
        }

        /** removeFrontendResourceFromCollection wrapper
         */
        function removeCSSFileFromCollection( string $address, string $collection, array $params = []){
            return $this->removeFrontendResourceFromCollection($address, $collection, 'css', $params);
        }

        /** removeFrontendResourcesFromCollection wrapper
         */
        function removeCSSFilesFromCollection( array $addresses, string $collection, array $params = []){
            return $this->removeFrontendResourcesFromCollection($addresses, $collection, 'css', $params);
        }

        /** moveFrontendResourceCollectionOrder wrapper
         */
        function moveCSSCollectionOrder(int $from, int $to, string $collection, array $params = [] ){
            return $this->moveFrontendResourceCollectionOrder($from, $to, $collection, 'css', $params);
        }

        /** swapFrontendResourceCollectionOrder wrapper
         */
        function swapCSSCollectionOrder(int $num1,int $num2, string $collection, array $params = [] ){
            return $this->swapFrontendResourceCollectionOrder($num1, $num2, $collection, 'css', $params);
        }

        /** Compiles a CSS file into an SCSS file
         *
         * @param string $address Relative address of the SCSS file
         * @param array $params -
         *          'checkExisting' - bool, default true - checks whether the SCSS is newer than an existing CSS
         *                            file, if one exists.
         *          'compileToFolder' - string, default '' - If set, will place all compiled css files into a subfolder
         *                             named after this param.
         *
         * @return array
         * of the form
         *      [
         *              'newName' => <new Name> | '',
         *              'newAddress' => <new address> | '',
         *      ]
         *  Empty string if the compilation fails
         */
        function compileSCSS(string $address, array $params = []){
            require_once 'ext/scssphp/scss.inc.php';

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : $test ? true : false;
            $checkExisting = isset($params['checkExisting'])? $params['checkExisting'] : true;
            $compileToFolder = isset($params['compileToFolder'])? $params['compileToFolder'] : '';

            $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('cssPathLocal');

            $resultAddress =
                [
                    'newName'=>'',
                    'newAddress'=>''
                ];

            //Get import folder
            $importFolder = $rootFolder;
            $resourcePath = explode('/',$address);
            $resourceFoldersCount = count($resourcePath)-1;
            //The import folder is the one
            for($i = 0; $i<$resourceFoldersCount; $i++ ){
                $importFolder .= $resourcePath[$i].'/';
            };

            //Get export folder
            if(!$compileToFolder)
                $exportFolder = $importFolder;
            else{
                if(!is_dir($importFolder.$compileToFolder)){
                    if(!$test)
                        mkdir($importFolder.$compileToFolder);
                    if($verbose)
                        echo 'Creating directory '.$importFolder.$compileToFolder.EOL;
                }
                $exportFolder = $importFolder.$compileToFolder;
            }
            if($exportFolder[-1] === '/' )
                $exportFolder = substr($exportFolder,0,-1);

            // Extract the new name
            $originName = $resourcePath[$resourceFoldersCount];
            $newName = explode('.',$originName);
            $newName[1] = 'css';
            $newName = implode('.',$newName);

            // Extract the relative path
            $newPath = $resourcePath;
            array_pop($newPath);
            $newPath = implode('/',$newPath);
            if(count($resourcePath)>1)
                $newPath .= '/';
            if($compileToFolder)
                $newPath .= $compileToFolder.'/';
            $newPath .= $newName;

            //Set the export address
            $exportAddress = $exportFolder.'/'.$newName;

            //Check if files exist and are up to date
            if($checkExisting){
                $existingOriginalTime = @filemtime($rootFolder.$address);
                $existingCompiledTime = @filemtime($exportAddress);
                if($existingOriginalTime && $existingCompiledTime>$existingOriginalTime )
                    return
                        [
                        'newName'=>$newPath,
                        'newAddress'=>$exportAddress
                        ];
                elseif(!$existingOriginalTime)
                    return $resultAddress;
            }

            if(!$test){
                $scss = new \ScssPhp\ScssPhp\Compiler();
                $scss->setImportPaths($importFolder);
                $compiled = $scss->compile('@import "'.$originName.'";');
                if($compiled){
                    IOFrame\Util\file_force_contents($exportAddress,$compiled);
                    $resultAddress = [
                        'newName'=>$newPath,
                        'newAddress'=>$exportAddress
                    ];
                }
            }
            else
                $resultAddress = [
                    'newName'=>$newPath,
                    'newAddress'=>$exportAddress
                ];

            if($verbose){
                echo 'Compiling file '.$address.' to '.$exportAddress.EOL;
            }

            return $resultAddress;
        }

        /*---------------------------------------------- HERE BE IMAGES ----------------------------------------------*/

        /** getFrontendResources with type 'css'
         */
        function getImages(array $addresses = [], array $params = []){
            return $this->getFrontendResources($addresses,'img',$params);
        }

        /**  moveFrontendResourceFiles with type 'css'
         */
        function moveImage(string $src, string $dest,  array $params = []){
            return $this->moveFrontendResourceFile($src, $dest, 'img',$params);
        }

        /**  moveFrontendResourceFiles with type 'css'
         */
        function moveImages(array $inputs,  array $params = []){
            return $this->moveFrontendResourceFiles($inputs,'img',$params);
        }

        /**  deleteFrontendResourceFile with type 'css'
         */
        function deleteImage(string $address,  array $params = []){
            return $this->deleteImages([$address],$params);
        }

        /**  deleteFrontendResourceFiles with type 'css'
         */
        function deleteImages(array $addresses,  array $params = []){
            return $this->deleteFrontendResourceFiles($addresses,'img',$params);
        }

        /** Same as incrementResourceVersion
         */
        function incrementImage(string $address, array $params = []){
            return $this->incrementImages([$address], $params);
        }

        /** Same as incrementResourcesVersions
         */
        function incrementImages(array $addresses, array $params = []){
            return $this->incrementResourcesVersions($addresses,'img', $params);
        }

        /** Same as getFrontendResourceCollection
         */
        function getGallery(string $name, array $params = [] ){
            return $this->getGalleries([$name], $params);
        }
        /** Same as getFrontendResourceCollections;
         */
        function getGalleries(array $names, array $params = [] ){
            return $this->getFrontendResourceCollections($names, 'img', $params);
        }

        /** Same as setResourceCollections
         */
        function setGallery(string $name, string $meta = null , array $params = []){
            return $this->setFrontendResourceCollection($name,'img',$meta,$params);
        }

        /** Same as deleteFrontendResourceCollection
         */
        function deleteGallery(string $name, array $params = []){
            return $this->deleteFrontendResourceCollection($name,'img',$params);
        }

        /** Same as addResourceToCollection but always ordered
         */
        function addImageToGallery( string $address, string $collection, array $params = []){
            return $this->addImagesToGallery([$address], $collection, $params);
        }

        /** Same as addResourcesToCollection but always ordered
         */
        function addImagesToGallery( array $addresses, string $collection, array $params = []){
            return $this->addFrontendResourcesToCollection($addresses, $collection, 'img', $params);
        }

        /** Same as removeResourceFromCollection
         */
        function removeImageFromGallery( string $address, string $collection, array $params = []){
            return $this->removeImagesFromGallery([$address], $collection, $params);
        }

        /** Same as removeResourcesFromCollection
         */
        function removeImagesFromGallery( array $addresses, string $collection, array $params = []){
            return $this->removeFrontendResourcesFromCollection($addresses, $collection, 'img', $params);
        }

        /** Same as moveCollectionOrder
         */
        function moveGalleryOrder(int $from, int $to, string $collection, array $params){
            return $this->moveFrontendResourceCollectionOrder($from, $to, $collection, 'img', $params);
        }

        /** Same as swapCollectionOrder
         */
        function swapGalleryOrder(int $num1,int $num2, string $collection, array $params){
            return $this->swapFrontendResourceCollectionOrder($num1, $num2, $collection, 'img', $params);
        }

    }
}