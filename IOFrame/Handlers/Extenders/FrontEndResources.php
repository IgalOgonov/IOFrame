<?php
namespace IOFrame\Handlers\Extenders{
    use MatthiasMullie\Minify;
    use ScssPhp\ScssPhp;
    use ScssPhp\ScssPhp\Exception\SassException;

    /**  This class manages front-end resources.
     *  Specifically, JS and CSS (for now).
     *  Also used for optional on-the-fly minification and bundling.
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class FrontEndResources extends \IOFrame\Handlers\ResourceHandler
    {

        /** Standard constructor
         *
         * @param \IOFrame\Handlers\SettingsHandler $settings The standard settings object
         * @param array $params - All parameters share the name/type of the class variables
         * */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, array $params = []){

            parent::__construct($settings,$params);
        }

        /** Gets all resources available, by type.
         * Can also get by specific addresses
         *
         * @param array $addresses defaults to [], if not empty will only get specific resources by addresses
         * @param string $type 'js' or 'css'
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
         *                                If minifyName isn't set, offset is relative to each file (or folder) UNLESS
         *                                the value starts with '/'.
         *                                Else, offset is relative to the JS root set in settings.
         *          'scss'              - bool, default true - whether to compile scss to css on the fly.
         *
         * @return array
         * @throws SassException
         */
        protected function getFrontendResources(array $addresses, string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            //If we are getting all addresses, enforce restrictions
            if($addresses == []){
                if(!isset($params['ignoreLocal']))
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
                $rootFolder = $params['rootFolder'];
            else{
                if($type === 'js')
                    $rootFolder = $this->resourceSettings->getSetting('jsPathLocal');
                elseif($type === 'css')
                    $rootFolder = $this->resourceSettings->getSetting('cssPathLocal');
                elseif($type === 'img')
                    $rootFolder = $this->resourceSettings->getSetting('imagePathLocal');
                elseif($type === 'vid')
                    $rootFolder = $this->resourceSettings->getSetting('videoPathLocal');
                else
                    throw new \Exception('Invalid front-end resource type!');
            }
            $rootFolderOrigin = $rootFolder;
            $rootFolder = $this->settings->getSetting('absPathToRoot').$rootFolder;

            $updateDBIfExists = !isset($params['updateDBIfExists']) || $params['updateDBIfExists'];

            $includeChildFiles = !isset($params['includeChildFiles']) || $params['includeChildFiles'];

            $includeChildFolders = $params['includeChildFolders'] ?? false;

            $includeSubFolders = $params['includeSubFolders'] ?? false;

            $minifyName = $params['minifyName'] ?? false;

            $ignoreLocal = $params['ignoreLocal'] ?? false;

            $params['ignoreBlob'] = $params['ignoreBlob'] ?? true;

            if(isset($params['forceMinify']) && ($type === 'js' || $type === 'css'))
                $forceMinify = $params['forceMinify'];
            else
                if($type === 'js')
                    $forceMinify = $this->resourceSettings->getSetting('autoMinifyJS') == 1;
                elseif($type === 'css')
                    $forceMinify = $this->resourceSettings->getSetting('autoMinifyCSS') == 1;
                else
                    $forceMinify = false;
            $scss = !isset($params['scss']) || $params['scss'];

            $resources = $params['existingAddresses'] ?? $this->getResources($addresses, $type, $params);

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
                //The address '@' is reserved for meta information, in case of a full search
                if($address === '@'){
                    $resourcesToReturn[$address] = $resource;
                    continue;
                }

                //Get expected local file locations
                $currentRootFolder = $rootFolder;
                $currentRootFolderOrigin = $rootFolderOrigin;
                $resourcePath = explode('/',$address);
                $resourceName = array_pop($resourcePath);
                $resourcePath = $rootFolder.implode('/',$resourcePath);
                if($resourcePath[-1] === '/')
                    $resourcePath = substr($resourcePath,0,-1);
                $fullAddress = ($addresses !== [])? $resourcePath.'/'.$resourceName : substr($rootFolder,0,-1);
                $isDir = is_dir($fullAddress);
                $isFile = is_file($fullAddress);

                //Fall back to ioframe default resource location IF both dir and file do not exist, but IOFrame ones do
                if(!$isDir && !$isFile && !$ignoreLocal){
                    $fallBack = [];
                    if($type === 'js')
                        $fallBack['currentRootFolder'] = 'front/ioframe/js/';
                    elseif($type === 'css')
                        $fallBack['currentRootFolder'] = 'front/ioframe/css/';
                    elseif($type === 'img')
                        $fallBack['currentRootFolder'] = 'front/ioframe/img/';
                    elseif($type === 'vid')
                        $fallBack['currentRootFolder'] = 'front/ioframe/vid/';
                    $fallBack['currentRootFolderOrigin'] = $fallBack['currentRootFolder'];
                    $fallBack['currentRootFolder'] = $this->settings->getSetting('absPathToRoot').$fallBack['currentRootFolder'];
                    $fallBack['resourcePath'] = explode('/',$address);
                    $fallBack['resourceName'] = array_pop($fallBack['resourcePath']);
                    $fallBack['resourcePath'] = $fallBack['currentRootFolder'].implode('/',$fallBack['resourcePath']);
                    if($fallBack['resourcePath'][-1] === '/')
                        $fallBack['resourcePath'] = substr($fallBack['resourcePath'],0,-1);
                    $fallBack['fullAddress'] = ($addresses !== [])? $fallBack['resourcePath'].'/'.$fallBack['resourceName'] : substr($fallBack['currentRootFolder'],0,-1);
                    $fallBack['isDir'] = is_dir($fullAddress);
                    $fallBack['isFile'] = is_file($fullAddress);
                    if($fallBack['isDir'] || $fallBack['isFile']){
                        foreach($fallBack as $paramName => $value){
                            ${$paramName} = $value;
                        }
                    }
                }

                $resourceNameTemp = explode('.',$resourceName);
                $resourceExtension = $isFile? array_pop($resourceNameTemp) : '';
                $newAddress = '';

                //Make sure the type matches the extension in case of js/css
                if( $isFile && ($type !== $resourceExtension)  && (in_array($type,['js','css'])) ){
                    //In case of a CSS file that's an SCSS file, compile it to CSS on the fly
                    if($type === 'css' && $resourceExtension === 'scss' && $scss){
                        $compilation = $this->compileSCSS($address,array_merge($params,['rootFolder'=>$currentRootFolderOrigin]));
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
                    if( (!$isDir && !$isFile) || $ignoreLocal )
                        continue;
                    $resource = [];
                    //If we are dealing with an SCSS the address is different
                    if($type === 'css' && $resourceExtension === 'scss')
                        $resource['Address'] = $newAddress;
                    else
                        $resource['Address'] = $address;
                    $fullAddress = ($addresses !== [])? $currentRootFolder.$resource['Address'] : substr($currentRootFolder,0,-1);
                    $resource['Resource_Local'] = true;
                    $resource['Minified_Version'] = false;
                    $resource['Text_Content'] = null;
                    $resource['Version'] = 1;
                    $existing[$address] = 1;
                    //Add the resource if requested
                    if($updateDBIfExists){
                        $resourcesToAdd[] = ['address' => $address];
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
                                    $moreResourcesToReturn[] = $address . '/' . $content;
                                else
                                    $moreResourcesToReturn[] = $content;
                        }
                    }
                    //Handle recursive return
                    $folderParams = array_merge($params,['rootFolder'=>$currentRootFolderOrigin]);
                    if(!$includeSubFolders){
                        $folderParams['includeChildFiles'] = false;
                        $folderParams['includeChildFolders'] = false;
                    }
                    else{
                        $folderParams['includeChildFiles'] = true;
                        $folderParams['includeChildFolders'] = true;
                    }
                    //Add all the extra resources to return
                    if($moreResourcesToReturn != [])
                        $resourcesToReturn = array_merge(
                            $resourcesToReturn,
                            $this->getFrontendResources($moreResourcesToReturn,$type,$folderParams)
                        );
                }

                //If the resource isn't local, just return it as is.
                if(!$resource['Resource_Local']){
                    //Links will not have a data type, while actuall resources will
                    $resourcesToReturn[$address] = $resource['Data_Type']?
                        [
                            'address' => $resource['Address'],
                            'dataType' => $resource['Data_Type'],
                            'relativeAddress' => '',
                            'folder' => false,
                            'meta' =>  $resource['Text_Content'],
                            'lastChanged' => $resource['Last_Updated'],
                            'version' => $resource['Version'],
                            'type' => $resource['Data_Type'],
                            'local' => false,
                            'size' => 0
                        ]
                        :
                        [
                            'address' => $resource['Address'],
                            'dataType' => null,
                            'relativeAddress' => '',
                            'folder' => false,
                            'meta' =>  $resource['Text_Content'],
                            'lastChanged' => $resource['Last_Updated'],
                            'version' => $resource['Version'],
                            'local' => false,
                            'size' => 0
                        ];
                    if($verbose)
                        echo 'Returning remote resource '.$address.' as is!'.EOL;
                    continue;
                }

                $changeTime = 0;
                //Minify or add to minified list
                $resourceNameHintsItsMinified = preg_match('/.+\.min\.'.$type.'$/',$address);
                if($forceMinify && !($resource['Minified_Version'] || $isDir || $resourceNameHintsItsMinified)){

                    if($minifyName){
                        if($newAddress == '')
                            $resourcesToMinify[] = $address;
                        else
                            $resourcesToMinify[] = $newAddress;
                    }
                    else{
                        if($newAddress == '')
                            $minified = $this->minifyFrontendResource($address,$type,array_merge($params,['rootFolder'=>$currentRootFolderOrigin]));
                        else
                            $minified = $this->minifyFrontendResource($newAddress,$type,array_merge($params,['rootFolder'=>$currentRootFolderOrigin]));

                        if(!is_array($minified))
                            continue;
                        else{
                            $fullAddress = $minified['address'];
                            $changeTime = $minified['lastChanged'];
                        }
                    }
                }
                else{
                    //Update the file change time if requested, plus check that the file exists
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
                        'dataType' => null,
                        'relativeAddress' => substr($fullAddress,strlen($currentRootFolder)),
                        'folder' => $isDir,
                        'meta' =>  $resource['Text_Content'],
                        'lastChanged' => $changeTime,
                        'version' => $resource['Version'],
                        'local' => true,
                        'size' => ($isDir)? 0 : @filesize($fullAddress)
                    ];
                }
            }

            //If we are here, it means we are minifying multiple files into one and returning it
            if(!empty($resourcesToMinify)){

                $minified = $this->minifyFrontendResources($resourcesToMinify,$type,$params)[$minifyName];

                //Only return anything if there is something to return (aka resources were minified)
                if(is_array($minified)){

                    $fullAddress = $minified['address'];
                    $changeTime = $minified['lastChanged'];

                    $resourcesToReturn[$minifyName] = [
                        'address' => $fullAddress,
                        'dataType' => null,
                        'relativeAddress' => substr($fullAddress,strlen($rootFolder)),
                        'folder' => false,
                        'meta' =>  null,
                        'lastChanged' => $changeTime,
                        'version' => 1,
                        'local' => true,
                        'size' => @filesize($fullAddress)
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
         * @param string $type 'js' or 'css'
         * @param array $params
         *          'updateDBIfExists'  - bool, default true - Whether to update the db when a local file exists that isn't
         *                                in the DB.
         *          'local'             - bool, default false - only does the operation locally, ignoring the db
         * @return int
         * @throws \Exception
         */
        function moveFrontendResourceFile(string $address, string $dest, string $type, array $params = []): int {
            return $this->moveFrontendResourceFiles([[$address,$dest]],$type,$params);
        }

        /** Moves frontend resources, then updates the DB.
         * Can also get by specific addresses
         *
         * @param array[] $inputs Same order as first 2 moveFrontendResourceFile inputs
         * @param string $type 'js' or 'css'
         * @param array $params
         *          'updateDBIfExists'  - bool, default true - Whether to update the db when a local file exists that isn't
         *                                in the DB.
         *          'local'             - bool, default false - only does the operation locally, ignoring the db
         *          'copy'             - bool, default false - copies the file instead of moving it
         *          'existingAddresses' - Array, potential existing addresses if we already got them earlier.
         * @return int
         * @throws \Exception If provided type is not supported.
         */
        function moveFrontendResourceFiles(array $inputs, string $type,  array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $updateDBIfExists = !isset($params['updateDBIfExists']) || $params['updateDBIfExists'];
            $local = $params['local'] ?? false;
            $copy = $params['copy'] ?? false;

            if($type === 'js')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
            elseif($type === 'css')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
            elseif($type === 'img')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('imagePathLocal');
            elseif($type === 'vid')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('videoPathLocal');
            else
                throw new \Exception('Invalid front-end resource type!');

            $existing = [];

            if($updateDBIfExists){
                foreach($inputs as $inputArray){
                    $existing[] = $inputArray[0];
                }
                $existing = $this->getResources($existing,$type,array_merge($params,['updateCache'=>false,'type'=>$type]));
            }

            $needToAdd = [];
            $needToMove = [];

            //Move the files
            foreach($inputs as $inputArray){
                $src = $inputArray[0];
                $dest = $inputArray[1];
                //Check destination not existing
                if(is_file($rootFolder.$dest) || is_dir($rootFolder.$dest)){
                    return 1;
                }

                //Check origin existing
                if(!is_file($rootFolder.$src) && !is_dir($rootFolder.$src)){
                    return 2;
                }

                //Add to DB if requested
                if(is_file($rootFolder.$src) || is_dir($rootFolder.$src)){
                    if($updateDBIfExists && !is_array($existing[$src]) ){
                        $needToAdd[] = ['address' => $src];
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
                $continue = $local || ($this->renameResource($src, $dest, $type, $params) !== -1);
                if($continue){
                    if(!$copy){
                        if(!$test){
                            if(!\IOFrame\Util\FileSystemFunctions::force_rename($rootFolder.$src, $rootFolder.$dest))
                                $this->logger->error('Failed to rename local file',['from'=>$rootFolder.$src,'to'=>$rootFolder.$dest]);
                        }
                        if($verbose)
                            echo 'Moving '.$rootFolder.$src.' to '.$rootFolder.$dest.EOL;
                    }
                    else{
                        if(!$test){
                            if(!is_dir($rootFolder.$src))
                                $res  = copy($rootFolder.$src, $rootFolder.$dest);
                            else
                                $res = \IOFrame\Util\FileSystemFunctions::folder_copy($rootFolder.$src, $rootFolder.$dest);
                            if(!$res)
                                $this->logger->error('Failed to copy local file',['from'=>$rootFolder.$src,'to'=>$rootFolder.$dest]);
                        }
                        if($verbose)
                            echo 'Copying '.$rootFolder.$src.' to '.$rootFolder.$dest.EOL;
                    }
                }
                else
                    return -1;
            }

            return 0;
        }

        /** Deletes local frontend file
         *
         * @param string $address address
         * @param string $type 'js' or 'css'
         * @param array $params of the form:
         *          'updateDBIfExists' - bool, default false - will only delete DB items that do not exist locally.
         *          'local'       - bool, default false - only does the operation locally, ignoring the db
         * @return int
         * @throws \Exception
         */
        function deleteFrontendResourceFile(string $address, string $type, array $params = []): int {
            return $this->deleteFrontendResourceFiles([$address],$type,$params);
        }

        /** Deletes local frontend file
         *
         * @param array $addresses
         * @param string $type 'js' or 'css'
         * @param array $params of the form:
         *          'updateDBIfNotExists' - bool, default false - will only delete DB items that do not exist locally.
         *          'local'       - bool, default false - only does the operation locally, ignoring the db
         * @return int
         * @throws \Exception If provided type is not supported.
         */
        function deleteFrontendResourceFiles(array $addresses, string $type,  array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $updateDBIfNotExists = $params['updateDBIfNotExists'] ?? true;
            $local = $params['local'] ?? false;
            if($type === 'js')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
            elseif($type === 'css')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('jsPathLocal');
            elseif($type === 'img')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('imagePathLocal');
            elseif($type === 'vid')
                $rootFolder = $this->settings->getSetting('absPathToRoot').$this->resourceSettings->getSetting('videoPathLocal');
            else
                throw new \Exception('Invalid front-end resource type!');

            $needToDeleteDB = [];
            $needToDelete = [];

            foreach($addresses as $address){
                if(is_file($rootFolder.$address) || is_dir($rootFolder.$address)){
                    $needToDelete[$address] = true;
                    if(!$local)
                        $needToDeleteDB[] = $address;
                }
                else
                    if(!$updateDBIfNotExists && !$local)
                        $needToDeleteDB[] = $address;
            }

            //Update the db if needed
            $continue = ($needToDeleteDB !== [])?
                $this->deleteResources($addresses,$type,array_merge($params,['checkExisting'=>false])) : true;
            if(!$continue)
                return -1;

            $allRes = [];
            //Delete the files
            foreach($needToDelete as $address=>$true){
                if($verbose)
                    echo 'Deleting '.$rootFolder.$address.EOL;
                if(!$test){
                    $res = is_file($rootFolder.$address)? unlink($rootFolder.$address) : \IOFrame\Util\FileSystemFunctions::folder_delete($rootFolder.$address);
                    if(!$res)
                        $allRes[] = $rootFolder.$address;
                }
            }
            if(!empty($allRes))
                $this->logger->error('Failed to delete some local files/folders',['resources'=>$allRes]);

            return 0;
        }

        /** Same as incrementResourceVersion
         */
        function incrementFrontendResourceVersion(string $address, string $type, array $params = []): int {
            return $this->incrementResourceVersion($address, $type, $params);
        }

        /** Same as incrementResourcesVersions
         */
        function incrementFrontendResourceVersions(array $addresses, string $type, array $params = []): int {
            return $this->incrementResourcesVersions($addresses, $type, $params);
        }


        /** Minifies a local resource
         *
         * @param string $address Relative address of resource to minify
         * @param string $type 'js' or 'css'
         * @param array $params same as minifyFrontendResources
         *
         * @return mixed
         * @throws SassException
         */
        function minifyFrontendResource( string $address, string $type, array $params = []){
            return $this->minifyFrontendResources([$address],$type, $params)[$address];
        }


        /** Minifies local resources.
         *
         * @param array $addresses Relative addresses of resources to minify
         * @param string $type 'js' or 'css'
         * @param array $params of the form
         *          'minifyName'        - string, default '' - If set, will group all minified files under this name,
         *                                and returns the url of the newly minified resource.
         *          'minifyToFolder'    - string, default '' - If set, will place all minified js files into a subfolder
         *                                named after this setting.
         *                                If minifyName isn't set, offset is relative to each file (or folder).
         *                                Else, offset is relative to the JS root set in settings.
         *           'ignoreTimeChanged'  - bool, default false - whether to ignore time changed for minifying files
         *
         * @return array|int
         * @throws SassException
         */
        function minifyFrontendResources( array $addresses, string $type, array $params = []): array|int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $ignoreTimeChanged = $params['ignoreTimeChanged']?? false;

            $minifyName = $params['minifyName'] ?? false;

            $minifyToFolder = $params['minifyToFolder'] ?? '';

            if(isset($params['rootFolder']))
                $rootFolder = $params['rootFolder'];
            else{
                if($type === 'js')
                    $rootFolder = $this->resourceSettings->getSetting('jsPathLocal');
                else
                    $rootFolder = $this->resourceSettings->getSetting('cssPathLocal');
            }
            $rootFolder = $this->settings->getSetting('absPathToRoot').$rootFolder;

            //Minifier
            if($type === 'js')
                $minifier = new Minify\JS();
            else
                $minifier = new Minify\CSS();

            $results = [];
            if(!$minifyName){
                foreach($addresses as $address){
                    $currentRoot = $rootFolder;
                    //Get expected local file locations
                    $resourcePath = explode('/',$address);
                    $resourceName = array_pop($resourcePath);
                    $resourcePath = $currentRoot.implode('/',$resourcePath);
                    if($resourcePath[-1] === '/')
                        $resourcePath = substr($resourcePath,0,-1);
                    $fullAddress = $resourcePath.'/'.$resourceName;
                    $timeOriginalChanged = @filemtime($fullAddress);

                    //Check if file exists, if not - try to fall back to default folder
                    if(!$timeOriginalChanged){
                        if($type === 'js')
                            $currentRoot = $this->settings->getSetting('absPathToRoot').'front/ioframe/js/';
                        else
                            $currentRoot = $this->settings->getSetting('absPathToRoot').'front/ioframe/css/';
                        $fallBack = [];
                        $fallBack['resourcePath'] = explode('/',$address);
                        $fallBack['resourceName'] = array_pop($fallBack['resourcePath']);
                        $fallBack['resourcePath'] = $currentRoot.implode('/',$fallBack['resourcePath']);
                        if($fallBack['resourcePath'][-1] === '/')
                            $fallBack['resourcePath'] = substr($fallBack['resourcePath'],0,-1);
                        $fallBack['fullAddress'] = $fallBack['resourcePath'].'/'.$fallBack['resourceName'];
                        $fallBack['timeOriginalChanged'] = @filemtime($fallBack['fullAddress']);
                        if($fallBack['timeOriginalChanged']){
                            foreach($fallBack as $paramName => $value){
                                    ${$paramName} = $value;
                            }
                        }
                        else{
                            $results[$address] = 1;
                            continue;
                        }
                    }

                    $resourceNameTemp = explode('.',$resourceName);
                    $resourceExtension = array_pop($resourceNameTemp);
                    $resourceMinifiedName = implode('.',array_merge($resourceNameTemp,['min',$resourceExtension]));
                    $minifiedAddress = $resourcePath.'/'.$resourceMinifiedName;
                    //Minify into a folder if asked
                    if($minifyToFolder){

                        if($minifyToFolder[0] === '/'){
                            $minifyToFolder = substr($minifyToFolder,1);
                            $minifiedFolderPath = $currentRoot.$minifyToFolder;
                            $minifiedAddress = $minifiedFolderPath.'/'.$resourceMinifiedName;
                        }
                        else{
                            $minifiedFolderPath = $resourcePath.'/'.$minifyToFolder;
                        }

                        if(!is_dir($minifiedFolderPath)){
                            if(!$test)
                                mkdir($minifiedFolderPath);
                            if($verbose)
                                echo 'Creating folder '.$minifiedFolderPath.'/'.EOL;
                        }
                    }

                    //If a mutex already exists, or we cannot make one, return
                    $mutex = new \IOFrame\Managers\LockManager($minifiedAddress);
                    if(is_file($minifiedAddress.'_mutex') || !$mutex->makeMutex()){
                        $results[$address] = [
                            'address' => $minifiedAddress,
                            'relativeAddress'=>substr($minifiedAddress,strlen($rootFolder)),
                            'local'=>true,
                            'lastChanged' => time()
                        ];
                        if($verbose){
                            if(is_file($minifiedAddress.'_mutex'))
                                echo 'Mutex already exists! ';
                            echo 'Cannot get mutex on resource! '.$minifiedAddress.EOL;
                        }
                        $mutex->deleteMutex();
                        continue;
                    }

                    //Remember - either of those may not exist
                    $timeMinifiedChanged =  $ignoreTimeChanged ? 0 : (int)@filemtime($minifiedAddress);

                    if($verbose)
                        echo $minifiedAddress.' changed at '.($timeMinifiedChanged?:'0').EOL.$fullAddress.' changed at '.$timeOriginalChanged.EOL;
                    //If the file exists, and was changed later than the minified version, minify it again
                    if($timeOriginalChanged > $timeMinifiedChanged){
                        if($resourceExtension === 'scss'){
                            $newData = $this->compileSCSS($address,array_merge($params,['returnString'=>true]));
                            if(!$newData)
                                continue;
                            $minifier->add($newData);
                        }
                        else{
                            $minifier->add($fullAddress);
                        }
                        if(!$test){
                            try {
                                //Set the file modification time to 1 second ago - to avoid synchronization problems
                                touch($fullAddress, time()-1);
                                $minifier->minify($minifiedAddress);
                            } catch (\Exception $e) {
                                $this->logger->error('Failed to create minified file, exception '.$e->getMessage(),['address'=>$fullAddress,'trace'=>$e->getTrace()]);
                                if($verbose)
                                    echo 'Could not minify file '.$minifiedAddress.', exception: '.$e->getMessage().EOL;
                                $mutex->deleteMutex();
                                continue;
                            }
                        }
                        if($verbose)
                            echo 'Minifying file '.$fullAddress.' to '.$minifiedAddress.EOL;
                        $results[$address] = [
                            'address' => $minifiedAddress,
                            'relativeAddress'=>substr($minifiedAddress,strlen($rootFolder)),
                            'local'=>true,
                            'lastChanged' => time()
                        ];
                    }
                    else{
                        if($verbose)
                            echo 'Minified address '.$minifiedAddress.' is up to date!'.EOL;
                        $results[$address] = [
                            'address' => $minifiedAddress,
                            'relativeAddress'=>substr($minifiedAddress,strlen($rootFolder)),
                            'local'=>true,
                            'lastChanged' => $timeMinifiedChanged
                        ];
                    }
                    //Finally delete the mutex
                    $mutex->deleteMutex();
                }
            }
            else{

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

                    //Check if file exists, if not - try to fall back to default folder
                    if(!$changeTime){
                        if($type === 'js')
                            $currentRoot = $this->settings->getSetting('absPathToRoot').'front/ioframe/js/';
                        else
                            $currentRoot = $this->settings->getSetting('absPathToRoot').'front/ioframe/css/';
                        $fallBack = [];
                        $fallBack['resourcePath'] = explode('/',$address);
                        $fallBack['resourceName'] = array_pop($fallBack['resourcePath']);
                        $fallBack['resourcePath'] = $currentRoot.implode('/',$fallBack['resourcePath']);
                        if($fallBack['resourcePath'][-1] === '/')
                            $fallBack['resourcePath'] = substr($fallBack['resourcePath'],0,-1);
                        $fallBack['fullAddress'] = $fallBack['resourcePath'].'/'.$fallBack['resourceName'];
                        $fallBack['lastChanged'] = @filemtime($fallBack['fullAddress']);
                        if($fallBack['lastChanged']){
                            foreach($fallBack as $paramName => $value){
                                ${$paramName} = $value;
                            }
                        }
                        else{
                            continue;
                        }
                    }
                    $resourceNameTemp = explode('.',$resourceName);
                    $resourceExtension = array_pop($resourceNameTemp);

                    $originalChangeTime = max($changeTime,$originalChangeTime);

                    if($resourceExtension === 'scss'){
                        $newData = $this->compileSCSS($address,array_merge($params,['returnString'=>true]));
                        if(!$newData){
                            continue;
                        }
                        $minifier->add($newData);
                    }
                    else{
                        $minifier->add($fullAddress);
                    }
                    if($verbose)
                        echo 'File '.$fullAddress.' added to minification'.EOL;
                }

                $resourceMinifiedName = $minifyName.'.min.'.$type;
                //Minify into a folder if asked
                if($minifyToFolder!=''){
                    if(!is_dir($rootFolder.$minifyToFolder)){
                        if(!$test){
                            if(!mkdir($rootFolder.$minifyToFolder))
                                $this->logger->error('Failed to create folder during minification',['folder'=>$rootFolder.$minifyToFolder]);
                        }
                        if($verbose)
                            echo 'Creating folder '.$rootFolder.$minifyToFolder.EOL;
                    }
                }
                $resourceMinifiedName = $minifyToFolder.'/'.$resourceMinifiedName;
                $minifiedAddress = $rootFolder.$resourceMinifiedName;


                //If a mutex already exists, or we cannot make one, return
                $mutex = new \IOFrame\Managers\LockManager($minifiedAddress);
                if(is_file($minifiedAddress.'_mutex') || !$mutex->makeMutex()){
                    $results[$minifyName] = [
                        'address' => $minifiedAddress,
                        'relativeAddress'=>substr($minifiedAddress,strlen($rootFolder)),
                        'local'=>true,
                        'lastChanged' => time()
                    ];
                    $mutex->deleteMutex();
                    return $results;
                }

                //Remember - the minified version may not exist
                $timeMinifiedChanged =  $ignoreTimeChanged ? 0 : (int)@filemtime($minifiedAddress);

                //If any of the original files was changed alter than the minified version, and some of them exist, minify
                if($originalChangeTime && ($originalChangeTime > $timeMinifiedChanged)){
                    if(!$test){
                        $minifier->minify($minifiedAddress);
                    }
                    if($verbose)
                        echo 'Files '.implode(',',$addresses).' minified to '.$minifiedAddress.EOL;
                    $results[$minifyName] = [
                        'address' => $minifiedAddress,
                        'relativeAddress'=>substr($minifiedAddress,strlen($rootFolder)),
                        'local'=>true,
                        'lastChanged' => time()
                    ];
                }
                else{
                    if($timeMinifiedChanged){
                        if($verbose)
                            echo 'Minified address '.$minifiedAddress.' is up to date!'.EOL;

                        $results[$minifyName] = [
                            'address' => $minifiedAddress,
                            'relativeAddress'=>substr($minifiedAddress,strlen($rootFolder)),
                            'local'=>true,
                            'lastChanged' => $timeMinifiedChanged
                        ];
                    }
                    else{
                        $this->logger->warning('File does not exist, and neither do the sources',['address'=>$minifiedAddress,'sources'=>$addresses]);
                        if($verbose)
                            echo 'File '.$minifiedAddress.' does not exist, and neither do the sources!'.EOL;
                        $mutex->deleteMutex();
                        return 1;
                    }
                }
            }

            if(!empty($mutex))
                $mutex->deleteMutex();
            return $results;
        }

        /** Returns frontend resource collections and minifies them while at it.
         *
         * @param string $name Collection name
         * @param string $type 'js' or 'css'
         * @param array $params
         *              -- Params of GetFrontendResource and minifyFrontendResource--
         *
         * @return mixed
         * @throws \Exception
         */
        function getFrontendResourceCollection(string $name, string $type, array $params = []){
            return $this->getFrontendResourceCollections([$name],$type,$params)[$name];
        }

        /**Returns frontend resource collections and minifies them while at it.
         *
         * @param string[] $names Collection names
         * @param string $type 'js' or 'css'
         * @param array $params
         *              -- Params of GetFrontendResources and minifyFrontendResources--
         *              'includeGalleryInfo' - false, whether to include gallery info
         * @return array
         * @throws \Exception|SassException
         */
        function getFrontendResourceCollections(array $names, string $type, array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $includeGalleryInfo = $params['includeGalleryInfo'] ?? false;
            $minifyToFolder = $params['minifyToFolder'] ?? 'min';
            //Else get and minify all resources. This wont matter if names are []
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
                            $addresses[] = $address;
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

                    foreach($frontEndResults as $resName => $resultArray){
                        $results[$resName] = $resultArray;
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
        function deleteFrontendResourceCollection(string $name, string $type, array $params = []): int {
            return $this->deleteResourceCollection($name,$type,$params);
        }

        /** Same as addResourceToCollection but always ordered
         */
        function addFrontendResourceToCollection( string $address, string $collection, string $type, array $params = []): array {
            return $this->addFrontendResourcesToCollection([$address], $collection, $type, $params);
        }

        /** Same as addResourcesToCollection but always ordered
         */
        function addFrontendResourcesToCollection( array $addresses, string $collection, string $type, array $params = []): array {
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
        function moveFrontendResourceCollectionOrder(int $from, int $to, string $collection, string $type, array $params): int {
            return $this->moveCollectionOrder($from, $to, $collection, $type, $params);
        }

        /** Same as swapCollectionOrder
         */
        function swapFrontendResourceCollectionOrder(int $num1,int $num2, string $collection, string $type, array $params): int {
            return $this->swapCollectionOrder($num1, $num2, $collection, $type, $params);
        }

        /** Creates a new folder at the resource root
         *
         * @param string $relativeAddress Relative address to resource root
         * @param string $name Folder name
         * @param string $type JS, CSS, IMG at the moment
         * @param array $params
         *          'rootFolder'        - string, Root folder for the local resources (relative to server root!).
         *                                Defaults to resource settings 'jsPathLocal' and 'cssPathLocal'
         * @returns int
         *     -1 - creation error
         *      0 - success
         *      1 - folder already exists
         * @throws \Exception
         * @throws \Exception
         */
        function createFolder(string $relativeAddress, string $name,  string $type,  array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(isset($params['rootFolder']))
                $rootFolder = $params['rootFolder'];
            else{
                if($type === 'js')
                    $rootFolder = $this->resourceSettings->getSetting('jsPathLocal');
                elseif($type === 'css')
                    $rootFolder = $this->resourceSettings->getSetting('cssPathLocal');
                elseif($type === 'img')
                    $rootFolder = $this->resourceSettings->getSetting('imagePathLocal');
                elseif($type === 'vid')
                    $rootFolder = $this->resourceSettings->getSetting('videoPathLocal');
                else
                    throw new \Exception('Invalid front-end resource type!');
            }
            $rootFolder = $this->settings->getSetting('absPathToRoot').$rootFolder;
            if($relativeAddress !== '' && $relativeAddress[strlen($relativeAddress)-1] !== '/')
                $relativeAddress .= '/';

            $addressToCreate = $rootFolder.$relativeAddress.$name;
            if(is_dir($addressToCreate))
                return 1;

            if($verbose)
                echo 'Creating folder '.$addressToCreate.EOL;
            if(!$test)
                $folderCreation = @mkdir($addressToCreate,0777,true);
            else
                $folderCreation = true;

            if(!$folderCreation)
                return -1;

            return 0;
        }

        /*------------------------------------------------ HERE BE JS ------------------------------------------------*/

        /**  getFrontendResources with type 'js'
         */
        function getJS(array $addresses = [], array $params = []){
            return $this->getFrontendResources($addresses,'js',$params);
        }

        /**  moveFrontendResourceFiles with type 'js'
         */
        function moveJSFile(string $src, string $dest,  array $params = []): int {
            return $this->moveFrontendResourceFile($src, $dest,'js',$params);
        }

        /**  moveFrontendResourceFiles with type 'js'
         */
        function moveJSFiles(array $inputs,  array $params = []): int {
            return $this->moveFrontendResourceFiles($inputs,'js',$params);
        }

        /**  deleteFrontendResourceFile with type 'js'
         */
        function deleteJSFile(string $address,  array $params = []): int {
            return $this->deleteFrontendResourceFile($address,'js',$params);
        }

        /**  deleteFrontendResourceFiles with type 'js'
         */
        function deleteJSFiles(array $addresses,  array $params = []): int {
            return $this->deleteFrontendResourceFiles($addresses,'js',$params);
        }

        /** Same as incrementResourceVersion
         */
        function incrementJSVersion(string $address, array $params = []): int {
            return $this->incrementFrontendResourceVersion($address, 'js', $params);

        }

        /** Same as incrementResourcesVersions
         */
        function incrementJSVersions(array $addresses, array $params = []): int {
            return $this->incrementFrontendResourceVersions($addresses, 'js', $params);

        }

        /** Same as minifyFrontendResource with type 'js'
         */
        function minifyJSFile( string $address, array $params = []){
            return $this->minifyFrontendResource($address, 'js',$params);
        }

        /** Same as minifyFrontendResources with type 'js'
         */
        function minifyJSFiles( array $addresses, array $params = [] ): array|int {
            return $this->minifyFrontendResources($addresses, 'js',$params);
        }

        /** Same as getFrontendResourceCollection
         */
        function getJSCollection(string $name, array $params = [] ){
            return $this->getFrontendResourceCollection($name, 'js', $params);
        }
        /** Same as getFrontendResourceCollections;
         */
        function getJSCollections(array $names, array $params = [] ): array {
            return $this->getFrontendResourceCollections($names, 'js', $params);
        }

        /** Same as setFrontendResourceCollection
         */
        function setJSCollection(string $name, string $meta = null, array $params = []){
            return $this->setFrontendResourceCollection($name,'js',$meta,$params);
        }

        /** Same as deleteFrontendResourceCollection
         */
        function deleteJSCollection(string $name, array $params = []): int {
            return $this->deleteFrontendResourceCollection($name,'js',$params);
        }

        /** addFrontendResourceToCollection wrapper
         */
        function addJSFileToCollection( string $address, string $collection, array $params = []): array {
            return $this->addFrontendResourceToCollection($address,$collection, 'js', $params);
        }


        /** addFrontendResourcesToCollection wrapper
         */
        function addJSFilesToCollection( array $addresses, string $collection, array $params = []): array {
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
        function moveJSCollectionOrder(int $from, int $to, string $collection, array $params = [] ): int {
            return $this->moveFrontendResourceCollectionOrder($from, $to, $collection, 'js', $params);
        }

        /** swapFrontendResourceCollectionOrder wrapper
         */
        function swapJSCollectionOrder(int $num1,int $num2, string $collection, array $params = [] ): int {
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
        function moveCSSFile(string $src, string $dest,  array $params = []): int {
            return $this->moveFrontendResourceFile($src, $dest,'css',$params);
        }

        /**  moveFrontendResourceFiles with type 'css'
         */
        function moveCSSFiles(array $inputs,  array $params = []): int {
            return $this->moveFrontendResourceFiles($inputs,'css',$params);
        }

        /**  deleteFrontendResourceFile with type 'css'
         */
        function deleteCSSFile(string $address,  array $params = []): int {
            return $this->deleteFrontendResourceFile($address,'css',$params);
        }

        /**  deleteFrontendResourceFiles with type 'css'
         */
        function deleteCSSFiles(array $addresses,  array $params = []): int {
            return $this->deleteFrontendResourceFiles($addresses,'css',$params);
        }

        /** Same as incrementResourceVersion
         */
        function incrementCSSVersion(string $address, array $params = []): int {
            return $this->incrementFrontendResourceVersion($address, 'css', $params);

        }

        /** Same as incrementResourcesVersions
         */
        function incrementCSSVersions(array $addresses, array $params = []): int {
            return $this->incrementFrontendResourceVersions($addresses,'css', $params);

        }

        /** Same as minifyFrontendResource with type 'css'
         */
        function minifyCSSFile( string $address, array $params = []){
            return $this->minifyFrontendResource($address, 'css',$params);
        }

        /** Same as minifyFrontendResources with type 'css'
         */
        function minifyCSSFiles( array $addresses, array $params = [] ): array|int {
            return $this->minifyFrontendResources($addresses, 'css',$params);
        }

        /** Same as getFrontendResourceCollection
         */
        function getCSSCollection(string $name, array $params = [] ){
            return $this->getFrontendResourceCollection($name, 'css', $params);
        }
        /** Same as getFrontendResourceCollections;
         */
        function getCSSCollections(array $names, array $params = [] ): array {
            return $this->getFrontendResourceCollections($names, 'css', $params);
        }


        /** Same as the JS version but with CSS
         */
        function setCSSCollection(string $name, string $meta = null , array $params = []){
            return $this->setFrontendResourceCollection($name,'css',$meta,$params)[$name];
        }

        /** Same as deleteFrontendResourceCollection
         */
        function deleteCSSCollection(string $name, array $params = []): int {
            return $this->deleteFrontendResourceCollection($name,'css',$params);
        }

        /** addFrontendResourceToCollection wrapper
         */
        function addCSSFileToCollection( string $address, string $collection, array $params = []): array {
            return $this->addFrontendResourceToCollection($address,$collection, 'css', $params);
        }

        /** addFrontendResourcesToCollection wrapper
         */
        function addCSSFilesToCollection( array $addresses, string $collection, array $params = []): array {
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
        function moveCSSCollectionOrder(int $from, int $to, string $collection, array $params = [] ): int {
            return $this->moveFrontendResourceCollectionOrder($from, $to, $collection, 'css', $params);
        }

        /** swapFrontendResourceCollectionOrder wrapper
         */
        function swapCSSCollectionOrder(int $num1,int $num2, string $collection, array $params = [] ): int {
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
         *                             If it starts with '/', will be relative to root folder.
         *           'returnString' - bool, default false - If passed, will only return the compiled string on success, and
         *                            false on failure.
         *
         * @return array | string | bool
         * of the form
         *      [
         *              'newName' => <new Name> | '',
         *              'newAddress' => <new address> | '',
         *      ]
         *  Empty string if the compilation fails
         * @throws SassException
         * @throws SassException
         */
        function compileSCSS(string $address, array $params = []): array|bool|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $returnString = $params['returnString']?? false;
            $checkExisting = $params['checkExisting'] ?? !$returnString;
            $compileToFolder = $params['compileToFolder'] ?? '';

            $rootFolder = $params['rootFolder'] ?? $this->resourceSettings->getSetting('cssPathLocal');
            $rootFolder = $this->settings->getSetting('absPathToRoot').$rootFolder;

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
            }

            //Get export folder
            if(!$compileToFolder)
                $exportFolder = $importFolder;
            else{

                if($compileToFolder[0] === '/'){
                    $compileToFolder = substr($compileToFolder,1);
                    $exportFolder = $rootFolder.$compileToFolder;
                }
                else{
                    $exportFolder = $importFolder.$compileToFolder;
                }

                if(!is_dir($exportFolder)){
                    if(!$test)
                        mkdir($exportFolder);
                    if($verbose)
                        echo 'Creating directory '.$exportFolder.EOL;
                }
            }
            if($exportFolder[-1] === '/' )
                $exportFolder = substr($exportFolder,0,-1);

            // Extract the new name
            $originName = $resourcePath[$resourceFoldersCount];
            $newName = explode('.',$originName);
            $newName[1] = 'css';
            $newName = implode('.',$newName);

            // Extract the relative path
            $newPath = explode('/',substr($exportFolder,strlen($rootFolder)));
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
                    return $returnString ? false : $resultAddress;
            }

            $scss = new ScssPhp\Compiler();
            $scss->setImportPaths($importFolder);
            try{
                $compiled = $scss->compileString('@import "'.$originName.'";')->getCss();
            }
            catch (\Exception $e){
                $this->logger->error('Failed to compile SCSS, exception '.$e->getMessage(),['address'=>$address,'params'=>$params,'trace'=>$e->getTrace()]);
                if($verbose)
                    echo 'Failed to compile SCSS, exception '.$e->getMessage().EOL;
                return $returnString ? false : $resultAddress;
            }
            if($compiled){
                if(!$returnString){
                    if(!$test){
                        if(!\IOFrame\Util\FileSystemFunctions::file_force_contents($exportAddress,$compiled))
                            $this->logger->error('Failed to write new SCSS into file',['address'=>$address,'newAddress'=>$exportAddress,'params'=>$params]);
                    }
                    if($verbose)
                        echo 'Compiling SCSS into '.$exportAddress;
                    $resultAddress = [
                        'newName'=>$newPath,
                        'newAddress'=>$exportAddress
                    ];
                }
                else{
                    if($verbose)
                        echo 'SCSS successfully compiled'.EOL;
                }
            }

            return $returnString ? $compiled : $resultAddress;
        }

        /*---------------------------------------------- HERE BE IMAGES ----------------------------------------------*/

        /** getFrontendResources with type 'css'
         */
        function getImages(array $addresses = [], array $params = []){
            return $this->getFrontendResources($addresses,'img',$params);
        }

        /**  moveFrontendResourceFiles with type 'css'
         */
        function moveImage(string $src, string $dest,  array $params = []): int {
            return $this->moveFrontendResourceFile($src, $dest, 'img',$params);
        }

        /**  moveFrontendResourceFiles with type 'css'
         */
        function moveImages(array $inputs,  array $params = []): int {
            return $this->moveFrontendResourceFiles($inputs,'img',$params);
        }

        /**  deleteFrontendResourceFile with type 'css'
         */
        function deleteImage(string $address,  array $params = []): int {
            return $this->deleteImages([$address],$params);
        }

        /**  deleteFrontendResourceFiles with type 'css'
         */
        function deleteImages(array $addresses,  array $params = []): int {
            return $this->deleteFrontendResourceFiles($addresses,'img',$params);
        }

        /** Same as incrementResourceVersion
         */
        function incrementImage(string $address, array $params = []): int {
            return $this->incrementImages([$address], $params);
        }

        /** Same as incrementResourcesVersions
         */
        function incrementImages(array $addresses, array $params = []): int {
            return $this->incrementResourcesVersions($addresses,'img', $params);
        }

        /** Same as getFrontendResourceCollection
         */
        function getGallery(string $name, array $params = [] ): array {
            return $this->getGalleries([$name], $params);
        }
        /** Same as getFrontendResourceCollections;
         */
        function getGalleries(array $names, array $params = [] ): array {
            return $this->getFrontendResourceCollections($names, 'img', $params);
        }

        /** Same as setResourceCollections
         */
        function setGallery(string $name, string $meta = null , array $params = []){
            return $this->setFrontendResourceCollection($name,'img',$meta,$params);
        }

        /** Same as deleteFrontendResourceCollection
         */
        function deleteGallery(string $name, array $params = []): int {
            return $this->deleteFrontendResourceCollection($name,'img',$params);
        }

        /** Same as addResourceToCollection but always ordered
         */
        function addImageToGallery( string $address, string $collection, array $params = []): array {
            return $this->addImagesToGallery([$address], $collection, $params);
        }

        /** Same as addResourcesToCollection but always ordered
         */
        function addImagesToGallery( array $addresses, string $collection, array $params = []): array {
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
        function moveGalleryOrder(int $from, int $to, string $collection, array $params): int {
            return $this->moveFrontendResourceCollectionOrder($from, $to, $collection, 'img', $params);
        }

        /** Same as swapCollectionOrder
         */
        function swapGalleryOrder(int $num1,int $num2, string $collection, array $params): int {
            return $this->swapFrontendResourceCollectionOrder($num1, $num2, $collection, 'img', $params);
        }

        /*---------------------------------------------- HERE BE Videos ----------------------------------------------*/

        /** getFrontendResources with type 'css'
         */
        function getVideos(array $addresses = [], array $params = []){
            return $this->getFrontendResources($addresses,'vid',$params);
        }

        /**  moveFrontendResourceFiles with type 'css'
         */
        function moveVideo(string $src, string $dest,  array $params = []): int {
            return $this->moveFrontendResourceFile($src, $dest, 'vid',$params);
        }

        /**  moveFrontendResourceFiles with type 'css'
         */
        function moveVideos(array $inputs,  array $params = []): int {
            return $this->moveFrontendResourceFiles($inputs,'vid',$params);
        }

        /**  deleteFrontendResourceFile with type 'css'
         */
        function deleteVideo(string $address,  array $params = []): int {
            return $this->deleteVideos([$address],$params);
        }

        /**  deleteFrontendResourceFiles with type 'css'
         */
        function deleteVideos(array $addresses,  array $params = []): int {
            return $this->deleteFrontendResourceFiles($addresses,'vid',$params);
        }

        /** Same as incrementResourceVersion
         */
        function incrementVideo(string $address, array $params = []): int {
            return $this->incrementVideos([$address], $params);
        }

        /** Same as incrementResourcesVersions
         */
        function incrementVideos(array $addresses, array $params = []): int {
            return $this->incrementResourcesVersions($addresses,'vid', $params);
        }

        /** Same as getFrontendResourceCollection
         */
        function getVideoGallery(string $name, array $params = [] ): array {
            return $this->getVideoGalleries([$name], $params);
        }
        /** Same as getFrontendResourceCollections;
         */
        function getVideoGalleries(array $names, array $params = [] ): array {
            return $this->getFrontendResourceCollections($names, 'vid', $params);
        }

        /** Same as setResourceCollections
         */
        function setVideoGallery(string $name, string $meta = null , array $params = []){
            return $this->setFrontendResourceCollection($name,'vid',$meta,$params);
        }

        /** Same as deleteFrontendResourceCollection
         */
        function deleteVideoGallery(string $name, array $params = []): int {
            return $this->deleteFrontendResourceCollection($name,'vid',$params);
        }

        /** Same as addResourceToCollection but always ordered
         */
        function addVideoToVideoGallery( string $address, string $collection, array $params = []): array {
            return $this->addVideosToVideoGallery([$address], $collection, $params);
        }

        /** Same as addResourcesToCollection but always ordered
         */
        function addVideosToVideoGallery( array $addresses, string $collection, array $params = []): array {
            return $this->addFrontendResourcesToCollection($addresses, $collection, 'vid', $params);
        }

        /** Same as removeResourceFromCollection
         */
        function removeVideoFromVideoGallery( string $address, string $collection, array $params = []){
            return $this->removeVideosFromVideoGallery([$address], $collection, $params);
        }

        /** Same as removeResourcesFromCollection
         */
        function removeVideosFromVideoGallery( array $addresses, string $collection, array $params = []){
            return $this->removeFrontendResourcesFromCollection($addresses, $collection, 'vid', $params);
        }

        /** Same as moveCollectionOrder
         */
        function moveVideoGalleryOrder(int $from, int $to, string $collection, array $params): int {
            return $this->moveFrontendResourceCollectionOrder($from, $to, $collection, 'vid', $params);
        }

        /** Same as swapCollectionOrder
         */
        function swapVideoGalleryOrder(int $num1,int $num2, string $collection, array $params): int {
            return $this->swapFrontendResourceCollectionOrder($num1, $num2, $collection, 'vid', $params);
        }

    }
}