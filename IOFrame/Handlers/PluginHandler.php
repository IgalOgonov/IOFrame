<?php
namespace IOFrame\Handlers{

    define('IOFrameHandlersPluginHandler',true);

    /**  This class handles every action related to plugins.
     *
     *  This class does:
     *  Returning all available plugins
     *  Displaying information about active (installed) plugins
     *  Installing and uninstalling plugins
     *  Various changes to plugin order
     *
     * Before I describe the structure of a legal plugin folder, I will describe the "settings" file /localFiles/plugins.
     * This file stores information about installed plugins.
     * The fileName of each plugin is the name of its folder inside /plugins/. It can also have a prettier name in its meta files.
     * The maximum length of a fileName is 64 characters. The fileName must only contain word characters (that match \w regex).
     *
     * I will describe the structure of a legal plugin folder, and the format of each file:
     *
     *  ------------quickInstall.php---------------- | REQUIRED (OPTIONAL if fullInstall.php is present)
     *  This file will be included during the quick plugin installation, and will be executed procedurally.
     *  This file will have been included after a user supplied array $options. Those options will be matched
     *  against the installOptions.json file, and each legal option will be available to the installer.
     *  IF ANY unhandled exception is thrown in this file, instead of crushing the handler will stop and execute quickUninstall.php
     *  Remember to clean up resources like fopen file handlers.
     *
     *  ------------fullInstall.php---------------- | OPTIONAL (REQUIRED if quickInstall.php is absent)
     *  This file is an optional installer - if it exists, the user will have an option to be redirected to this page to install
     *  the plugin. It's up to the author to write everything manually in this case - from the front end to using this class
     *  and its functions to install the plugin.
     *  However, this also allows you much more freedom - you can  filter the users installation options more
     *  thoroughly, and you can allow a higher variety of said options to begin with, and style it however you like.
     *
     *  ------------quickUninstall.php-------------- | REQUIRED
     *  This file will be included and procedurally executed either when the user uninstalls the plugin, or if install fails
     *  at some point and throws an exception.
     *  It is important to remember that not everything you need to uninstall exists - and write the file accordingly. This file will have been
     *  included after a user supplied array $options. Those options will be matched against the uninstallOptions.json file,
     *  and each legal option will be available to the installer.
     *  Remember to remove EVERYTHING you installed, unless the user has specified otherwise in the options.
     *
     *  ------------fullUninstall.php---------------- | OPTIONAL (REQUIRED if quickUninstall.php is absent)
     *  This file is an optional uninstaller - if it exists, the user will have an option to be redirected to this page to uninstall
     *  the plugin. It's up to the author to write everything manually in this case - from the front end to using this class
     *  and its functions to uninstall the plugin.
     *  However, this also allows you much more freedom - you can  filter the users uninstall options more
     *  thoroughly, and you can allow a higher variety of said options to begin with, and style it however you like.
     *
     *  ------------(install|uninstall)Options.json--------------- | OPTIONAL
     *  This file is to provide the plugin writer with ways of accepting and filtering user options for the quick installation.
     *  It is a JSON string of the format (content inside square brackets is optional):
     * {"<Option Name>": {
     *                  "name":"<Pretty Option Name>",`- Numbers, Letters, Underscore, Whitespace - 255 characters
     *                  "type":"<Valid Type>",
     *                  ["list": {                   -------- In case of radio/checkbox/select
     *                          "name1":"value1",
     *                          "name2":"value2"
     *                           ...
     *                          }],
     *                  ["desc":"<text>"],
     *                  ["placeholder":"<text>"],
     *                  ["optional":<boolean>]
     *                  ["maxLength":<number>],       ------- Char count. Default 20000, max 1000000. Used to prevent DOS and possible SO
     *                  ["maxNum":<number>]            ------- Max and min number size, used to prevent SO. Defult PHP_INT_MAX;
     *                  }
     * }
     *  - <Option Name> is a name against which the user-fed option will be checked. So for example, if the user is expected
     *  to provide an option named "hasDoor", that will be the name.
     *  - "name" is the "pretty" name of the option, displayed to the user client-side. Numbers, letters and whitespace.
     *  - "desc" is an optional description of the option for the user, displayed to him client side. Any text.
     *  - "placeholder" will add a placeholder attribute to the generated HTML tag - client side. Numbers, Latters and whitespace.
     *  - "type" is 'number', 'text', 'textarea','radio','checkbox','select','email', or 'password'.
     *  - "optional" is true or false (0 or 1 should work too), and specifies whether the option is required or not.
     *  Note that this filtering might not be enough in some cases - the plugin writer is free to do any additional filtering
     *  in "fullInstall.php" itself. This is mainly meant to let the front-end know which options to generate for the user
     *  during (un)installation.
     *  Also, in case a more complex setup is needed, writers can always do it inside their plugin after this initial install.
     *
     *  Example:
     *  * {"size": {
     *                  "name":"Apartment Size",
     *                  "type":"number",
     *                  "desc":"Choose approximate apartment size in sq meters",
     *                  }
     *     "color": {
     *                  "name":"Outer walls color",
     *                  "type":"text",
     *                  "desc":"Describe the outer walls color",
     *                  "placeholder":"Your description goes here"
     *                  }
     *     "text":  {   "name":"Notes"
     *                  "type":"bigText"
     *                  }
     *     "rooms":  {   "name":"Rooms"
     *                  "type":"radio"
     *                  "desc":"Choose the number of rooms",
     *                  "list": {
     *                          "Single room":"1r"
     *                          "Two rooms":"2r"
     *                          }
     *                  }
      *     "opt":  {   "name":"[Additional] Rent:"           --------- The main name IS NOT the value returned
     *                  "type":"checkbox"                     --------- Returns an array where if "vehicle1" was
     *                  "desc":"Rent additional vehicles",    --------- checked, the array would be
     *                  "list": {                             --------- {'vehicle1':true, 'vehicle2':false}
     *                          "Car":"vehicle1",
     *                          "Bike":"vehicle2"
     *                          },
     *                  "optional":true
     *                  }
     * }
     *
     * Will accept an option by the name of "size" that is a number, an option named "color" that is a text consisting of only
     * letters, and an option named "text" that can by anything.
     * Rooms will be a choice between 2 radio buttons, and 2 additional options will be available to mark down.
     * Note that inside "install", the plugin writer may sanitize "text", or any other option for that matter.
     *
     *  ------------definitions.php------------ | OPTIONAL
     *  Any definitions which you want your plugin to add to the WHOLE system at start (not just after you include include.php,
     *  or after whatever new files you created are included), go here in a JSON format of {"Definition":"Value"}.
     *  They will be added on quick install into the system definition.json file, and removed from there at the uninstall.
     *  You SHOULD start every definition with <PLUGIN_NAME>+underscore. Aka for testPlugin, start definitions with "TESTPLUGIN_"
     *
     *  ------------include.php---------------- | OPTIONAL
     *  This file will be included to run at the end of utilCore.php - only for an active plugin!
     *  Plugins are included in the same order they appear at /localFiles/plugins, unless they are explicitly added to
     *  /plugins/order - a simple text file of the format "<Plugin Name 1>, <Plugin Name 2>, ..." that will specify
     *  specific plugins that need to be run first, and in a specific order.
     *
     *  ------------meta.json------------------ | REQUIRED
     *  This file is a JSON of the format:
     * {
     *  "name":<Plugin Name>,
     *  "version":<Plugin Version>[,
     *  "summary":<A short plugin summary>,][
     *  "description":<A full plugin description>]
     * }
     *  "name" is the Plugin Name to be displayed to the user - Numbers, Latters and whitespace.
     *  "version" is a SINGLE NUMBER (no dots)
     *
     *  ------------dependencies.json---------- | OPTIONAL
     *  This file is a JSON of the format:
     * {"fileName":{
     *              "minVersion":number
     *              [,"maxVersion":number]
     *              },
     * "fileName2":{
     *              "minVersion":number
     *              [,"maxVersion":number]
     *              },
     * etc...
     * }
     * "fileName" is the real name of a plugin. For example, for the test plugin, it would be "testPlugin"
     * "min/maxVersion" are the minimum and maximum versions of said plugin that will satisfy the dependency.
     *  For example, if you want our plugin depending on the "objects" plugin version 1, but not a higher version
     *  than 20 (e.g. it breaks backwards compatibility) the file will be:
     * {"objects":{
     *              "minVersion":1,
     *              "maxVersion":20
     *            }
     * }
     *
     *  ------------update.php---------------- | OPTIONAL
     *  This file will be included if a user chooses to update, and will be executed procedurally.
     *  This file will have been included after the plugin current information is loaded into $plugInfo, which also contains the dependencies.
     *  A variable with the current plugin version ($currentVersion) will also be passed, as well as $targetVersion (version to which the plugin should update).
     *  By default,$targetVersion is one plus of the range the plugin's version is matching (explained in update.json), and is what $currentVersion becomes on a successful iteration.
     *  The above can be changed if this script explicitly changes  $targetVersion.
     *  Also by default, after a single successful iteration, if the new version matches the next range, the plugin will keep installing the next one,
     *  and repeat this process until it has no more ranges to match.
     *  IF ANY unhandled exception is thrown in this file, instead of crushing the handler will stop and execute updateFallback.php.
     *  It is a **VERY** good idea to create temporary local back-ups (in localFiles/temp, typically) of every local file or table variable
     *  changed during each update iteration, so that updateFallback.php can use them on a failed update - they can be deleted on a successful one.
     *
     *  ------------updateFallback.php-------------- | OPTIONAL (REQUIRED if update.php is present)
     *  This file will be included and procedurally executed if the update fails at some point and throws an exception.
     *  The variables $currentVersion and  $targetVersion are passed by the update function, and is automatically calculated (the upper range to which the update failed, plus one).
     *  It's up to updateFallback.php to clean up the failed update at that range based on the above 2 variables, without
     *  leaving behind any mess or damaging the potential already-successful updates.
     *
     *  ------------update.json------------------ | OPTIONAL (REQUIRED if update.php is present)
     *  This file is a JSON array of ranges the format:
     * [
     *  <int, one specific version that can be updated to the next version>
     *  OR
     *  <int[], update range of the form [<int, lowest version for this range>,<int, highest version for this range>]>
     * ]
     * Each version/range represents the version that can be updated.
     * A single version represents a possible version the plugin needs to be in order to be updated, while a range represents
     * a range of versions.
     * From here, a single version will be treated as a range where 2 of the numbers are the same, and also called "range".
     * The list needs to be sorted (by the range minimal version), but wont be validated (as at worst, the site admin wont be able to update the plugin when needed).
     * A plugin can be updated if its version (from meta.json) is inside one of the ranges - otherwise, it cannot be updated.
     * Also, any update iteration that would break one of the plugins dependencies (if any of those have a "maxVersion") would also fail.
     * The plugin's version will always be updated to one above the range it matches, unless explicitly changed by the update script.
     * If the new version matches the next range, it will (by default) keep updating until it reaches the end of the chain of matches.
     * If the chain of versions is broken in a few places, it likely means you need to completely reinstall the plugin
     * to get it to its latest version.
     *
     *  ------------icon.png|jpg|bmp|gif------- | OPTIONAL
     *  The icon is meant to represent the plugin in a small list - 64x64
     *
     *  ------------thumbnail.png|jpg|bmp|gif-- | OPTIONAL
     *  This thumbnail is meant to represent the plugin in a larger list - 256x128
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class PluginHandler extends \IOFrame\Abstract\DBWithCache{

        //Internal constants
        const PLUGIN_HAS_QUICK_UNINSTALL = 'quick';
        const PLUGIN_HAS_FULL_UNINSTALL = 'full';
        const PLUGIN_HAS_BOTH_UNINSTALL = 'both';
        const PLUGIN_HAS_QUICK_INSTALL = 'quick';
        const PLUGIN_HAS_FULL_INSTALL = 'full';
        const PLUGIN_HAS_BOTH_INSTALL = 'both';
        const PLUGIN_HAS_ICON = 1;
        const PLUGIN_HAS_THUMBNAIL = 2;
        const PLUGIN_HAS_ICON_THUMBNAIL = 3;

        //External constants
        public const PLUGIN_FOLDER_NAME = 'plugins/';
        public const PLUGIN_FOLDER_PATH = '';
        public const PLUGIN_IMAGE_FOLDER = 'front/ioframe/img/pluginImages/';

        private \IOFrame\Managers\OrderManager $OrderManager;

        /**
         * @var Int Tells us for how long to cache stuff by default.
         * 0 means indefinitely.
         */
        protected mixed $cacheTTL = 3600;

        /* Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param object $settings The standard settings object
         * @param object $conn The standard DB connection object
         * */

        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = []){

            parent::__construct($settings,array_merge($params,['logChannel'=>\IOFrame\Definitions::LOG_PLUGINS_CHANNEL]));

            //Create new order handler
            $params['name'] = 'plugin';
            $params['localURL'] =  $this->settings->getSetting('absPathToRoot').'/localFiles';
            $params['tableName'] =  'CORE_VALUES';
            $params['columnNames'] = [
                0 => 'tableKey',
                1 => 'tableValue'
            ];
            $this->OrderManager = new \IOFrame\Managers\OrderManager($settings,$params);
        }

        /** Gets available plugins
         *
         * Returns an array of all of the plugins who's folder lies in /plugins. If they follow the correct structure of a plugin -
         * aka, having at least full/quickInstall.php, quickUninstall.php and a correctly formatted meta.json, they are
         * legal. Else they are illegal. The possible statuses are "legal" and "illegal".
         * If $name is specified, only checks the specified folder - if it even exists.
         * Also checks /localFiles/plugins/settings. If a plugin exists there, but either doesn't exist or is illegal
         *
         * @param array $params Object array of the form:
         *          name => Name of the plugin you want to check. If isn't provided (or empty),
         *                  will return an array of all plugins.
         *
         * @return array
         * All available plugins, in the format ["pluginName"=>"<Status>", ...].
         * If a name is specified, returns 1 item in the array, with:
         * "illegal" if the plugin folder is of improper format
         * "absent"if there is a plugin listed as installed, but does not exist or is illegal
         * "legal" if the plugin folder is of proper format
         * "active" if the plugin folder is of proper format and it is listed as installed
         * @throws \Exception
         * @throws \Exception
         */
        function getAvailable(array $params = []): array {

            //Set defaults
            $name = $params['name'] ?? '';

            $res = array();
            $plugList = new \IOFrame\Handlers\SettingsHandler($this->settings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/plugins/');  //Listed plugins

            $url = $this->settings->getSetting('absPathToRoot').self::PLUGIN_FOLDER_PATH.self::PLUGIN_FOLDER_NAME;   //Plugin folder
            $folderUrls = array();                                              //Just the folders in plugin folder
            if($name == ''){
                $dirArray = scandir($url);                                          //All files in plugin folder
                foreach($dirArray as $fileUrl){
                    if(is_dir ($url.$fileUrl) && $fileUrl!='.' && $fileUrl!='..' && (preg_match('/\W/',$fileUrl)<1)){
                        $folderUrls[$fileUrl] = $url.$fileUrl;
                    }
                }
            }
            else
                if(is_dir ($url.$name) && (preg_match('/\W/',$name)<1)){
                    $folderUrls[$name] = $url.$name;
                }
            //For each plugin, check if it's legal, then check if it exists in the json list
            foreach($folderUrls as $pluginName => $url){
                $legal = $this->validatePlugin($url,$params);
                $res[$pluginName] = $legal ? 'legal': 'illegal';
            }
            //'absent' if there is a plugin listed as active, but does not exist
            //Change legal to active if it's installed on the list
            if(count($plugList->getSettings())>0 && $name == ''){
                foreach($plugList->getSettings() as $plugin => $pluginArr){
                    if(!(array_key_exists($plugin,$res) || !\IOFrame\Util\PureUtilFunctions::is_json($pluginArr)))
                        $res[$plugin] = 'absent';
                    else{
                        $status = json_decode($pluginArr,true)['status'];
                        if($status == 'installed' && $res[$plugin] == 'legal')
                            $res[$plugin] = 'active';
                    }
                }
            }
            else if ($name != ''){
                $pluginArr = $plugList->getSetting($name);
                if(\IOFrame\Util\PureUtilFunctions::is_json($pluginArr) && json_decode($pluginArr,true)['status'] == 'installed' && isset($res[$name]))
                    if($res[$name] == 'legal')
                        $res[$name] = 'active';
            }
            if($name != '' && count($res) == 0){
                $res[$name] = 'absent';
            }
            return $res;
        }

        /** Gets plugin info
         *
         * If $name isn't specified, returns a list (2D array) of plugins, which consist of - Available plugins + Active plugins listed at localFiles/plugins.
         * It is important to note that unless a plugin's folder was removed or critically altered before the plugin was uninstalled,
         * the Active plugins should be a subset of the Available+legal plugins. The format is [<Plugin Name>][<Plugin Info as JSON>]
         * If $name is specified, returns a single plugin's info, as a 2D assoc array of size 1 of the following format
         * $res[0] =
         * {
         *   "fileName":<Name of folder or listed in /localFiles/plugins>
         *   "status": <active/legal/illegal/absent/zombie/installing>,
         *   "name": <Plugin Name>,
         *   "version": <Plugin Version>,
         *   ["summary": <Summary of the plugin>,]
         *   ["description": <A full description of the plugin>,]
         *   ["currentVersion": <The current version of an installed plugin>,]
         *   "icon": <image type>,
         *   "thumbnail": <image type>,
         *   "uninstallOptions": <JSON string>
         *   "installOptions": <JSON string>
         *   "hasUpdateFiles": <bool, true or false, depending on whether the update files and updateRanges if of the valid format>
         *   ["updateRanges": ARRAY of ranges]
         * }
         *
         *  The status can be:
         * "active" (if it is both legal and listed "installed"),
         * "legal" (as in getAvailable)
         * "illegal" (as in getAvailable - wont have other info)
         * "absent" (as in getAvailable - wont have other info)
         * "zombie" (if uninstall has started, but wasn't finished - shouldn't appear not during runtime- wont have other info),
         * "installing" (self explanatory).
         *
         *  "icon"/"thumbnail" are only present if the status is "available" or "active", and are a mix of the info in meta.json and
         *  whether or not an icon and/or a thumbnail are present.
         *
         *  "options" is present if the status is "available", and the plugin has an installOptions.json file in its folder. This
         *  specific options file is meant for install purposes only.
         *
         * @param array $params of the form:
         *          'name' => Name of the plugin you want to get. If isn't provided, will return an array of all plugins.
         *
         * @return array
         * If $name is specified, returns an array of the format described above.
         * Else, returns an array where each element is such an array.
         * @throws \Exception
         * @throws \Exception
         */
        function getInfo(array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            isset($params['name'])?
                $name = $params['name'] : $name = '';

            $res = array();
            $plugList = new \IOFrame\Handlers\SettingsHandler($this->settings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/plugins/');  //Listed plugins
            $url = $this->settings->getSetting('absPathToRoot').self::PLUGIN_FOLDER_PATH.self::PLUGIN_FOLDER_NAME;   //Plugin folder
            $names = array();
            //Single plugin case
            if($name != ''){
                $names[0] = $name;
            }
            else{
                $tempArr = array_merge($this->getAvailable(),$plugList->getSettings());
                $tempCounter = 0;
                foreach($tempArr as $key=>$val){
                    $names[$tempCounter] = $key;
                    $tempCounter++;
                }
            }
            foreach($names as $num=>$name){
                $res[$num] = $this->getAvailable(['name'=>$name]);
                $res[$num]['fileName'] = $name;
                $res[$num]['status'] = $res[$num][$name];
                if($res[$num]['status'] === 'active')
                    $res[$num]['currentVersion']= (int)(json_decode($plugList->getSetting($name),true)['version']);
                if($res[$num][$name] == 'absent' || $res[$num][$name] == 'illegal'){
                    //Do nothing else if the plugin is absent or illegal
                }
                else{
                    $installOpt = 'installOptions';
                    $uninstallOpt = 'uninstallOptions';
                    $fileUrl = $url.$name;
                    $LockManager = new \IOFrame\Managers\LockManager($fileUrl);

                    //Get the meta data and update it
                    $meta = json_decode(\IOFrame\Util\FileSystemFunctions::readFileWaitMutex($fileUrl,'meta.json'),true);
                    // Important to escape using htmlspecialchars, in case meta.json contains some nasty stuff
                    $res[$num]['name']=htmlspecialchars($meta['name']);
                    $res[$num]['version']=(int)$meta['version'];
                    if(isset($meta['summary']))  $res[$num]['summary']=htmlspecialchars($meta['summary']);
                    if(isset($meta['description']))  $res[$num]['description']=htmlspecialchars($meta['description']);
                    //Get current version if installed
                    //Check whether the plugin has the update files
                    if(file_exists($fileUrl.'/update.json')){
                        $updateRanges = json_decode(\IOFrame\Util\FileSystemFunctions::readFileWaitMutex($fileUrl,'update.json'),true);
                        if(
                            file_exists($fileUrl.'/update.php') &&
                            file_exists($fileUrl.'/updateFallback.php') &&
                            $this->validatePluginFile($updateRanges,'updateRanges',['isFile'=>true,'test'=>$test,'verbose'=>$verbose])
                        ){
                            $res[$num]['hasUpdateFiles'] = true;
                            $res[$num]['updateRanges'] = $updateRanges;
                        }
                        else{
                            $res[$num]['hasUpdateFiles'] = false ;
                        }
                    }
                    else
                        $res[$num]['hasUpdateFiles'] = false ;

                    //Start by checking if the plugin has fullInstall, quickInstall, or both - has to have one at least, because it's legal
                    if(file_exists($fileUrl.'/fullUninstall.php')){
                        file_exists($fileUrl.'/quickUninstall.php') ?
                            $res[$num]['uninstallStatus'] = self::PLUGIN_HAS_BOTH_UNINSTALL :
                            $res[$num]['uninstallStatus'] = self::PLUGIN_HAS_FULL_UNINSTALL ;
                    }
                    else{
                        $res[$num]['uninstallStatus'] = self::PLUGIN_HAS_QUICK_UNINSTALL ;
                    }
                    //Same for install
                    if(file_exists($fileUrl.'/fullInstall.php')){
                        file_exists($fileUrl.'/quickInstall.php') ?
                            $res[$num]['installStatus'] = self::PLUGIN_HAS_BOTH_INSTALL :
                            $res[$num]['installStatus'] = self::PLUGIN_HAS_FULL_INSTALL ;
                    }
                    else{
                        $res[$num]['installStatus'] = self::PLUGIN_HAS_QUICK_INSTALL ;
                    }

                    //Now, onto the other files.
                    if($LockManager->waitForMutex()){
                        //open and read meta.json
                        $metaFile = @fopen($fileUrl.'/meta.json',"r") or die("Cannot open");
                        $meta = fread($metaFile,filesize($fileUrl.'/meta.json'));
                        fclose($metaFile);
                        $meta = json_decode($meta,true);
                        $res[$num] = array_merge($res[$num],$meta);
                        //open and read install options if those exist
                        if(file_exists($fileUrl.'/'.$installOpt.'.json') && filesize($fileUrl.'/'.$installOpt.'.json')){
                            $instFile = @fopen($fileUrl.'/'.$installOpt.'.json',"r") or die("Cannot open");
                            $inst = @fread($instFile,filesize($fileUrl.'/'.$installOpt.'.json'));
                            fclose($instFile);
                        }
                        else{
                            $inst = file_exists($fileUrl.'/'.$installOpt.'.json') ? '' : null;
                        }
                        //Install Options exist?
                        if($inst != null)
                            if(\IOFrame\Util\PureUtilFunctions::is_json($inst)){
                                //Ensure options are legal
                                if($this->validatePluginFile(json_decode($inst,true),$installOpt,['isFile'=>true,'test'=>$test,'verbose'=>$verbose]))
                                    $res[$num]['installOptions'] = json_decode($inst,true);
                            }
                        //open and read uninstall options if those exist
                        if(file_exists($fileUrl.'/'.$uninstallOpt.'.json') && filesize($fileUrl.'/'.$uninstallOpt.'.json')){
                            $uninstFile = @fopen($fileUrl.'/'.$uninstallOpt.'.json',"r") or die("Cannot open");
                            $uninst = @fread($uninstFile,filesize($fileUrl.'/'.$uninstallOpt.'.json'));
                            fclose($uninstFile);
                        }
                        else{
                            $uninst = file_exists($fileUrl.'/'.$uninstallOpt.'.json') ? '' : null;
                        }
                        //Uninstall Options exist?
                        if($uninst != null)
                            if(\IOFrame\Util\PureUtilFunctions::is_json($uninst)){
                                //Ensure options are legal
                                if($this->validatePluginFile(json_decode($uninst,true),$uninstallOpt,['isFile'=>true,'test'=>$test,'verbose'=>$verbose]))
                                    $res[$num]['uninstallOptions'] = json_decode($uninst,true);
                            }
                        //Dependencies
                        if(file_exists($fileUrl.'/dependencies.json') && filesize($fileUrl.'/dependencies.json')){
                            $depFile = @fopen($fileUrl.'/dependencies.json',"r") or die("Cannot open");
                            $dep = @fread($depFile,filesize($fileUrl.'/dependencies.json'));
                            fclose($depFile);
                        }
                        else{
                            $dep = file_exists($fileUrl.'/dependencies.json') ? '' : null;
                        }
                        //Dependencies exist?
                        if($dep != null)
                            if(\IOFrame\Util\PureUtilFunctions::is_json($dep)){
                                //Ensure dependencies are legal
                                if($this->validatePluginFile(json_decode($dep,true),'dependencies',['isFile'=>true,'test'=>$test,'verbose'=>$verbose]))
                                    $res[$num]['dependencies'] = json_decode($dep,true);
                            }
                        //Icon/thumbnail exist?
                        $supportedFormats = ['png','jpg','bmp','gif'];
                        $res[$num]['icon'] = false ;
                        $res[$num]['thumbnail'] = false ;
                        foreach($supportedFormats as $format){
                            if(!$res[$num]['icon'])
                                if(file_exists($fileUrl.'/icon.'.$format))
                                    $res[$num]['icon'] = $format;
                            if(!$res[$num]['thumbnail'])
                                if(file_exists($fileUrl.'/thumbnail.'.$format))
                                    $res[$num]['thumbnail'] = $format;
                        }
                    }
                }
                unset($res[$num][$name]);
            }
            return $res;
        }

        /** Installs a plugin
         * If the $name specified is a legal, uninstalled plugin, installs it.
         * 1) Validates dependencies
         * 2) Compares each of the provided $options (assoc array) with the same-name options at installOptions.json of the plugin, and checks
         *    the legality of each option - if it exists, is of the correct type, and matches/doesn't match the regex provided
         *    in that file (depending on whether it should). If the option is legal, adds it to an associative array that eventually
         *    overwrites $options. If options are missing the function encounters an illegal value, will exit with an error code
         *    Farther description of the installOptions.json file found at the top.
         * 3) Checks existing system and plugin definitions. If any of the definitions have the same name, exits with an error.
         *    Else adds all the definitions - if there are any - of the plugin definitions.json into the system definitions.json.
         * 4) Adds the plugin on /localFiles/plugins, and sets its status to "installing".
         * 5) Includes quickInstall.php, which should be a procedural file doing the actual installing. It is usually included
         *    after coreUtils.php, but you can't be certain, so when writing quickInstall.php consider the worst scenario in both
         *    cases.
         *    You can, however, rely on the fact that $options was defined here, and quickInstall.php has access to it.
         *    Also, $local is defined and either 'true' or 'false' here as well.
         * 6) Changes the plugin at /localFiles/plugins to "active".
         * At any point, if any exception is thrown, stops, echoes it, and includes quickUninstall.php.
         *
         * It is important to note that fullInstall.php isn't used here.
         * fullInstall.php should be a standalone installer for the plugin, that runs more like "_install.php" at the root folder.
         *
         * @param string $name Name of the plugin to install
         * @param array|null $options An array of options for the installer.
         * @param array $params Parameters of the form:
         *              'override' => Whether to try to install despite plugin being considered illegal. Default false
         *              'local' => bool, default false in Web, true in CLI - Install plugin locally as opposed to adding it to globally.
         *              'handleGlobalSettingFiles' => bool, default !local - Tell the plugin that it needs to handle non-local setting files/tables
         *              'handleGlobalSettings' => bool, default !local - Tell the plugin that it needs to handle non-local settings
         *              'handleGlobalActions' => bool, default !local - Tell the plugin that it needs to handle auth actions
         *              'handleGlobalSecurityEvents' => bool, default !local - Tell the plugin that it needs to handle security events
         *              'handleGlobalRoutes' => bool, default !local - Tell the plugin that it needs to handle routes
         *              'handleGlobalMatches' => bool, default !local - Tell the plugin that it needs to handle route matches
         *              'handleDb' => bool, default !local - Tell the plugin that it needs to handle DB tables
         *
         * @returns integer Returns
         * 0 installation was complete and without errors
         * 1 plugin is already installed or zombie
         * 2 quickInstall.php is missing, or if the plugin is illegal and $override is false.
         * 3 dependencies are missing.
         * 4 missing or illegal options
         * 5 plugin definitions are similar to existing system definitions  - will also echo exception
         * 6 exception thrown during install  - will also echo exception
         *
         * @throws \Exception
         * @throws \Exception
         */
        function install(string $name, array $options = null, array $params = []){

            //Set defaults

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $options = $options??[];

            $override = $params['override'] ?? false;

            $local = $params['local'] ?? (php_sapi_name() == "cli");
            $handleGlobalSettingFiles = $params['handleGlobalSettingFiles'] ?? !$local;
            $handleGlobalSettings = $params['handleGlobalSettings'] ?? !$local;
            $handleGlobalActions = $params['handleGlobalActions'] ?? !$local;
            $handleGlobalSecurityEvents = $params['handleGlobalSecurityEvents'] ?? !$local;
            $handleGlobalRoutes = $params['handleGlobalRoutes'] ?? !$local;
            $handleGlobalMatches = $params['handleGlobalMatches'] ?? !$local;

            $url = $this->settings->getSetting('absPathToRoot').self::PLUGIN_FOLDER_PATH.self::PLUGIN_FOLDER_NAME.$name.'/';   //Plugin folder
            $LockManager = new \IOFrame\Managers\LockManager($url);
            $plugList = new \IOFrame\Handlers\SettingsHandler($this->settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/plugins/');
            $plugInfo = $this->getInfo(['name'=>$name])[0];

            //-------Check if the plugin is installed
            $status = $plugList->getSetting($name) ? json_decode($plugList->getSetting($name),true)['status'] : false;
            if($status == 'installed' || $status == 'zombie' || $status == 'installing'){
                if($verbose)
                    echo 'Plugin '.$name.' is either installed, installing or zombie!'.EOL;
                $this->logger->error('Failed to install, existing plugin is invalid status',['plugin'=>$name,'status'=>$status]);
                return 1;
            }

            //-------Check if the plugin is illegal with override off, or the install is missing
            if($plugInfo['status'] != 'legal'){
                if($override && $plugInfo['status'] == 'illegal' &&
                    file_exists($url.'quickInstall.php'))
                    $goOn = true;
                else
                    $goOn = false;
            }
            else
                $goOn = true;
            if(!$goOn){
                if($verbose)
                    echo 'quickInstall for '.$name.' is either missing, or plugin illegal!'.EOL;
                $this->logger->error('Failed to install, quickInstall does not exist',['plugin'=>$name]);
                return 2;
            }

            //-------Validate dependencies
            $dependencies = $plugInfo['dependencies'] ?? [];
            if($this->validateDependencies($name,['dependencyArray'=>$dependencies,'test'=>$test,'verbose'=>$verbose]) > 1)
                return 3;

            //-------Time to validate options
            if(!$this->validateOptions('installOptions',$url,$name,$options,['test'=>$test,'verbose'=>$verbose]))
                return 4;

            //-------Change plugin to "installing"
            if(!$test)
                $plugList->setSetting($name,json_encode(['status'=>'installing','version'=>$plugInfo['version']]),['createNew'=>true]);

            //-------Time to validate (then update) definitions if the exist
            if(file_exists($url.'definitions.json')){
                if(!$this->validatePluginFile($url,'definitions',['isFile'=>false,'test'=>$test,'verbose'=>$verbose])){
                    if($verbose)
                        echo 'Definitions for '.$name.' are not valid!'.EOL;
                    return 5;
                }
                //Now add the definitions to the system definition file
                try{
                    $gDefUrl = $this->settings->getSetting('absPathToRoot').'localFiles/definitions/';
                    //Read definition files - and merge them
                    $defFile = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($url,'definitions.json',['LockManager' => $LockManager]);
                    if($defFile != null){       //If the file is empty, don't bother doing work
                        $defArr = json_decode($defFile,true);
                        $gDefFile = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($gDefUrl,'definitions.json',['LockManager' => $LockManager]);
                        $gDefArr = json_decode($gDefFile,true);
                        if(is_array($gDefArr))
                            $newDef = array_merge($defArr,$gDefArr);
                        else
                            $newDef = $defArr;
                        //Write to global definition file after backing it up
                        if(!$test){
                            $defLock = new \IOFrame\Managers\LockManager($gDefUrl);
                            $defLock->makeMutex();
                            \IOFrame\Util\FileSystemFunctions::backupFile($gDefUrl,'definitions.json');
                            $gDefFile = fopen($gDefUrl.'definitions.json', "w+") or die("Unable to open definitions file!");
                            fwrite($gDefFile,json_encode($newDef));
                            fclose($gDefFile);
                            $defLock->deleteMutex();
                        }
                        //If this was a test, return the expected result
                        if($verbose){
                            echo 'New definitions added to system definitions: '.$defFile.EOL;
                        }
                    }
                }
                catch (\Exception $e){
                    if($verbose)
                        echo 'Exception :'.$e.EOL;
                    $this->logger->error('Failed to add dynamic definitions, exception '.$e->getMessage(),['plugin'=>$name,'trace'=>$e->getTrace()]);
                    try{
                        $options = [];
                        require $url.'quickUninstall.php';
                        $plugList->setSetting($name,null,['createNew'=>true]);
                    }
                    catch (\Exception $e){
                        if($verbose)
                            echo 'Exception during definition inclusion of plugin '.$name.': '.$e.EOL;
                        $this->logger->critical('Failed to add dynamic definitions, then failed to uninstall exception '.$e->getMessage(),['plugin'=>$name,'trace'=>$e->getTrace()]);
                    }
                    return 5;
                }
            }

            //-------Finally, include the install file
            try{
                require $url.'quickInstall.php';
            }catch
            (\Exception $e){
                $this->logger->error('Failed to install, exception '.$e->getMessage(),['plugin'=>$name,'trace'=>$e->getTrace()]);
                try{
                    $options = [];
                    require $url.'quickUninstall.php';
                    $plugList->setSetting($name,null,['createNew'=>true]);
                }
                catch (\Exception $e){
                    $this->logger->critical('Failed to install, then failed to uninstall exception '.$e->getMessage(),['plugin'=>$name,'trace'=>$e->getTrace()]);
                    if($verbose)
                        echo 'Exception during install of plugin '.$name.': '.$e.EOL;
                }
                return 6;
            }
            //-------Populate dependency map
            $this->populateDependencies($name,$dependencies,['test'=>$test,'verbose'=>$verbose]);

            //-------Change plugin to "installed"
            if(!$test)
                $plugList->setSetting($name,json_encode(['status'=>'installed','version'=>$plugInfo['version']]),['createNew'=>true]);

            //-------Add to order list - globally, and potentially locally
            if(!$local)
                $this->pushToOrder($name,['local'=>false,'verify'=>!$override,'test'=>$test,'verbose'=>$verbose]);

            $this->pushToOrder(
                $name,
                ['verify'=>false,'backUp'=>true,'local'=>true,'test'=>$test,'verbose'=>$verbose]
            );

            return 0;
        }

        /** Uninstalls a plugin
         *
         * If the $name specified is a legal, existing plugin, uninstalls it.
         * 1) Validates all files/options
         * 2) Changes the plugin at /localFiles/plugins to "zombie".
         * 3) Includes quickUninstall.php, which should be a procedural file doing the uninstalling.
         *    Notice that this file can also be included at any point at the installation, so when writing it, don't assume
         *    every component of your plugin is installed correctly (or at all).
         *    Like with install, you can rely on the fact that $options was defined here, as well as $local (but again -
         *    $options may be the ones from the install).
         * 4) Removes all the definitions - if there are any - of the plugin from the systems definitions.json file.
         * 5) Removes the plugin from the /localFiles/plugins list.
         * At any point, if any exception is thrown, stops and echoes it. Remember that a plugin with a failed uninstall will
         * have the status "zombie".
         *
         * @param string $name Name of the plugin to uninstall
         * @param array|null $options Array of options for the uninstaller.
         * @param array $params of the form:
         *               'override' => Whether to try to install despite plugin being considered illegal. Default false
         *               'local' => bool, default false in Web, true in CLI - Uninstall plugin locally as opposed to adding it to globally.
         *               'handleGlobalSettingFiles' => bool, default !local - Tell the plugin that it needs to handle non-local setting files/tables
         *               'handleGlobalSettings' => bool, default !local - Tell the plugin that it needs to handle non-local settings
         *               'handleGlobalActions' => bool, default !local - Tell the plugin that it needs to handle auth actions
         *               'handleGlobalSecurityEvents' => bool, default !local - Tell the plugin that it needs to handle security events
         *               'handleGlobalRoutes' => bool, default !local - Tell the plugin that it needs to handle routes
         *               'handleGlobalMatches' => bool, default !local - Tell the plugin that it needs to handle route matches
         *               'handleDb' => bool, default !local - Tell the plugin that it needs to handle DB tables
         *
         * @returns mixed Returns
         * 0 the plugin was uninstalled successfully
         * 1 the plugin was "absent" OR  override == false and the plugin isn't listed as installed
         * 2 quickUninstall.php wasn't found
         * 3 Override is false, and there are dependencies on this plugin
         * 4 uninstallOptions mismatch with given options
         * 5 Could not remove definitions  - will also echo exception
         * 6 Exception during uninstall - will also echo exception
         *
         * @throws \Exception
         * @throws \Exception
         */
        function uninstall(string $name, array $options = null, array $params = []){

            //Set defaults
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $options = $options??[];

            $override = $params['override'] ?? true;

            $local = $params['local'] ?? (php_sapi_name() == "cli");
            $handleGlobalSettingFiles = $params['handleGlobalSettingFiles'] ?? !$local;
            $handleGlobalSettings = $params['handleGlobalSettings'] ?? !$local;
            $handleGlobalActions = $params['handleGlobalActions'] ?? !$local;
            $handleGlobalSecurityEvents = $params['handleGlobalSecurityEvents'] ?? !$local;
            $handleGlobalRoutes = $params['handleGlobalRoutes'] ?? !$local;
            $handleGlobalMatches = $params['handleGlobalMatches'] ?? !$local;

            $url = $this->settings->getSetting('absPathToRoot').self::PLUGIN_FOLDER_PATH.self::PLUGIN_FOLDER_NAME.$name.'/';   //Plugin folder
            $depUrl = $this->settings->getSetting('absPathToRoot').'localFiles/pluginDependencyMap/';
            $LockManager = new \IOFrame\Managers\LockManager($url);
            $plugList = new \IOFrame\Handlers\SettingsHandler($this->settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/plugins/');
            $plugInfo = $this->getInfo(['name'=>$name])[0];

            //-------Check if the plugin is absent - or if override is false while the plugin isn't listed installed.
            $goOn = ($plugInfo['status'] != 'absent');
            if($goOn && !$override && $plugInfo['status'] != 'active')
                $goOn = false;
            if(!$goOn){
                if($verbose)
                    echo 'Plugin '.$name.' absent, can not uninstall!'.EOL;
                $this->logger->error('Failed to uninstall, existing plugin absent',['plugin'=>$name]);
                if(($plugInfo['status'] == 'absent') && !$test) //Only remove the plugin from the list if its actually absent
                    $plugList->setSetting($name,null);
                return 1;
            }

            //-------Make sure quickUninstall exists
            if(!file_exists($url.'quickUninstall.php')){
                if($verbose)
                    echo 'Plugin '.$name.' quickUninstall absent, can not uninstall!'.EOL;
                $this->logger->critical('Failed to uninstall, quickInstall does not exist',['plugin'=>$name]);
                return 2;
            }

            //-------Check for dependencies
            $dep = $this->checkDependencies($name,['validate'=>true,'test'=>$test,'verbose'=>$verbose]);
            if(!($dep === 0)){
                if($verbose)
                    echo 'Plugin '.$name.' dependencies are '.$dep.', can not uninstall!'.EOL;
                return 3;
            }

            //-------Change plugin to "zombie"
            if(!$test)
                $plugList->setSetting($name,json_encode(['status'=>'zombie','version'=>($plugInfo['version'] ?? 0)]));

            //-------Validate options
            if(!$this->validateOptions('uninstallOptions',$url,$name,$options,['test'=>$test,'verbose'=>$verbose])){
                $this->logger->critical('Failed to uninstall, uninstallOptions are missing, plugin is now a zombie',['plugin'=>$name]);
                return 4;
            }

            //-------Call quickUninstall.php - REMEMBER - OPTIONS ARRAY MUST BE FILTERED
            try{
                require $url.'quickUninstall.php';
            }
            catch(\Exception $e){
                $this->logger->critical('Failed to uninstall exception '.$e->getMessage(),['plugin'=>$name,'trace'=>$e->getTrace()]);
                if($verbose)
                    echo 'Exception during uninstall of plugin '.$name.': '.$e.EOL;
                return 6;
            }

            //-------Remove plugin from order list - globally, and potentially locally
            if(!$local)
                $this->removeFromOrder(
                        $name,
                        'name',
                        ['verify'=>false,'backUp'=>true,'local'=>false,'test'=>$test,'verbose'=>$verbose]
                    );
            $this->removeFromOrder(
                    $name,
                    'name',
                    ['verify'=>false,'backUp'=>true,'local'=>true,'test'=>$test,'verbose'=>$verbose]
                );
            //-------Remove dependencies
            $dep = json_decode(\IOFrame\Util\FileSystemFunctions::readFileWaitMutex($url,'dependencies.json'),true);
            if(is_array($dep))
                foreach($dep as $pName=>$ver){
                    if(file_exists($depUrl.$pName.'/settings')){
                        if(!$test){
                            $depHandler = new \IOFrame\Handlers\SettingsHandler($depUrl.$pName.'/',['useCache'=>false]);
                            $depHandler->setSetting($name,null);
                        }
                        if($verbose){
                            echo 'Removing '.$name.' from dependency tree of '.$pName.EOL;
                        }
                    }
                }

            //-------If the definitions file exists, remove them
            if(file_exists($url.'definitions.json')){
                if(!$this->validatePluginFile($url,'definitions',['isFile'=>false,'test'=>$test,'verbose'=>$verbose])){
                    if($verbose)
                        echo 'Definitions for '.$name.' are not valid!'.EOL;
                    $this->logger->critical('Failed to remove dynamic definitions after uninstall',['plugin'=>$name]);
                    return 5;
                }
                //Now remove the definitions from the system definition file
                try{
                    $gDefUrl = $this->settings->getSetting('absPathToRoot').'localFiles/definitions/';
                    //Read definition files - and remove the matching ones
                    $defFile = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($url,'definitions.json',['LockManager' => $LockManager]);
                    if($defFile != null){       //If the file is empty, don't bother doing work
                        $defArr = json_decode($defFile,true);
                        $gDefFile = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($gDefUrl,'definitions.json',['LockManager' => $LockManager]);
                        $gDefArr = json_decode($gDefFile,true);
                        foreach($defArr as $def=>$val){
                            if(isset($gDefArr[$def]))
                                if($gDefArr[$def] == $val){
                                    unset($gDefArr[$def]);
                                }
                        }
                        //Write to global definition file after backing it up
                        if(!$test){
                            $defLock = new \IOFrame\Managers\LockManager($gDefUrl);
                            $defLock->makeMutex();
                            \IOFrame\Util\FileSystemFunctions::backupFile($gDefUrl,'definitions.json');
                            $gDefFile = fopen($gDefUrl.'definitions.json', "w+") or die("Unable to open definitions file!");
                            fwrite($gDefFile,json_encode($gDefArr));
                            fclose($gDefFile);
                            $defLock->deleteMutex();
                        }
                        //If this was a test, return the expected result
                        if($verbose){
                            echo 'Definitions removed from system definitions: '.$defFile.EOL;
                        }
                    }
                }
                catch (\Exception $e){
                    $this->logger->critical('Failed to remove dynamic definitions after uninstall, exception '.$e->getMessage(),['plugin'=>$name,'trace'=>$e->getTrace()]);
                    if($verbose)
                        echo 'Exception during definition removal plugin '.$name.': '.$e.EOL;
                    return 5;
                }
            }

            //-------Remove plugin from list
            if(!$test)
                $plugList->setSetting($name,null);

            return 0;
        }

        /** Updates a plugin
         * If the $name specified is a legal, installed plugin, tries to update it.
         *
         * 1) Checks that the plugin is installed
         * 2) Ensures all update files are present and of the valid format.
         * 3) Checks whether there even is anything new to update - if yes, calculates $targetVersion,
         *    the version the next update iteration would bring this plugin to.
         * 4) Enters the following loop, which lasts for $params['iterationLimit'] iterations (or independent of it),
         *    or until the plugin can no longer be updated, the earliest of the two:
         *      4.1) Requires update.php, which should be a procedural file doing the actual updating.
         *           It is farther explained at the top of this class.
         *      4.2) Assuming no exception was thrown, sets "result" to 0, "resultType" to 'success' and "newVersion" to $targetVersion.
         *      4.3) Updates the plugin version in its meta.json file - throws an exception on failure.
         *      4.4) Checks whether there is another version to update to - breaks the loop if no, calculates next $targetVersion if yes.
         *      At any point, if any exception is thrown, the following happens:
         *      4['exception'] - sets "result" to 4, populates "exception", updates "resultType" and requires updateFallback.php, then returns.
         *                       If updateFallback throws an exception the following happens:
         *                       4['exceptionInFallback'] - sets "result" to 5, populates "exceptionInFallback", updates "resultType" and returns.
         *
         * @param string $name Name of the plugin to update
         * @param array $params Parameters of the form:
         *              'iterationLimit' => int, default -1. The maximum number of update iterations (explained earlier). -1 means "no limit"
         *              'local' => bool, default false in Web, true in CLI - Install plugin locally as opposed to adding it to globally.
         *              'handleGlobalSettingFiles' => bool, default !local - Tell the plugin that it needs to handle non-local setting files/tables
         *              'handleGlobalSettings' => bool, default !local - Tell the plugin that it needs to handle non-local settings
         *              'handleGlobalActions' => bool, default !local - Tell the plugin that it needs to handle auth actions
         *              'handleGlobalSecurityEvents' => bool, default !local - Tell the plugin that it needs to handle security events
         *              'handleGlobalRoutes' => bool, default !local - Tell the plugin that it needs to handle routes
         *              'handleGlobalMatches' => bool, default !local - Tell the plugin that it needs to handle route matches
         *              'handleDb' => bool, default !local - Tell the plugin that it needs to handle DB tables
         * @returns array Returns an assoc array of the form
         * [
         *      'resultType' => 'error' - on error code
         *                      'success' - success with no errors
         *                      'success-partial' - succeeded at least one iteration, then failed
         *      'newVersion' => int, on any result that isn't an error, will return the new plugin version
         *      'result' => On error, one of the following codes:
         *                  0 plugin not installed
         *                  1 One of the required update files is missing.
         *                  2 No new updates possible for current version
         *                  -- During each iteration --
         *                  3 Updating would violate existing dependencies (maxVersion of dependant plugin).
         *                  4 exception thrown during update - will also populate exception
         *                  5 critical error - exception thrown during updateFallback - will also populate exceptionInFallback
         *
         *                  On success or partial success, possible codes are:
         *                  0 plugin updated successfully
         *                  3 same as earlier
         *                  4 same as earlier
         *                  5 same as earlier
         *
         *      'exception' => string, empty, populated on a specific update exception.
         *      'exceptionInFallback' => string, empty, populated on a specific updateFallback exception.
         *      'moreUpdates' => bool, only set to true if we stopped updating due to the version requirement chain being broken
         * ]
         *
         * @throws \Exception
         * @throws \Exception
         */
        function update(string $name, array $params = []): array {

            //Set defaults
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $iterationLimit = $params['iterationLimit'] ?? -1;
            $local = $params['local'] ?? (php_sapi_name() == "cli");
            $handleGlobalSettingFiles = $params['handleGlobalSettingFiles'] ?? !$local;
            $handleGlobalSettings = $params['handleGlobalSettings'] ?? !$local;
            $handleGlobalActions = $params['handleGlobalActions'] ?? !$local;
            $handleGlobalSecurityEvents = $params['handleGlobalSecurityEvents'] ?? !$local;
            $handleGlobalRoutes = $params['handleGlobalRoutes'] ?? !$local;
            $handleGlobalMatches = $params['handleGlobalMatches'] ?? !$local;

            $res = [
                'resultType'=>'error',
                'newVersion'=>-1,
                'result'=>0,
                'exception' => '',
                'exceptionInFallback' => '',
                'moreUpdates' => false
            ];

            $url = $this->settings->getSetting('absPathToRoot').self::PLUGIN_FOLDER_PATH.self::PLUGIN_FOLDER_NAME.$name.'/';   //Plugin folder
            $plugList = new \IOFrame\Handlers\SettingsHandler($this->settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/plugins/');
            $plugInfo = $this->getInfo(['name'=>$name])[0];
            $dep = $this->checkDependencies($name,['validate'=>false]);
            if(\IOFrame\Util\PureUtilFunctions::is_json($dep))
                $dep = json_decode($dep,true);
            else
                $dep = [];

            //-------Check if the plugin is installed
            if(!$plugInfo['status'] === 'active'){
                if($verbose)
                    echo 'Plugin '.$name.' is not installed!'.EOL;
                $this->logger->warning('Failed to update, plugin not installed ',['plugin'=>$name]);
                return $res;
            }
            else
                $res['result'] = 1;

            $currentVersion = isset($plugInfo['currentVersion'])? (int)$plugInfo['currentVersion'] : 0;
            $targetVersion = 0;
            $currentRangeIndex = 0;

            //-------Check if update files are valid
            if(!$plugInfo['hasUpdateFiles']){
                $this->logger->warning('Failed to update, plugin has no update files',['plugin'=>$name]);
                if($verbose)
                    echo 'Plugin '.$name.' has no valid update files!'.EOL;
                return $res;
            }
            else
                $res['result'] = 2;

            //-------Check if  a new update is possible -also initiate target version
            foreach($plugInfo['updateRanges'] as $index => $range){
                if(gettype($range) === 'integer')
                    $plugInfo['updateRanges'][$index] = [$range,$range];
                else
                    $plugInfo['updateRanges'][$index] = [(int)$range[0],(int)$range[1]];
                $range = $plugInfo['updateRanges'][$index];
                if($targetVersion > 0)
                    continue;
                if( ($currentVersion >= $range[0]) && ($currentVersion <= $range[1])){
                    $currentRangeIndex = $index;
                    $targetVersion = $range[1] + 1;
                }
            }
            if($targetVersion <= 0){
                $this->logger->notice('Failed to update, plugin has no new updates',['plugin'=>$name]);
                if($verbose)
                    echo 'Plugin '.$name.' has no new updates!'.EOL;
                return $res;
            }

            //Continue until iteration limit is 0 (wont happen if it's -1) - however, will also stop who no more valid updates are available
            while( ($iterationLimit-- !== 0) && ($targetVersion > 0)){
                //-------Validate dependencies
                foreach($dep as $dependency => $range){
                    $range = json_decode($range,true);
                    if(!empty($range['maxVersion']) && ($targetVersion > $range['maxVersion']) ){
                        if($verbose)
                            echo 'Plugin '.$dependency.' depends on '.$name.'\'s version to be at most '.$range['maxVersion'].EOL;
                        $res['result'] = 3;
                        $res['resultType'] = $res['resultType'] === 'success' ? 'success-partial' : 'error';
                        if($res['resultType'] === 'success-partial')
                            $this->logger->notice('Stopped plugin update at version',['plugin'=>$name,'target'=>$targetVersion,'max'=>$range['maxVersion'],'result'=>$res['resultType']]);
                        else
                            $this->logger->error('Stopped plugin update at version',['plugin'=>$name,'target'=>$targetVersion,'max'=>$range['maxVersion'],'result'=>$res['resultType']]);
                        return $res;
                    }
                }

                //-------Try to update
                try{
                    require $url.'update.php';
                    $res['result'] = 0;
                    $res['resultType'] = 'success';
                    $res['newVersion'] = $targetVersion;

                    //Important to ensure we succeeded at updating the meta
                    $currentSetting = json_decode($plugList->getSetting($name),true);
                    if(!$plugList->setSetting($name,json_encode(array_merge($currentSetting,['version'=>$targetVersion])),['test'=>$test]))
                        throw new \Exception("Could not update plugin meta to new version!");
                    $currentVersion = $targetVersion;

                    //See if there is a new target version
                    $currentRangeIndex += 1;
                    if(!isset($plugInfo['updateRanges'][$currentRangeIndex]))
                        $targetVersion = 0;
                    elseif($plugInfo['updateRanges'][$currentRangeIndex][0] > $targetVersion){
                        $targetVersion = 0;
                        $res['moreUpdates'] = true;
                    }
                    else{
                        $targetVersion = $plugInfo['updateRanges'][$currentRangeIndex][1] + 1;
                    }
                }
                catch (\Exception $e){
                    try{
                        $this->logger->error('Plugin update failed, exception '.$e->getMessage(),['plugin'=>$name,'trace'=>$e->getTrace()]);
                        if($verbose)
                            echo 'Plugin '.$name.' update failure - exception thrown in update script '.EOL;
                        $res['result'] = 4;
                        $res['exception'] = $e->getMessage();
                        $res['resultType'] = $res['resultType'] === 'success' ? 'success-partial' : 'error';
                        require $url.'updateFallback.php';
                        return $res;
                    }
                    catch (\Exception $e){
                        $this->logger->critical('Plugin update failed, then fallback failed '.$e->getMessage(),['plugin'=>$name,'trace'=>$e->getTrace()]);
                        if($verbose)
                            echo 'Plugin '.$name.' update critical failure - exception thrown in fallBack '.EOL;
                        $res['result'] = 5;
                        $res['exceptionInFallback'] = $e->getMessage();
                        $res['resultType'] = $res['resultType'] === 'success' ? 'success-partial' : 'error';
                        return $res;
                    }
                }
            }

            return $res;
        }

        /** See OrderManager documentation
         * @param array $params
         * @return mixed
         * */
        function getOrder(array $params = []): mixed {
            return $this->OrderManager->getOrder($params);
        }

        /** Pushes a plugin to the bottom/top of the order list, if index is -1/-2, respectively, or
         * to the index (pushing everything below it down)
         * @param string $name Plugin name
         * @param array $params of the form:
         *              'index' - int, default -1 - As described above.
         *              'verify' => bool, default true - Verify plugin dependencies before changing order
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'backUp' => bool, default false - Back up local file after changing
         * @returns int
         * 0 - success
         *
         *
         * 3 - couldn't read or write file/db
         * 4 - failed to verify plugin is active
         * */
        function pushToOrder(string $name, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $verify = $params['verify'] ?? true;

            //Verify first, if $verify == true
            if($verify){
                if($verbose)
                    echo 'Verifying that plugin '.$name.' is active!'.EOL;
                $info = $this->getAvailable(['name'=>$name]);
                if( ($info[$name] != 'active')){
                    $this->logger->notice('Tried to push inactive plugin to order',['plugin'=>$name,'status'=>$info[$name]]);
                    if($verbose)
                        echo 'Cannot push a plugin into order that is not active!'.EOL;
                    return 4;
                }
            }

            return $this->OrderManager->pushToOrder($name,$params);
        }

        /** Remove a plugin from the order.
         *
         * @param string $target is the index (number) or name of the plugin, depending on $type. Range is of format '<from>,<to>'
         * @param string $type is 'index', 'range' or 'name'
         * @param array $params of the form:
         *              'verify' => bool, default true - Verify plugin dependencies before changing order
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'backUp' => bool, default false - Back up local file after changing
         * @returns int
         * 0 - success
         * 1 - index or name don't exist
         * 2 - incorrect type
         * 3 - couldn't read or write file/db, or order is not an array
         * JSON of the form {'fromName':'violatedDependency'} - $validate is true, and dependencies would be violated by
         *  removing the plugin from the order
         * */
        function removeFromOrder(string $target, string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Set defaults
            $verify = $params['verify'] ?? true;

            $order = $this->OrderManager->getOrder($params);

            $params['order'] = is_array($order)? implode(',',$order): $order;

            $params['indexChecksOnly'] = true;
            $indexChecks = $this->OrderManager->removeFromOrder($target,$type,$params);
            $params['indexChecksOnly'] = false;

            if($indexChecks != 0)
                return $indexChecks;

            //Verify dependencies
            if($verify){
                if($verbose)
                    echo 'Verifying that plugin '.$order[$target].' has no dependencies!'.EOL;
                $dep = $this->checkDependencies($order[$target],['validate'=>true,'test'=>$test,'verbose'=>$verbose]);
                if($dep !== 0){
                    $this->logger->notice('Tried to remove plugin with active dependencies from order',['plugin'=>$order[$target],'dependencies'=>$dep]);
                    if($verbose)
                        echo 'Plugin '.$order[$target].' dependencies are '.$dep.', can not remove!'.EOL;
                    return $dep;
                }
            }

            return $this->OrderManager->removeFromOrder($target,$type,$params);
        }

        /** Moves a plugin from one index in the order list to another,
         * pushing everything it passes 1 space up/down in the opposite direction
         * @param int $from
         * @param int $to
         * @param array $params of the form:
         *              'verify' => bool, default true - Verify plugin dependencies before changing order
         *              'local' => bool, default true - Whether to change the order just locally, or globally too
         *              'backUp' => bool, default false - Back up local file after changing
         * @return false|int|string
         * @throws \Exception
         */
        function moveOrder(int $from, int $to, array $params = []): bool|int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Set defaults
            $verify = $params['verify'] ?? true;
            
            $order = $this->OrderManager->getOrder($params);

            $params['order'] = is_array($order)? implode(',',$order): $order;

            $params['indexChecksOnly'] = true;
            $indexChecks = $this->OrderManager->moveOrder($from,$to,$params);
            $params['indexChecksOnly'] = false;

            if($indexChecks != 0)
                return $indexChecks;

            //Verify dependencies
            if($verify){
                $pluginUrl = $this->settings->getSetting('absPathToRoot').self::PLUGIN_FOLDER_PATH.self::PLUGIN_FOLDER_NAME;
                $fromName = $order[$from];

                if($verbose)
                    echo 'Verifying that plugin '.$fromName.' can be moved from '.$from.' to '.$to. '!'.EOL;

                //If we push something upwards, we need to make sure its own dependencies are not violated
                if($from > $to){
                    $dep = json_decode(\IOFrame\Util\FileSystemFunctions::readFileWaitMutex($pluginUrl.$fromName,'dependencies.json'),true);
                    if(is_array($dep))
                        for($i = $to; $i<$from; $i++){
                            //This would mean a dependency would get swapped underneath us, which is illegal.
                            //Remember that $order[i] is the name of each plugin in the order, and $dep is an array whos keys are plugins
                            //We are dependant on.
                            if( array_key_exists($order[$i],$dep) ){
                                $this->logger->notice('Tried to move plugin, which would violate dependencies',['plugin'=>$fromName,'from'=>$from,'to'=>$to,'dependency'=>$order[$i]]);
                                if($verbose)
                                    echo 'Order movement would violate '.$fromName.' dependency on '.$order[$i].EOL;
                                return  json_encode([$order[$i]=>$fromName]);
                            }
                        }
                }
                //If we push something downwards, we need ensure it does not break others' dependencies on it.
                else{
                    $dep = json_decode( $this->checkDependencies($fromName,['validate'=>true]) , true);
                    if(is_array($dep))
                        for($i = $from+1; $i<=$to; $i++){
                            //This would mean we can get swapped under a plugin that depends on us, which is illegal.
                            //Remember that $order[i] is the name of each plugin in the order, and $dep is an array
                            //of plugins that depend on this one.
                            if( array_key_exists($order[$i],$dep) ){
                                if($verbose)
                                    echo 'Order movement would violate '.$order[$i].' dependency on '.$fromName.EOL;
                                $this->logger->notice('Tried to move plugin, which would violate dependencies',['plugin'=>$order[$i],'from'=>$from,'to'=>$to,'dependency'=>$fromName]);
                                return json_encode([$fromName=>$order[$i]]);
                            }
                        }
                }
            }

            return $this->OrderManager->moveOrder($from,$to,$params);
        }

        /** Swaps 2 plugins in the order
         * @param int $num1
         * @param int $num2
         * @param array $params of the form:
         *              'verify' => bool, default true - Verify plugin dependencies before changing order
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'backUp' => bool, default false - Back up local file after changing
         * @return false|int|string
         * @throws \Exception
         */
        function swapOrder(int $num1,int $num2, array $params = []): bool|int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Set defaults
            $verify = $params['verify'] ?? true;

            $order = $this->OrderManager->getOrder($params);

            $params['order'] = is_array($order)? implode(',',$order): $order;

            $params['indexChecksOnly'] = true;
            $indexChecks = $this->OrderManager->moveOrder($num1,$num1,$params);
            $params['indexChecksOnly'] = false;

            if($indexChecks != 0)
                return $indexChecks;

            //Validate dependencies
            if($verify){

                if($verbose)
                    echo 'Verifying that plugins at '.$num1.' and '.$num2.' can be swapped!'.EOL;

                $pluginUrl = $this->settings->getSetting('absPathToRoot').self::PLUGIN_FOLDER_PATH.self::PLUGIN_FOLDER_NAME;
                //If we push something upwards, we need to make sure its own dependencies are not violated
                $dep = null;
                if(!empty($order[$num2]))
                    $dep = json_decode(\IOFrame\Util\FileSystemFunctions::readFileWaitMutex($pluginUrl.$order[$num2],'dependencies.json'),true);
                if(is_array($dep))
                    for($i = $num1; $i<$num2; $i++){
                        //This would mean a dependency would get swapped underneath us, which is illegal.
                        //Remember that $order[i] is the name of each plugin in the order, and $dep is an array whos keys are plugins
                        //We are dependant on.
                        if( array_key_exists($order[$i],$dep) ){
                            $this->logger->notice('Tried to swap plugin, which would violate dependencies',['plugin'=>$order[$num2],'dependency'=>$order[$i]]);
                            if($verbose)
                                echo 'Order swap would violate '.$order[$num2].' dependency on '.$order[$i].EOL;
                            return json_encode([$order[$i]=>$order[$num2]]);
                        }
                    }
                //If we push something downwards, we need ensure it does not break others' dependencies on it.
                $dep = json_decode( $this->checkDependencies($order[$num1],['validate'=>true]) , true);
                if(is_array($dep))
                    for($i = $num1+1; $i<=$num2; $i++){
                        //This would mean we can get swapped under a plugin that depends on us, which is illegal.
                        //Remember that $order[i] is the name of each plugin in the order, and $dep is an array
                        //of plugins that depend on this one.
                        if( array_key_exists($order[$i],$dep) ){
                            $this->logger->notice('Tried to swap plugin, which would violate dependencies',['plugin'=>$order[$i],'dependency'=>$order[$num1]]);
                            if($verbose)
                                echo 'Order swap would violate '.$order[$i].' dependency on '.$order[$num1].EOL;
                            return json_encode([$order[$num1]=>$order[$i]]);
                        }
                    }
            }

            return $this->OrderManager->moveOrder($num1,$num2,$params);
        }

        /** Checks whether a the contents of the folder at $url are contents of a valid plugin folder
         * @param string $url url to the plugin
         * @param array $params
         * @return bool
         */
        function validatePlugin(string $url,array $params = []): bool {
            $params['isFile'] = false;
            $res = false;
            if( (file_exists($url.'/quickInstall.php') || file_exists($url.'/fullInstall.php')) &&
                (file_exists($url.'/quickUninstall.php') || file_exists($url.'/fullUninstall.php')))
                $res = $this->validatePluginFile($url,'meta',$params);
            return $res;
        }

        /** Makes sure the format of array $options matches the option file $target at $url. $target is '(un)installOptions'
         * @param string $target One of the two - "installOptions" / "uninstallOptions"
         * @param string $url Url to the options file
         * @param string $name Name of the plugin
         * @param array $options Options array (probably user provided)
         * @param array $params
         * @return bool
         */
        function validateOptions(string $target, string $url, string $name, array $options, array $params = []): bool {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $params['isFile'] = false;
            //-------Time to validate options
            if(!$this->validatePluginFile($url,$target,$params)){
                if($verbose)
                    echo $target.' of '.$name.' are invalid!'.EOL;
                return false;
            }
            try{
                $optionsFile = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($url,$target.'.json');
                if($optionsFile == '')
                    return true;
                $optionsFile = json_decode($optionsFile,true);
                //Validate the FORMAT and TYPES of $options - not their content validity.
                foreach($optionsFile as $optName => $optProperties){
                    //No need to check for an optional option that was not provided.
                    if(isset($optProperties['optional']) && !isset($options[$optName]))
                        continue;

                    if(!isset($optProperties['maxLength']))
                        $maxLength = 20000;
                    else
                        $maxLength = $optProperties['maxLength'];
                    if(!isset($optProperties['maxNum']))
                        $maxNum = PHP_INT_MAX;
                    else
                        $maxNum = $optProperties['maxNum'];
                    //$options has to contain EVERY option described in installOptions.json - unless it is optional
                    if(!isset($options[$optName])){
                        if(!isset($optProperties['optional'])){
                            if($verbose)
                                echo 'Option '.$optName.' for '.$name.' is not set!'.EOL;
                            return false;
                        }
                    }
                    if($optProperties['type'] == 'number'){
                        if($options[$optName] > $maxNum){
                            if($verbose)
                                echo 'Option '.$optName.' for '.$name.' is too big a number!'.EOL;
                            return false;
                        }
                    }
                    else{
                        if(!is_array($options[$optName]))
                            if(strlen($options[$optName]) > $maxLength){
                                if($verbose)
                                    echo 'Option '.$optName.' for '.$name.' is too long!'.EOL;
                                return false;
                            }
                    }
                }
            }
            catch(\Exception $e){
                $this->logger->critical('Tried to validate plugin options, exception '.$e->getMessage(),['target'=>$target,'name'=>$name,'url'=>$url,'options'=>$options,'trace'=>$e->getTrace()]);
                if($verbose)
                    echo $target.' of '.$name.' threw an exception!'.EOL;
                return false;
            }
            return true;
        }

        /** Returns true if a the contents of the file at url $target are contents of a VALID FORMAT - can still be ILLEGAL VALUES
         * @param mixed $target Depends on type
         * @param string $type Type of file - 'meta', 'installOptions', 'uninstallOptions','definitions'
         * @param array $params of the form:
         *                  'isFile' => bool, default false - is true, will treat $target as a string of the file contents
         *                                    rather than the URL
         * @returns bool
         * */
        function validatePluginFile(mixed $target, string $type, array $params = []): bool {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['isFile'])?
                $isFile = $params['isFile'] : $isFile = false;
            $res = false;
            $supportedTypes = ['meta','installOptions','uninstallOptions','definitions','dependencies','updateRanges'];
            if(!in_array($type,$supportedTypes))
                return $res;
            if(!$isFile){
                try{
                    $fileContents = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($target,$type.'.json');
                    if(\IOFrame\Util\PureUtilFunctions::is_json($fileContents)){
                        $fileContents = json_decode($fileContents,true);
                    }
                    else if($fileContents == ''){
                        $fileContents = array();
                    }
                    else{
                        $this->logger->warning('Failed to validate plugin file, contents not a json ',['target'=>$target, 'type'=>$type, 'isFile'=>$isFile]);
                        if($verbose)
                            echo 'File contents if '.$type.' are not a JSON!'.EOL;
                        return false;
                    }
                }
                catch(\Exception $e){
                    $this->logger->critical('Failed to validate plugin file, exception '.$e->getMessage(),['target'=>$target, 'type'=>$type, 'isFile'=>$isFile, 'trace'=>$e->getTrace()]);
                    return false;
                }
            }
            else{
                $fileContents = $target;
            }
            switch($type){
                case 'meta':
                    //"name", "version" and "summary" must exist. "version" must contain only numbers.
                    if(
                        isset($fileContents['name']) && isset($fileContents['version']) && isset($fileContents['summary']) &&
                        (strlen($fileContents['name'])>0) && (strlen($fileContents['summary'])>0) && (strlen($fileContents['version'])>0) &&
                        (preg_match('/\/D/',$fileContents['version']) == 0)
                    )
                        $res = true;
                    else
                        $this->logger->warning('Failed to validate plugin file, meta invalid',['target'=>$target, 'type'=>$type, 'isFile'=>$isFile]);
                    break;
                case 'uninstallOptions':
                case 'installOptions':
                    $res = true;
                    foreach($fileContents as $option => $optJSON){
                        //Validate option real name - has to only contain word characters - no spaces!
                        if(preg_match_all('/\w/',$option)<strlen($option) || strlen($option)>255
                            || strlen($option)==0 ){
                            if($verbose)
                                echo 'Option '.$option.' name is invalid!'.EOL;
                            $res = false;
                        }
                        if(!$res)
                            break;
                        //Those 2 have to be set
                        if(!isset($optJSON['name'], $optJSON['type'])){
                            if($verbose)
                                echo 'Option '.$option.' missing name or type!'.EOL;
                            $res = false;
                        }
                        else{
                            //Format for the name
                            if(htmlspecialchars($optJSON['name'])!=$optJSON['name'] || strlen($optJSON['name'])>255
                                || strlen($optJSON['name'])==0 ){
                                if($verbose)
                                    echo 'Option '.$option.' name is of invalid format!'.EOL;
                                $res = false;
                            }
                            //Currently supported types
                            $types = ['number', 'text', 'textarea','radio','checkbox','select','email','password'];
                            if(!in_array($optJSON['type'],$types)){
                                if($verbose)
                                    echo 'Option '.$option.' type invalid!'.EOL;
                                $res = false;
                            }
                            //A list must be set for checkbox, radio or select.
                            if(!isset($optJSON['list']) &&
                                ($optJSON['type'] == 'select' ||$optJSON['type'] == 'checkbox' ||$optJSON['type'] == 'radio')){
                                if($verbose)
                                    echo 'Option '.$option.' missing a list!'.EOL;
                                $res = false;
                            }
                            //If a list is set, validate it
                            if(isset($optJSON['list'])){
                                if(!is_array($optJSON['list'])){
                                    if($verbose)
                                        echo 'Option '.$option.' list not an array!'.EOL;
                                    $res = false;
                                }
                            }
                            //Validate lengths
                            if(isset($optJSON['maxLength'])){
                                if($optJSON['maxLength']<1 || $optJSON['maxLength']>1000000){
                                    if($verbose)
                                        echo 'Option '.$option.' maxLength invalid!'.EOL;
                                    $res = false;
                                }
                            }
                            //Validate lengths
                            if(isset($optJSON['maxNum'])){
                                if($optJSON['maxNum']<1 || !($optJSON['maxNum']<=PHP_INT_MAX)){
                                    if($verbose)
                                        echo 'Option '.$option.' maxNum invalid!'.EOL;
                                    $res = false;
                                }
                            }
                        }
                    }
                    if(!$res)
                        $this->logger->warning('Failed to validate plugin file, options invalid',['target'=>$target, 'type'=>$type, 'isFile'=>$isFile]);
                    break;
                case 'definitions':
                    $res = true;
                    //Just make sure the file is json and all definitions start with a upper case latter, and contain only word characters.
                    if(!is_array($fileContents)){
                        $this->logger->warning('Failed to validate plugin file, definitions not an array',['target'=>$target, 'type'=>$type, 'isFile'=>$isFile]);
                        if($verbose)
                            echo 'Definition file isn\'t an array!'.EOL;
                        $res = false;
                    }
                    else{
                        foreach($fileContents as $def => $val){
                            if(!$res)
                                break;
                            if(preg_match_all('/\w/',$def)<strlen($def) || preg_match('/[A-Z]/',substr($def,0,1)) != 1
                                ||strlen($def)>255){
                                if($verbose)
                                    echo 'Definition file improperly formatted!'.EOL;
                                $res = false;
                            }
                        }
                        if(!$res)
                            $this->logger->warning('Failed to validate plugin file, definition invalid',['target'=>$target, 'type'=>$type, 'isFile'=>$isFile]);
                    }
                    break;
                case 'dependencies':
                    $res = true;
                    //Just make sure the file is json and all definitions start with a upper case latter, and contain only word characters.
                    if(!is_array($fileContents)){
                        if($verbose)
                            echo 'Dependencies file isn\'t an array!'.EOL;
                        $res = false;
                    }
                    else{
                        foreach($fileContents as $def => $val){

                            if(!$res)
                                break;

                            //Name validation
                            if(strlen($def)>64){
                                if($verbose)
                                    echo 'Name too long!'.EOL;
                                $res = false;
                            }
                            if(preg_match('/\W/',$def)==1){
                                if($verbose)
                                    echo 'Name must only contain word characters!'.EOL;
                                $res = false;
                            }

                            //Validate the 2 possible options
                            if(!isset($val['minVersion'])){
                                $res = false;
                            }
                            else{
                                if($val['minVersion']<1 || !($val['minVersion']<=PHP_INT_MAX)){
                                    if($verbose)
                                        echo 'Dependency '.$def.' minVersion invalid!'.EOL;
                                    $res = false;
                                }
                            }
                            if(isset($val['maxVersion'])){
                                if($val['maxVersion']<1 || !($val['maxVersion']<=PHP_INT_MAX)){
                                    if($verbose)
                                        echo 'Dependency '.$def.' maxVersion invalid!'.EOL;
                                    $res = false;
                                }
                            }
                        }
                        if(!$res)
                            $this->logger->warning('Failed to validate plugin file, dependencies invalid',['target'=>$target, 'type'=>$type, 'isFile'=>$isFile]);
                    }
                    break;
                case 'updateRanges':
                    $res = true;
                    //Just make sure the file is json and all definitions start with a upper case latter, and contain only word characters.
                    if(!is_array($fileContents) && !empty($fileContents)){
                        $this->logger->warning('Failed to validate plugin file, updateRanges is not an array',['target'=>$target, 'type'=>$type, 'isFile'=>$isFile]);
                        if($verbose)
                            echo 'updateRanges file isnt an array!'.EOL;
                        $res = false;
                    }
                    else{
                        $currentVal = 0;
                        if(empty($fileContents))
                            $fileContents = [];
                        foreach($fileContents as $index => $val){

                            if(is_array($val)){
                                if(count($val) !== 2){
                                    if($verbose)
                                        echo 'updateRanges range '.$index.' does not have 2 members!'.EOL;
                                    $res = false;
                                }
                                if(!filter_var($val[1],FILTER_VALIDATE_INT)){
                                    if($verbose)
                                        echo 'updateRanges maximum version at index '.$index.' not an integer!'.EOL;
                                    $res = false;
                                }
                                $val = $val[0];
                            }

                            if(!filter_var($val,FILTER_VALIDATE_INT)){
                                if($verbose)
                                    echo 'updateRanges minimal version at index '.$index.' not an integer!'.EOL;
                                $res = false;
                            }

                            if($currentVal >=$val){
                                if($verbose)
                                    echo 'updateRanges minimal version at index '.$index.' is not larger than previous minimal version!'.EOL;
                                $res = false;
                            }
                            else
                                $currentVal = $val;
                        }

                        if(!$res)
                            $this->logger->warning('Failed to validate plugin file, updateRanges range invalid',['target'=>$target, 'type'=>$type, 'isFile'=>$isFile]);
                    }
                    break;
            }

            return $res;
        }

        /** Initially, the icon and thumbnail for the plugin are located inside its folder.
         * However, it is impossible to access them, due to the .htaccess in teh plugins folder denying any access from the web.
         * That restriction has to remain for security reasons. However, we want to be able to display the images somehow.
         * This is why we have this function. It will check whether a folder for a plugin exists inside PLUGIN_IMAGE_FOLDER (named after the plugin).
         * If such folder doesn't exist, creates it.
         *  -Check whether icon, thumbnail or both files exist inside the original plugin.
         *  -If yes, copies them to the plugin folder inside PLUGIN_IMAGE_FOLDER and exits with appropriate code.
         *
         * @param string $pName Plugin name
         * @param array $params
         * @return int|void
         */
        function ensurePublicImage(string $pName ,array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $pUrl = $this->settings->getSetting('absPathToRoot').self::PLUGIN_FOLDER_PATH.self::PLUGIN_FOLDER_NAME.$pName;   //Plugin folder
            $imgUrl = $this->settings->getSetting('absPathToRoot').self::PLUGIN_IMAGE_FOLDER.$pName;   //shared image folder
            $supportedFormats = ['png','jpg','bmp','gif'];                            //Supported image extentions
            $images = array( 'icon'=>array(), 'thumbnail'=> array() );
            foreach($images as $key=>$image){
                $images[$key]['exists'] = false; //Whether the original image exists
                $images[$key]['format'] = '';    //Original image format
                $images[$key]['size'] = 0;       //Original image filesize
                $images[$key]['tocopy'] = false;
                $images[$key]['todelete'] = false;
            }

            //Validation
            if(strlen($pName)>64){
                if($verbose)
                    echo 'Name too long!'.EOL;
                return -1;
            }
            if(preg_match('/\W/',$pName)==1){
                if($verbose)
                    echo 'Plugin name must only contain word characters!'.EOL;
                return -1;
            }

            //Check if plugin exists
            if(!is_dir($pUrl)){
                if($verbose)
                    echo 'Plugin does not exist!'.EOL;
                return -1;
            }
            //Check whether a folder for the plugin exists.
            foreach($supportedFormats as $format){
                foreach($images as $imageType=>$image){
                    if(!$image['exists'])
                        if(file_exists($pUrl.'/'.$imageType.'.'.$format)){
                            $images[$imageType]['exists'] = true;
                            $images[$imageType]['format'] = $format;
                            $images[$imageType]['size'] = filesize($pUrl.'/'.$imageType.'.'.$format);
                            $images[$imageType]['tocopy'] = true;
                            $images[$imageType]['todelete'] = false;
                        }
                }
            }
            //Here we will try to create existing folders if needed, and copy the images if they are outdated (or dont exist).
            try{
                //If a folder does not exist, create it
                if(!is_dir($imgUrl)){
                    //--------------------Create plugins public image folder if needed--------------------
                    if(!is_dir($this->settings->getSetting('absPathToRoot').self::PLUGIN_IMAGE_FOLDER)){
                        if(!mkdir($this->settings->getSetting('absPathToRoot').self::PLUGIN_IMAGE_FOLDER))
                            die('Cannot create base plugin image folder!');
                        //-------------------- Copy default icon/thumbnail into plugins image folder --------------------
                        if(!file_exists($this->settings->getSetting('absPathToRoot').self::PLUGIN_IMAGE_FOLDER.'/def_icon.png'))
                            file_put_contents(
                                $this->settings->getSetting('absPathToRoot').self::PLUGIN_IMAGE_FOLDER.'/def_icon.png',
                                file_get_contents('plugins/def_icon.png')
                            );
                        if(!file_exists($this->settings->getSetting('absPathToRoot').self::PLUGIN_IMAGE_FOLDER.'/def_thumbnail.png'))
                            file_put_contents(
                                $this->settings->getSetting('absPathToRoot').self::PLUGIN_IMAGE_FOLDER.'/def_thumbnail.png',
                                file_get_contents('plugins/def_thumbnail.png')
                            );
                    }
                    if($verbose)
                        echo 'Plugin folder in '.self::PLUGIN_IMAGE_FOLDER.' does not exist, creating..!'.EOL;
                    if(!$test)
                        mkdir($imgUrl);
                }
                else{
                    //Check whether a new, updated images are present
                    foreach($images as $imageType=>$image){
                        if($image['exists']) {
                            //Check whether the file exists, and if it does, whether it matches the original image size.
                            //If it does not it will be deleted and replaced with the image in the plugin folder
                            if(file_exists($imgUrl.'/'.$imageType.'.'.$image['format']))
                                filesize($imgUrl.'/'.$imageType.'.'.$image['format']) == $image['size']?
                                    $images[$imageType]['tocopy'] = false : $images[$imageType]['todelete'] = true;
                        }
                    }
                }
                //For each image, delete the old file if needed, and copy the new one if needed.
                foreach($images as $imageType=>$image){
                    if($image['todelete']) {
                        if($verbose)
                            echo 'Deleting old '.$imageType.EOL;
                        if(!$test)
                            unlink($imgUrl.'/'.$imageType.'.'.$image['format']);
                    }
                    if($image['tocopy']) {
                        if($verbose)
                            echo 'Copying new '.$imageType.EOL;
                        if(!$test)
                            copy($pUrl.'/'.$imageType.'.'.$image['format'],$imgUrl.'/'.$imageType.'.'.$image['format']);
                    }
                }
            }
            catch(\Exception $e){
                $this->logger->critical('Failed to ensure public images, exception '.$e->getMessage(),['plugin'=>$pName,'trace'=>$e->getTrace()]);
                return -2;
            }

            //Return relevant values
            if($images['icon']['exists']){
                if($images['thumbnail']['exists'])
                    return self::PLUGIN_HAS_ICON_THUMBNAIL;
                else
                    return self::PLUGIN_HAS_ICON;
            }
            else{
                if($images['thumbnail']['exists'])
                    return self::PLUGIN_HAS_THUMBNAIL;
                else
                    return 0;
            }
        }

        /** Runs ensurePublicImage on an array of unique names
         * @param string[] $pNameArr Array of string names
         * @param array $params
         * @return false|string
         */
        function ensurePublicImages(array $pNameArr, array $params = []): bool|string {
            $res = [];
            foreach($pNameArr as $value){
                $tempRes = $this->ensurePublicImage($value, $params);
                $res = array_merge($res,[$value=>$tempRes]);
            }
            return json_encode($res);
        }

        /** Makes sure that the dependencies needed to install plugin $name are met.
         *
         * @params string $name
         * @params array $params of the form
         *              'dependencyArray' => Array of the form <dependency name> => ['minVersion'=>X, (OPTIONAL)'maxVersion'=>Y]
         *                                  If dependencyArray is provided, will treat it as the dependency array of $pluginName.
         * @returns int
         * 0 - All fine
         * 1 - $pluginName or its dependency file do not exist.
         * 2 - Some dependencies are not met
         * */
        function validateDependencies(string $name,$params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['dependencyArray'])?
                $dependencyArray = $params['dependencyArray'] : $dependencyArray = [];
            //Validate dependency array
            if(!is_array($dependencyArray) || count($dependencyArray) == 0){
                $plugInfo = $this->getInfo(['name'=>$name])[0];
                if(isset($plugInfo['dependencies']))
                    $dependencies = $plugInfo['dependencies'];
                else{
                    if($verbose)
                        echo 'Plugin '.$name.' or its dependency file do not exist.'.EOL;
                    return 1;
                }
            }
            else
                $dependencies = $dependencyArray;
            $errors = 0;
            //Validate dependencies
            foreach($dependencies as $dep => $versions){

                $dep = $this->getInfo(['name'=>$dep])[0];
                (isset($dep['version']) && filter_var((int)$dep['version'],FILTER_VALIDATE_INT))?
                    $ver = (int)$dep['version']:
                    $ver = -1;

                if( $ver < $versions['minVersion'] || (isset($versions['maxVersion']) && $ver > $versions['maxVersion'])){
                    $errors++;
                    if($verbose)
                        echo 'Plugin '.$name.' is missing '.$dep['fileName'].' dependency, wrong version!'.EOL;
                }

                if($dep['status']!='active'){
                    $errors++;
                    if($verbose)
                        echo 'Plugin '.$name.' is missing '.$dep['fileName'].' dependency, not active!'.EOL;
                }

            }
            if($errors>0){
                if($verbose)
                    echo 'Plugin '.$name.' is missing '.$errors.' dependencies!'.EOL;
                return 2;
            }
            return 0;
        }

        /** Populates dependency map based on given array
         * @params array $dependencies of the form {<name> : { 'minVersion':x, 'maxVersion:y' } }
         * @params string $name is the name of the plugin dependent on those dependencies.
         * @params array $params
         * @returns int
         * 0 - all good
         * 1 - invalid input
         * */
        function populateDependencies(string $name, array $dependencies, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $url = $this->settings->getSetting('absPathToRoot').'localFiles/pluginDependencyMap/';
            if(!is_dir($url))
                if(!mkdir($url)){
                    $this->logger->critical('Failed to populate dependencies, cannot make dir ',['plugin'=>$name,'dir'=>$url]);
                    die('Cannot create Dependency Map directory for some reason - most likely insufficient user privileges.');
                }
            //Validate $name
            if(strlen($name)>64){
                if($verbose)
                    echo 'Name too long!'.EOL;
                return 1;
            }
            if(preg_match('/\W/',$name)==1){
                if($verbose)
                    echo 'Name must only contain word characters!'.EOL;
                return 1;
            }
            foreach($dependencies as $dep => $ver){
                //See whether the folder/file we need already exists, if no create them.
                if(!is_dir($url.$dep)){
                    if(!$test){
                        if(!mkdir($url.$dep)){
                            $this->logger->critical('Failed to populate dependencies, cannot make dir ',['plugin'=>$name,'dir'=>$url.$dep]);
                            die('Cannot create settings directory for some reason - most likely insufficient user privileges.');
                        }
                        else
                            fclose(fopen($url.$dep.'/settings','w'));
                    }
                    if($verbose){
                        echo 'Creating dependency folder at '.$url.$dep.EOL;
                    }
                }
                //Open dependency file and add the dependency
                if(!$test){
                    $depFile = new \IOFrame\Handlers\SettingsHandler($url.$dep.'/',['useCache'=>false]);
                    $depFile->setSetting($name,json_encode($ver),['createNew'=>true]);
                }
                if($verbose)
                    echo 'Adding '.$name.' to '.$dep.' dependency tree!'.EOL;
            }
            return 0;
        }

        /** Checks dependencies on the plugin $name. May validate that the dependant plugins are actually active.
         *
         * @param string $name Name of the plugin to check dependencies of
         * @param array $params of the form:
         *                  'validate' => Validate that all dependencies are active, not just that they exist
         * @returns mixed
         * 0 if no dependencies are present, or validate is true and no dependencies are active.
         * 1 invalid name
         * JSON of the form {<name>:{'minVersion':x[,'maxVersion':y]}} for each plugin that depends on $name (*and is active when validate==true)
         *
         * @throws \Exception
         * @throws \Exception
         */
        function checkDependencies(string $name, array $params = []): bool|int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['validate'])?
                $validate = $params['validate'] : $validate = true;
            $url = $this->settings->getSetting('absPathToRoot').'localFiles/pluginDependencyMap/';

            //Validate $name
            if(strlen($name)>64){
                if($verbose)
                    echo 'Name too long!'.EOL;
                return 1;
            }
            if(preg_match('/\W/',$name)==1){
                if($verbose)
                    echo 'Name must only contain word characters!'.EOL;
                return 1;
            }
            //If there are no dependencies, we are clear
            if(!file_exists($url.$name.'/settings')){
                if($verbose)
                    echo $name.' has no dependencies!'.EOL;
                return 0;
            }
            //Get dependencies
            $depFile = new \IOFrame\Handlers\SettingsHandler($url.$name.'/',['useCache'=>false]);
            $deps = $depFile->getSettings();
            $depNumber = count($deps);
            //Again, there might be 0 dependencies
            if($depNumber<1){
                if($verbose)
                    echo $name.' has no dependencies!'.EOL;
                return 0;
            }
            //if we don't validate, and there are dependencies, return them
            if(!$validate)
                return json_encode($deps);
            //If we do validate, remove each dependency that isn't currently active
            $available = $this->getAvailable();
            foreach($deps as $depName=>$vals){
                if(!isset($available[$depName]))
                    unset($deps[$depName]);
                else{
                    if($available[$depName] != 'active')
                        unset($deps[$depName]);
                }
            }
            if(count($deps)<1){
                if($verbose)
                    echo $name.' has no dependencies!'.EOL;
                return 0;
            }
            if($verbose)
                echo 'Dependencies: '.json_encode($deps).EOL;
            return json_encode($deps);
        }

    }


}