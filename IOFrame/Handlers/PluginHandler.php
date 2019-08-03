<?php
namespace IOFrame\Handlers{
    use IOFrame;
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;
    define('PluginHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    if(!defined('FileHandler'))
        require 'FileHandler.php';
    if(!defined('OrderHandler'))
        require 'OrderHandler.php';

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

    //If we did not define PLUGIN_FOLDER_NAME, the default is "plugins"
    if(!defined('PLUGIN_FOLDER_NAME')){
        define('PLUGIN_FOLDER_NAME','plugins/');
    }

    //If we did not define PLUGIN_FOLDER_PATH, the default is the root folder
    if(!defined('PLUGIN_FOLDER_PATH')){
        define('PLUGIN_FOLDER_PATH','');
    }
    //If we did not define PLUGIN_IMAGE_PATH, the default is here
    if(!defined('PLUGIN_IMAGE_FOLDER')){
        define('PLUGIN_IMAGE_FOLDER','front/ioframe/img/pluginImages/');
    }

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
     *                  "desc":"Rent additional vihicles",    --------- checked, the array would be
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
     *  ------------include.php---------------- | REQUIRED
     *  This file will be included to run at the end of utilCore.php - only for an active plugin, though!
     *  Plugins are included in the same order they appear at /localFiles/plugins, unless they are explicitly added to
     *  /plugins/order - a simple text file of the format "<Plugin Name 1>, <Plugin Name 2>, ..." that will specify
     *  specific plugins that need to be run first, and in a specific order.
     *
     *  ------------meta.json------------------ | REQUIRED
     *  This file is a JSON of the format:
     * {"name":<Plugin Name>,
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
     *  ------------icon.png|jpg|bmp|gif------- | OPTIONAL
     *  The icon is meant to represent the plugin in a small list - 64x64
     *
     *  ------------thumbnail.png|jpg|bmp|gif-- | OPTIONAL
     *  This thumbnail is meant to represent the plugin in a larger list - 256x128
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class PluginHandler extends IOFrame\abstractDBWithCache{

        private $FileHandler = null;

        private $OrderHandler = null;

        /**
         * @var Int Tells us for how long to cache stuff by default.
         * 0 means indefinitely.
         */
        protected $cacheTTL = 3600;

        /* Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param object $settings The standard settings object
         * @param object $conn The standard DB connection object
         * */
        function __construct(SettingsHandler $settings, $params = []){

            parent::__construct($settings,$params);

            if(isset($params['AuthHandler']))
                $this->AuthHandler = $params['AuthHandler'];

            //Create new file handler
            $this->FileHandler = new FileHandler();

            //Create new order handler
            $params['name'] = 'plugin';
            $params['localURL'] =  $this->settings->getSetting('absPathToRoot').'/localFiles';
            $params['tableName'] =  'CORE_VALUES';
            $params['columnNames'] = [
                0 => 'tableKey',
                1 => 'tableValue'
            ];
            $params['FileHandler'] = $this->FileHandler;
            $this->OrderHandler = new OrderHandler($settings,$params);
        }

        /** Gets available plugins
         *
         * Returns an array of all of the plugins who's folder lies in /plugins. If they follow the correct structure of a plugin -
         * aka, having at least full/quickInstall.php, quickUninstall.php, include.php and a correctly formatted meta.json, they are
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
        */
        function getAvailable($params = []){

            //Set defaults
            if(isset($params['name']))
                $name = $params['name'];
            else
                $name = '';

            $res = array();
            $plugList = new SettingsHandler($this->settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/plugins/');  //Listed plugins

            $url = $this->settings->getSetting('absPathToRoot').PLUGIN_FOLDER_PATH.PLUGIN_FOLDER_NAME;   //Plugin folder
            $folderUrls = array();                                              //Just the folders in plugin folder
            if($name == ''){
                $dirArray = scandir($url);                                          //All files in plugin folder
                foreach($dirArray as $key => $fileUrl){
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
                foreach($plugList->getSettings() as $plugin => $status){
                    if(!(array_key_exists($plugin,$res)))
                        $res[$plugin] = 'absent';
                    else
                        if($status == 'installed' && $res[$plugin] == 'legal')
                            $res[$plugin] = 'active';
                }
            }
            else if ($name != '')
                if($plugList->getSetting($name) == 'installed' && isset($res[$name]))
                    if($res[$name] == 'legal')
                        $res[$name] = 'active';
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
         * { "fileName":<Name of folder or listed in /localFiles/plugins>
         *   "status": <active/legal/illegal/absent/zombie/installing>,
         *   "name": <Plugin Name>,
         *   "version": <Plugin Version>,
         *   ["summary": <Summary of the plugin>,]
         *   ["description": <A full description of the plugin>,]
         *   "icon": <image type>,
         *   "thumbnail": <image type>,
         *   "uninstallOptions": <JSON string>
         *   "installOptions": <JSON string>
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
        */
        function getInfo(array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            isset($params['name'])?
                $name = $params['name'] : $name = '';

            $res = array();
            $plugList = new SettingsHandler($this->settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'plugins/');  //Listed plugins
            $url = $this->settings->getSetting('absPathToRoot').PLUGIN_FOLDER_PATH.PLUGIN_FOLDER_NAME;   //Plugin folder
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
                $res[$num] =array();
                $res[$num] = $this->getAvailable(['name'=>$name]);
                $res[$num]['fileName'] = $name;
                $res[$num]['status'] = $res[$num][$name];
                if($res[$num][$name] == 'absent' || $res[$num][$name] == 'illegal'){
                    //Do nothing else if the plugin is absent or illegal
                }
                else{
                    $installOpt = 'installOptions';
                    $uninstallOpt = 'uninstallOptions';
                    $fileUrl = $url.$name;
                    $LockHandler = new LockHandler($fileUrl);
                    //Get the meta data and update it
                    $meta = json_decode($this->FileHandler->readFileWaitMutex($fileUrl,'meta.json',[]),true);
                    // Important to escape using htmlspecialchars, in case meta.json contains some nasty stuff
                    $res[$num]['name']=htmlspecialchars($meta['name']);
                    $res[$num]['version']=$meta['version'];
                    if(isset($meta['summary']))  $res[$num]['summary']=htmlspecialchars($meta['summary']);
                    if(isset($meta['description']))  $res[$num]['description']=htmlspecialchars($meta['description']);
                    //Start by checking if the plugin has fullInstall, quickInstall, or both - has to have one at least, because it's legal
                    if(file_exists($fileUrl.'/fullUninstall.php')){
                        file_exists($fileUrl.'/quickUninstall.php')                       ?
                            $res[$num]['uninstallStatus'] = PLUGIN_HAS_BOTH_UNINSTALL :
                            $res[$num]['uninstallStatus'] = PLUGIN_HAS_FULL_UNINSTALL ;
                    }
                    else{
                        $res[$num]['uninstallStatus'] = PLUGIN_HAS_QUICK_UNINSTALL ;
                    }
                    //Same for install
                    if(file_exists($fileUrl.'/fullInstall.php')){
                        file_exists($fileUrl.'/quickInstall.php')                       ?
                            $res[$num]['installStatus'] = PLUGIN_HAS_BOTH_INSTALL :
                            $res[$num]['installStatus'] = PLUGIN_HAS_FULL_INSTALL ;
                    }
                    else{
                        $res[$num]['installStatus'] = PLUGIN_HAS_QUICK_INSTALL ;
                    }

                    //Now, onto the other files.
                    if($LockHandler->waitForMutex()){
                        //open and read meta.json
                        $metaFile = @fopen($fileUrl.'/meta.json',"r") or die("Cannot open");
                        $meta = fread($metaFile,filesize($fileUrl.'/meta.json'));
                        fclose($metaFile);
                        $meta = json_decode($meta,true);
                        array_merge($res[$num],$meta);
                        //open and read install options if those exist
                        if(file_exists($fileUrl.'/'.$installOpt.'.json')){
                            $instFile = @fopen($fileUrl.'/'.$installOpt.'.json',"r") or die("Cannot open");
                            $inst = @fread($instFile,filesize($fileUrl.'/'.$installOpt.'.json'));
                            fclose($instFile);
                        }
                        else{
                            $inst = null;
                        }
                        //Install Options exist?
                        if($inst != null)
                            if(IOFrame\Util\is_json($inst)){
                                //Ensure options are legal
                                if($this->validatePluginFile(json_decode($inst,true),$installOpt,['isFile'=>true,'test'=>$test,'verbose'=>$verbose]))
                                    $res[$num]['installOptions'] = json_decode($inst,true);
                            }
                        //open and read uninstall options if those exist
                        if(file_exists($fileUrl.'/'.$uninstallOpt.'.json')){
                            $uninstFile = @fopen($fileUrl.'/'.$uninstallOpt.'.json',"r") or die("Cannot open");
                            $uninst = @fread($uninstFile,filesize($fileUrl.'/'.$uninstallOpt.'.json'));
                            fclose($uninstFile);
                        }
                        else{
                            $uninst = null;
                        }
                        //Uninstall Options exist?
                        if($uninst != null)
                            if(IOFrame\Util\is_json($uninst)){
                                //Ensure options are legal
                                if($this->validatePluginFile(json_decode($uninst,true),$uninstallOpt,['isFile'=>true,'test'=>$test,'verbose'=>$verbose]))
                                    $res[$num]['uninstallOptions'] = json_decode($uninst,true);
                            }
                        //Dependencies
                        if(file_exists($fileUrl.'/dependencies.json')){
                            $depFile = @fopen($fileUrl.'/dependencies.json',"r") or die("Cannot open");
                            $dep = @fread($depFile,filesize($fileUrl.'/dependencies.json'));
                            fclose($depFile);
                        }
                        else{
                            $dep = null;
                        }
                        //Dependencies exist?
                        if($dep != null)
                            if(IOFrame\Util\is_json($dep)){
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
         * @param array $options An array of options for the installer.
         * @param array $params Parameters of the form:
         *              'override' => Whether to try to install despite plugin being considered illegal. Default false
         *              'local' => Install plugin locally as opposed to adding it to globally. Defaults to true in CLI mode, false otherwise.
         *
         * @returns integer Returns
         * 0 installation was complete and without errors
         * 1 plugin is already installed or zombie
         * 2 quickInstall.php is missing, or if the plugin is illegal and $override is false.
         * 3 dependencies are missing.
         * 4 missing or illegal options
         * 5 plugin definitions are similar to existing system definitions  - will also echo exception
         * 6 exception thrown during install  - will also echo exception
         * */
        function install(string $name, array $options = [], array $params = []){

            //Set defaults

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            if(isset($params['override']))
                $override = $params['override'];
            else
                $override = false;

            if(isset($params['local']))
                $local = $params['local'];
            else
                if (php_sapi_name() == "cli") {
                    $local = true;
                } else {
                    $local = false;
                }

            $url = $this->settings->getSetting('absPathToRoot').PLUGIN_FOLDER_PATH.PLUGIN_FOLDER_NAME.$name.'/';   //Plugin folder
            $LockHandler = new LockHandler($url);
            $plugList = new SettingsHandler($this->settings->getSetting('absPathToRoot').SETTINGS_DIR_FROM_ROOT.'/plugins/');
            $plugName = $this->getInfo(['name'=>$name])[0];

            //-------Check if the plugin is installed
            if($plugList->getSetting($name) == 'installed' || $plugList->getSetting($name) == 'zombie' || $plugList->getSetting($name) == 'installing'){
                if($verbose)
                    echo 'Plugin '.$name.' is either installed, installing or zombie!'.EOL;
                return 1;
            }

            //-------Check if the plugin is illegal with override off, or the install is missing
            if($plugName['status'] != 'legal'){
                if($override && $plugName['status'] == 'illegal' &&
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
                return 2;
            }

            //-------Validate dependencies
            if(isset($plugName['dependencies']))
                $dependencies = $plugName['dependencies'];
            else
                $dependencies = [];
            if($this->validateDependencies($name,['dependencyArray'=>$dependencies,'test'=>$test,'verbose'=>$verbose]) > 1)
                return 3;

            //-------Time to validate options
            if(!$this->validateOptions('installOptions',$url,$name,$options,['test'=>$test,'verbose'=>$verbose]))
                return 4;

            //-------Change plugin to "installing"
            if(!$test)
                $plugList->setSetting($name,'installing',['createNew'=>true]);

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
                    $defFile = $this->FileHandler->readFileWaitMutex($url,'definitions.json',['LockHandler' => $LockHandler]);
                    if($defFile != null){       //If the file is empty, don't bother doing work
                        $defArr = json_decode($defFile,true);
                        $gDefFile = $this->FileHandler->readFileWaitMutex($gDefUrl,'definitions.json',['LockHandler' => $LockHandler]);
                        $gDefArr = json_decode($gDefFile,true);
                        if(is_array($gDefArr))
                            $newDef = array_merge($defArr,$gDefArr);
                        else
                            $newDef = $defArr;
                        //Write to global definition file after backing it up
                        if(!$test){
                            $defLock = new LockHandler($gDefUrl);
                            $defLock->makeMutex();
                            $this->FileHandler->backupFile($gDefUrl,'definitions.json');
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
                    echo 'Exception :'.$e.EOL;
                    try{
                        $options = [];
                        require $url.'quickUninstall.php';
                        $plugList->setSetting($name,null,['createNew'=>true]);
                    }
                    catch (\Exception $e){
                        if($verbose)
                            echo 'Exception during definition inclusion of plugin '.$name.': '.$e.EOL;
                    }
                    return 5;
                }
            }

            //-------Finally, include the install file
            try{
                require $url.'quickInstall.php';
            }catch
            (\Exception $e){
                try{
                    $options = [];
                    require $url.'quickUninstall.php';
                    $plugList->setSetting($name,null,['createNew'=>true]);
                }
                catch (\Exception $e){
                    if($verbose)
                        echo 'Exception during install of plugin '.$name.': '.$e.EOL;
                }
                return 6;
            }
            //-------Populate dependency map
            $this->populateDependencies($name,$dependencies,['test'=>$test,'verbose'=>$verbose]);

            //-------Change plugin to "installed"
            if(!$test)
                $plugList->setSetting($name,'installed',['createNew'=>true]);

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
         * @param array $options Array of options for the uninstaller.
         * @param array $params of the form:
         *          'override' => bool, default true - whether to continue uninstalling even if the plugin is not marked as 'active'
         *          'local' => bool, default depends on PHP Mode (web vs cli) - whether to only change local state, or global state.
         *
         * @returns mixed Returns
         * 0 the plugin was uninstalled successfully
         * 1 the plugin was "absent" OR  override == false and the plugin isn't listed as installed
         * 2 quickUninstall.php wasn't found
         * 3 Override is false, and there are dependencies on this plugin
         * 4 uninstallOptions mismatch with given options
         * 5 Could not remove definitions  - will also echo exception
         * 6 Exception during uninstall - will also echo exception
         * */
        function uninstall(string $name, $options = [], $params = []){

            //Set defaults
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            if(isset($params['override']))
                $override = $params['override'];
            else
                $override = true;

            if(isset($params['local']))
                $local = $params['local'];
            else
                if (php_sapi_name() == "cli") {
                    $local = true;
                } else {
                    $local = false;
                }

            $url = $this->settings->getSetting('absPathToRoot').PLUGIN_FOLDER_PATH.PLUGIN_FOLDER_NAME.$name.'/';   //Plugin folder
            $depUrl = $this->settings->getSetting('absPathToRoot').'localFiles/pluginDependencyMap/';
            $LockHandler = new LockHandler($url);
            $plugList = new SettingsHandler($this->settings->getSetting('absPathToRoot').SETTINGS_DIR_FROM_ROOT.'/plugins/');
            $plugName = $this->getInfo(['name'=>$name])[0];

            //-------Check if the plugin is absent - or if override is false while the plugin isn't listed installed.
            $goOn = ($plugName['status'] != 'absent');
            if($goOn && !$override && $plugName['status'] != 'active')
                $goOn = false;
            if(!$goOn){
                if($verbose)
                    echo 'Plugin '.$name.' absent, can not uninstall!'.EOL;
                if(($plugName['status'] == 'absent') && !$test) //Only remove the plugin from the list if its actually absent
                    $plugList->setSetting($name,null);
                return 1;
            }

            //-------Make sure quickUninstall exists
            if(!file_exists($url.'quickUninstall.php')){
                if($verbose)
                    echo 'Plugin '.$name.' quickUninstall absent, can not uninstall!'.EOL;
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
                $plugList->setSetting($name,'zombie');

            //-------Validate options
            if(!$this->validateOptions('uninstallOptions',$url,$name,$options,['test'=>$test,'verbose'=>$verbose]))
                return 4;

            //-------Call quickUninstall.php - REMEMBER - OPTIONS ARRAY MUST BE FILTERED
            try{
                require $url.'quickUninstall.php';
            }
            catch(\Exception $e){
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
            $dep = json_decode($this->FileHandler->readFileWaitMutex($url,'dependencies.json',[]),true);
            if(is_array($dep))
                foreach($dep as $pName=>$ver){
                    if(file_exists($depUrl.$pName.'/settings')){
                        if(!$test){
                            $depHandler = new SettingsHandler($depUrl.$pName.'/',['useCache'=>false]);
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
                    return 5;
                }
                //Now remove the definitions from the system definition file
                try{
                    $gDefUrl = $this->settings->getSetting('absPathToRoot').'localFiles/definitions/';
                    //Read definition files - and remove the matching ones
                    $defFile = $this->FileHandler->readFileWaitMutex($url,'definitions.json',['LockHandler' => $LockHandler]);
                    if($defFile != null){       //If the file is empty, don't bother doing work
                        $defArr = json_decode($defFile,true);
                        $gDefFile = $this->FileHandler->readFileWaitMutex($gDefUrl,'definitions.json',['LockHandler' => $LockHandler]);
                        $gDefArr = json_decode($gDefFile,true);
                        foreach($defArr as $def=>$val){
                            if(isset($gDefArr[$def]))
                                if($gDefArr[$def] == $val){
                                    unset($gDefArr[$def]);
                                }
                        }
                        //Write to global definition file after backing it up
                        if(!$test){
                            $defLock = new LockHandler($gDefUrl);
                            $defLock->makeMutex();
                            $this->FileHandler->backupFile($gDefUrl,'definitions.json');
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

        /** See OrderHandler documentation
         * @param array $params
         * @return mixed
         * */
        function getOrder($params = []){
            return $this->OrderHandler->getOrder($params);
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
        function pushToOrder(string $name, $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            if(isset($params['verify']))
                $verify = $params['verify'];
            else
                $verify = true;

            //Verify first, if $verify == true
            if($verify == true){
                if($verbose)
                    echo 'Verifying that plugin '.$name.' is active!'.EOL;
                $info = $this->getAvailable(['name'=>$name]);
                if( ($info[$name] != 'active')){
                    if($verbose)
                        echo 'Cannot push a plugin into order that is not active!'.EOL;
                    if(!$test)
                        return 4;
                }
            }

            return $this->OrderHandler->pushToOrder($name,$params);
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
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            //Set defaults
            if(isset($params['verify']))
                $verify = $params['verify'];
            else
                $verify = true;

            $order = $this->OrderHandler->getOrder($params);

            $params['order'] = is_array($order)? implode(',',$order): $order;

            $params['indexChecksOnly'] = true;
            $indexChecks = $this->OrderHandler->removeFromOrder($target,$type,$params);
            $params['indexChecksOnly'] = false;

            if($indexChecks != 0)
                return $indexChecks;

            //Verify dependencies
            if($verify){
                if($verbose)
                    echo 'Verifying that plugin '.$order[$target].' has no dependencies!'.EOL;
                $dep = $this->checkDependencies($order[$target],['validate'=>true,'test'=>$test,'verbose'=>$verbose]);
                if($dep !== 0){
                    if($verbose)
                        echo 'Plugin '.$order[$target].' dependencies are '.$dep.', can not remove!'.EOL;
                    return $dep;
                }
            }

            return $this->OrderHandler->removeFromOrder($target,$type,$params);
        }

        /** Moves a plugin from one index in the order list to another,
         * pushing everything it passes 1 space up/down in the opposite direction
         * @param int $from
         * @param int $to
         * @param array $params of the form:
         *              'verify' => bool, default true - Verify plugin dependencies before changing order
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'backUp' => bool, default false - Back up local file after changing
         * @returns mixed
         * 0 - all good
         * 1 - from or to indexes are not set, or illegal
         * 2 - could not open file
         * 3 - failed to write to db
         * JSON of the form {'fromName':'violatedDependency'} - $validate is true, and dependencies would be violated by
         *  moving the plugin in the order
         * */
        function moveOrder(int $from, int $to, $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            //Set defaults
            if(isset($params['verify']))
                $verify = $params['verify'];
            else
                $verify = true;

            $order = $this->OrderHandler->getOrder($params);

            $params['order'] = is_array($order)? implode(',',$order): $order;

            $params['indexChecksOnly'] = true;
            $indexChecks = $this->OrderHandler->moveOrder($from,$to,$params);
            $params['indexChecksOnly'] = false;

            if($indexChecks != 0)
                return $indexChecks;

            //Verify dependencies
            if($verify){
                $pluginUrl = $this->settings->getSetting('absPathToRoot').PLUGIN_FOLDER_PATH.PLUGIN_FOLDER_NAME;
                $fromName = $order[$from];

                if($verbose)
                    echo 'Verifying that plugin '.$fromName.' can be moved from '.$from.' to '.$to. '!'.EOL;

                //If we push something upwards, we need to make sure its own dependencies are not violated
                if($from > $to){
                    $dep = json_decode($this->FileHandler->readFileWaitMutex($pluginUrl.$fromName,'dependencies.json',[]),true);
                    if(is_array($dep))
                        for($i = $to; $i<$from; $i++){
                            //This would mean a dependency would get swapped underneath us, which is illegal.
                            //Remember that $order[i] is the name of each plugin in the order, and $dep is an array whos keys are plugins
                            //We are dependant on.
                            if( array_key_exists($order[$i],$dep) ){
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
                                return json_encode([$fromName=>$order[$i]]);
                            }
                        }
                }
            }

            return $this->OrderHandler->moveOrder($from,$to,$params);
        }

        /** Swaps 2 plugins in the order
         * @param int $num1
         * @param int $num2
         * @param array $params of the form:
         *              'verify' => bool, default true - Verify plugin dependencies before changing order
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'backUp' => bool, default false - Back up local file after changing
         * @returns mixed
         * 0 - success
         * 1 - one of the indices is not set (or empty order file), or not integers
         * 2 - couldn't open order file, or order is not an array
         * 3 - Could not write to db
         * JSON of the form {'fromName':'violatedDependency'} - $validate is true, and dependencies would be violated by
         *  moving the plugin in the order
         * */
        function swapOrder(int $num1,int $num2, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            //Set defaults
            if(isset($params['verify']))
                $verify = $params['verify'];
            else
                $verify = true;

            $order = $this->OrderHandler->getOrder($params);

            $params['order'] = is_array($order)? implode(',',$order): $order;

            $params['indexChecksOnly'] = true;
            $indexChecks = $this->OrderHandler->moveOrder($num1,$num1,$params);
            $params['indexChecksOnly'] = false;

            if($indexChecks != 0)
                return $indexChecks;

            //Validate dependencies
            if($verify){

                if($verbose)
                    echo 'Verifying that plugins at '.$num1.' and '.$num2.' can be swapped!'.EOL;

                $pluginUrl = $this->settings->getSetting('absPathToRoot').PLUGIN_FOLDER_PATH.PLUGIN_FOLDER_NAME;
                //If we push something upwards, we need to make sure its own dependencies are not violated
                $dep = json_decode($this->FileHandler->readFileWaitMutex($pluginUrl.$order[$num2],'dependencies.json',[]),true);
                if(is_array($dep))
                    for($i = $num1; $i<$num2; $i++){
                        //This would mean a dependency would get swapped underneath us, which is illegal.
                        //Remember that $order[i] is the name of each plugin in the order, and $dep is an array whos keys are plugins
                        //We are dependant on.
                        if( array_key_exists($order[$i],$dep) ){
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
                            if($verbose)
                                echo 'Order swap would violate '.$order[$i].' dependency on '.$order[$num1].EOL;
                            return json_encode([$order[$num1]=>$order[$i]]);
                        }
                    }
            }

            return $this->OrderHandler->moveOrder($num1,$num2,$params);
        }

        /** Checks whether a the contents of the folder at $url are contents of a valid plugin folder
         * @param string $url url to the plugin
         * @param array $params
         * @returns bool
         * */
        function validatePlugin(string $url,array $params = []){
            $params['isFile'] = false;
            $res = false;
            if( (file_exists($url.'/quickInstall.php') || file_exists($url.'/fullInstall.php')) &&
                (file_exists($url.'/quickUninstall.php') || file_exists($url.'/fullUninstall.php')) &&
                file_exists($url.'/include.php'))
                $res = $this->validatePluginFile($url,'meta',$params);
            return $res;
        }

        /** Makes sure the format of array $options matches the option file $target at $url. $target is '(un)installOptions'
         * @param string $target One of the two - "installOptions" / "uninstallOptions"
         * @param string $url Url to the options file
         * @param string $name Name of the plugin
         * @param array $options Options array (probably user provided)
         * @param array $params
         * @returns bool
         * */
        function validateOptions(string $target, string $url, string $name, array $options, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $params['isFile'] = false;
            //-------Time to validate options
            if(!$this->validatePluginFile($url,$target,$params)){
                if($verbose)
                    echo $target.' of '.$name.' are invalid!'.EOL;
                return false;
            }
            try{
                $optionsFile = $this->FileHandler->readFileWaitMutex($url,$target.'.json',[]);
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
        function validatePluginFile($target, string $type,array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            isset($params['isFile'])?
                $isFile = $params['isFile'] : $isFile = false;
            $res = false;
            $supportedTypes = ['meta','installOptions','uninstallOptions','definitions','dependencies'];
            if(!in_array($type,$supportedTypes))
                return $res;
            if(!$isFile){
                try{
                    $fileContents = $this->FileHandler->readFileWaitMutex($target,$type.'.json',[]);
                    if(IOFrame\Util\is_json($fileContents)){
                        $fileContents = json_decode($fileContents,true);
                    }
                    else if($fileContents == ''){
                        $fileContents = array();
                    }
                    else{
                        if($verbose)
                            echo 'File contents are not a JSON!'.EOL;
                        return false;
                    }
                }
                catch(\Exception $e){
                    return false;
                }
            }
            else{
                $fileContents = $target;
            }
            switch($type){
                case 'meta':
                    //"name", "version" and "summary" must exist. "version" must contain only numbers.
                    if(isset($fileContents['name']) && isset($fileContents['version']) && isset($fileContents['summary']))
                        if(strlen($fileContents['name'])>0 && strlen($fileContents['summary'])>0 && strlen($fileContents['version'])>0 &&
                            preg_match('/\/D/',$fileContents['version']) == 0){
                            $res = true;
                        }
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
                    break;
                case 'definitions':
                    $res = true;
                    //Just make sure the file is json and all definitions start with a upper case latter, and contain only word characters.
                    if(!is_array($fileContents)){
                        if($verbose)
                            echo 'Definition file isnt an array!'.EOL;
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
                    }
                    break;
                case 'dependencies':
                    $res = true;
                    //Just make sure the file is json and all definitions start with a upper case latter, and contain only word characters.
                    if(!is_array($fileContents)){
                        if($verbose)
                            echo 'Definition file isnt an array!'.EOL;
                        $res = false;
                    }
                    else{
                        foreach($fileContents as $def => $val){

                            if(!$res)
                                break;

                            //Name validation
                            if(strlen($def>64)){
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
         * @returns int
         * -2 - Auth failure - copying, deleting or creating the images.
         * -1 - plugin does not exist, or illegal name
         * 0  - Plugin has neither icon nor thumbnail
         * PLUGIN_HAS_ICON (should be 1) - plugin only has an icon.
         * PLUGIN_HAS_THUMBNAIL (should be 2) - plugin only has a thumbnail.
         * PLUGIN_HAS_ICON_THUMBNAIL (should be 3)- plugin has both an icon and a thumbnail.
         * */
        function ensurePublicImage(string $pName ,array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $pUrl = $this->settings->getSetting('absPathToRoot').PLUGIN_FOLDER_PATH.PLUGIN_FOLDER_NAME.$pName;   //Plugin folder
            $imgUrl = $this->settings->getSetting('absPathToRoot').PLUGIN_IMAGE_FOLDER.$pName;   //shared image folder
            $supportedFormats = ['png','jpg','bmp','gif'];                            //Supported image extentions
            $images = array( 'icon'=>array(), 'thumbnail'=> array() );
            foreach($images as $key=>$image){
                $images[$key]['exists'] = false; //Whether the original image exists
                $images[$key]['format'] = '';    //Original image format
                $images[$key]['size'] = 0;       //Original image filesize
                $images[$key]['tocopy'] = false;
                $images[$key]['todelete'] = false;
            };

            //Validation
            if($pName>64){
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
                };
            }
            //Here we will try to create existing folders if needed, and copy the images if they are outdated (or dont exist).
            try{
                //If a folder does not exist, create it
                if(!is_dir($imgUrl)){
                    //--------------------Create plugins public image folder if needed--------------------
                    if(!is_dir($this->settings->getSetting('absPathToRoot').PLUGIN_IMAGE_FOLDER)){
                        if(!mkdir($this->settings->getSetting('absPathToRoot').PLUGIN_IMAGE_FOLDER))
                            die('Cannot create base plugin image folder!');
                        //-------------------- Copy default icon/thumbnail into plugins image folder --------------------
                        if(!file_exists($this->settings->getSetting('absPathToRoot').PLUGIN_IMAGE_FOLDER.'/def_icon.png'))
                            file_put_contents(
                                $this->settings->getSetting('absPathToRoot').PLUGIN_IMAGE_FOLDER.'/def_icon.png',
                                file_get_contents('plugins/def_icon.png')
                            );
                        if(!file_exists($this->settings->getSetting('absPathToRoot').PLUGIN_IMAGE_FOLDER.'/def_thumbnail.png'))
                            file_put_contents(
                                $this->settings->getSetting('absPathToRoot').PLUGIN_IMAGE_FOLDER.'/def_thumbnail.png',
                                file_get_contents('plugins/def_thumbnail.png')
                            );
                    }
                    if($verbose)
                        echo 'Plugin folder in '.PLUGIN_IMAGE_FOLDER.' does not exist, creating..!'.EOL;
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
                    };
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
                };
            }
            catch(\Exception $e){
                if($verbose)
                    var_dump($e);
                return -2;
            }

            //Return relevant values
            if($images['icon']['exists']){
                if($images['thumbnail']['exists'])
                    return PLUGIN_HAS_ICON_THUMBNAIL;
                else
                    return PLUGIN_HAS_ICON;
            }
            else{
                if($images['thumbnail']['exists'])
                    return PLUGIN_HAS_THUMBNAIL;
                else
                    return 0;
            }
        }

        /** Runs ensurePublicImage on an array of unique names
         * @param string[] $pNameArr Array of string names
         * @param array $params
         * @returns string results for each name in a JSON string e.g: "{'testPlugin':3, 'ghostTest':-1}"
         * */
        function ensurePublicImages(array $pNameArr, array $params = []){
            $res = array();
            foreach($pNameArr as $value){
                $tempRes = $this->ensurePublicImage($value, $params);
                $res += array($value => $tempRes);
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
        function validateDependencies(string $name,$params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            isset($params['dependencyArray'])?
                $dependencyArray = $params['dependencyArray'] : $dependencyArray = [];
            //Validate dependency array
            if(!is_array($dependencyArray) || count($dependencyArray) == 0){
                $plugName = $this->getInfo(['name'=>$name])[0];
                if(isset($plugName['dependencies']))
                    $dependencies = $plugName['dependencies'];
                else{
                    if($verbose)
                        echo 'Plugin '.$name.' or its dependency file do not exist!'.EOL;
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
                        echo 'Plugin '.$name.' is missing '.$dep.' dependency, wrong version!'.EOL;
                }

                if($dep['status']!='active'){
                    $errors++;
                    if($verbose)
                        echo 'Plugin '.$name.' is missing '.$dep.' dependency, not active!'.EOL;
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
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $url = $this->settings->getSetting('absPathToRoot').'localFiles/pluginDependencyMap/';
            if(!is_dir($url))
                if(!mkdir($url))
                    die('Cannot create Dependency Map directory for some reason - most likely insufficient user privileges.');
            //Validate $name
            if(strlen($name>64)){
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
                        if(!mkdir($url.$dep))
                            die('Cannot create settings directory for some reason - most likely insufficient user privileges.');
                        else
                            fclose(fopen($url.$dep.'/settings','w'));
                    }
                    if($verbose){
                        echo 'Creating dependency folder at '.$url.$dep.EOL;
                    }
                }
                //Open dependency file and add the dependency
                if(!$test){
                    $depFile = new SettingsHandler($url.$dep.'/',['useCache'=>false]);
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
         * */
        function checkDependencies(string $name, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            isset($params['validate'])?
                $validate = $params['validate'] : $validate = true;
            $url = $this->settings->getSetting('absPathToRoot').'localFiles/pluginDependencyMap/';
            //Validate $name
            if(strlen($name>64)){
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
            $depFile = new SettingsHandler($url.$name.'/',['useCache'=>false]);
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






?>