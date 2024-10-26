<?php
/* This API has the following uses:
 *
 *      See standard return values at defaultInputResults.php
 *_________________________________________________
 * getAvailable
 *      - Get available plugin (specified by name) or all plugins.
 *        Returns a json encoded string of the format:
 *          {'pluginName':'status'}
 *
 *        Examples: action=getAvailable&name=testPlugin, action=getAvailable
 *_________________________________________________
 * getInfo
 *      - Get info of a specific plugin, or of all plugins.
 *        Returns a 2D json encoded string of the format:
 *          {"0":
 *                  {"fileName":<Name of folder or listed in /localFiles/plugins>
 *                  "status": <active/legal/illegal/absent/zombie/installing>,
 *                  ["name": <Plugin Name>,
 *                  "version": <Plugin Version>,
 *                  "summary": <Summary of the plugin>,
 *                  "description": <A full description of the plugin>,
 *                  "icon": True/False,
 *                  "thumbnail": True/False,
 *                  "uninstallOptions": <JSON string>
 *                  "installOptions": <JSON string>]
 *                  }
 *              },
 *           "1":...
 *          }
 *
 *      Examples: action=getInfo&name=testPlugin, action=getInfo
 *_________________________________________________
 * getOrder
 *      - Get the current order of plugins, if there is any.
 *        Returns a string of the form '<plugin1>,<plugin2>,...', '' if there is no order.
 *        Example: action=getOrder
 *_________________________________________________
 * pushToOrder [CSRF protected]
 *      - Add a plugin by name to the top or bottom of the list, depending on whether 'toTop' parameter is true.
 *        Will instead push the plugin to a specified spot (sending the rest down) if index is specified and over 0.
 *        The plugin must be installed ("active"), unless the parameter 'verify' is set to false.
 *        Returns
 *          0 on success,
 *
 *
 *          3 if could read file/db,
 *          4 if failed to verify plugin is active
 *
 *      Examples: action=pushToOrder&name=hohoPlugin&toTop=true&verify=false
 *                action=pushToOrder&name=testPlugin&index=4&verify=true&backup=true
 *_________________________________________________
 * removeFromOrder [CSRF protected]
 *      - Remove a plugin from the order list.
 *        type is 'index' or 'name'
 *        target is the index (number) or name of the plugin, depending on $type.
 *        if backup is "true", backs up the order
 *        Returns:
 *        0 - success
 *        1 - index or name don't exist
 *        2 - incorrect type
 *        3 - couldn't read or write file, or order is not an array
 *        4 - removing the target violates its dependencies
 *      Examples: action=removeFromOrder&target=testPlugin&type=name
 *                action=removeFromOrder&target=madeUpPlugin&type=name
 *                action=removeFromOrder&target=1&type=index&backup=true
 *                action=removeFromOrder&target=4&type=index&backup=true
 *                action=removeFromOrder&target=-1&type=index&backup=true
 *_________________________________________________
 * moveOrder [CSRF protected]
 *      - Move a plugin from one index in the order list to another.
 *        from=<index>,to=<index>,backup=true/false - self explanatory (backup same as earlier actions)
 *        Returns
 *        0 - success
 *        1 - one of the indices is not set (or empty order file)
 *        2 - couldn't open order file, or order is not an array
 *        JSON of the form {'fromName':'violatedDependency'} - $validate is true (default), and dependencies would be violated by moving the plugin in the order
 *
 *      Examples: action=moveOrder&from=0&to=2
 *_________________________________________________
 * swapOrder [CSRF protected]
 *      - Swap 2 plugins in the order list.
 *        p1=<index>,p2=<index>,backup=true/false - self explanatory (backup same as earlier actions)
 *        Returns
 *        0 - success
 *        1 - one of the indices is not set (or empty order file)
 *        2 - couldn't open order file, or order is not an array
 *        JSON of the form {'p1/p2Name':'violatedDependency'} - $validate is true (default), and dependencies would be violated by swapping the 2 plugins in the order
 *      Examples: action=swapOrder&p1=0&p2=2
 *                action=swapOrder&p1=6&p2=3
 *_________________________________________________
 * install/fullInstall [CSRF protected]
 *      - Installs a plugin
 *        If the $name specified is a legal, uninstalled plugin, installs it.
 *        'name' should be a name of a legal plugin (you can get a list of legal ones through getAvailable API, too).
 *        'options' should be a JSON string of options, formatted {"option":"value",...}
 *             - it is the ONLY part that should be filtered inside the installer itself, although their format is enforced.
 *       'override' can be set to true if you want to install a plugin even if it is illegal (but the install file is there).
 *        Returns:
 *       0 installation was complete and without errors
 *       1 plugin is already installed or zombie
 *       2 quickInstall.php is missing, or if the plugin is illegal and $override is false.
 *       3 dependencies are missing.
 *       4 missing or illegal options
 *       5 plugin definitions are similar to existing system definitions  - will also echo exception
 *       6 exception thrown during install  - will also echo exception

 *      Example: action=install&name=testPlugin&options={"testOption":"hoho"}&override=true
 *
 *      *--*
 *      Full Install is available - just includes fullInstall.php of the plugin 'name', if it exists
 *      Example: action=fullInstall&name=testPlugin
 *_________________________________________________
 * uninstall/fullUninstall [CSRF protected]
 *      - Uninstalls a plugin
 *        'name' should be a name of an active plugin (you can get a list of active ones through getAvailable API, too).
 *        'options' should be a JSON string of options, formatted {"option":"value",...}
 *             - it is the ONLY part that should be filtered inside the installer itself, although their format is enforced.
 *       'override' can be set to true if you want to uninstall a plugin even if it isn't active (but the uninstall file is there).
 *        Returns:
 *      0 the plugin was uninstalled successfully
 *      1 the plugin was "absent" OR  override == false and the plugin isn't listed as installed
 *      2 quickUninstall.php wasn't found
 *      3 Override is false, and there are dependencies on this plugin
 *      4 uninstallOptions mismatch with given options
 *      5 Could not remove definitions  - will also echo exception
 *      6 Exception during uninstall - will also echo exception
 *
 *      Example: action=uninstall&name=testPlugin&options={"testOption":"haha"}&override=true
 *  *
 *      *--*
 *      Full Uninstall is available - just includes fullUninstall.php of the plugin 'name', if it exists
 *      Example: action=fullUninstall&name=testPlugin
 *_________________________________________________
 * update [CSRF protected]
 *      - Updates a plugin
 *        If the $name specified is a legal, installed plugin, updates it.
 *        'name' should be a name of a legal plugin (you can get a list of legal ones through getAvailable API, too).
 *        'once' can be set to true if you want to update the plugin only once (rather than to the highest possible version).
 *
 *         Returns an assoc array of the form
 *      {
 *          'resultType' => 'error' - on error code
 *                          'success' - success with no errors
 *                          'success-partial' - succeeded at least one iteration, then failed
 *          'newVersion' => int, on any result that isn't an error, will return the new plugin version
 *          'result' => On error, one of the following codes:
 *                      0 plugin not installed
 *                      1 One of the required update files is missing.
 *                      2 No new updates possible for current version
 *                      -- During each iteration --
 *                      3 Updating would violate existing dependencies (maxVersion of dependant plugin).
 *                      4 exception thrown during update - will also populate exception
 *                      5 critical error - exception thrown during updateFallback - will also populate exceptionInFallback
 *
 *                      On success or partial success, possible codes are:
 *                      0 plugin updated successfully
 *                      3 same as earlier
 *                      4 same as earlier
 *                      5 same as earlier
 *          'exception' => string, empty, populated on a specific update exception.
 *          'exceptionInFallback' => string, empty, populated on a specific updateFallback exception.
 *          'moreUpdates' => bool, only set to true if we stopped updating due to the version requirement chain being broken
 *      }
 *      Example: action=update&name=testPlugin
 *      Example: action=update&name=testPlugin&once=true
 *
 */

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'plugins_fragments/definitions.php';

if($test){
    echo 'Testing mode!'.EOL;
    foreach($_REQUEST as $key=>$value)
        echo htmlspecialchars($key.': '.$value).EOL;
}

//Input validation function
function checkInput($settings,$SQLManager,$SessionManager,$logger ,$test = false): void {
    //Make sure there is an action
    if(!isset($_REQUEST['action']))
        exit('No action specified');

    //Make sure the action is valid, and has all relevant parameters set.
    //Also, make sure the user is authorized to perform the action.
    $AuthHandler = new \IOFrame\Handlers\AuthHandler($settings,['SQLManager'=>$SQLManager,'logger'=>$logger]);
    switch($_REQUEST['action']){
        case 'getAvailable':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_GET_AVAILABLE_AUTH))){
                if($test)
                    echo 'Insufficient auth to use getAvailable!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            break;
        case 'getInfo':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_GET_INFO_AUTH))){
                if($test)
                    echo 'Insufficient auth to use getInfo!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            break;
        case 'getOrder':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_GET_ORDER_AUTH))){
                if($test)
                    echo 'Insufficient auth to use getOrder!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            break;
        case 'pushToOrder':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_PUSH_TO_ORDER_AUTH))){
                if($test)
                    echo 'Insufficient auth to use pushToOrder!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            if(!isset($_REQUEST['name'])){
                if($test)
                    echo 'Name must be specified with pushToOrder!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!validateThenRefreshCSRFToken($SessionManager))
                exit(WRONG_CSRF_TOKEN);
            break;
        case 'removeFromOrder':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_REMOVE_FROM_ORDER_AUTH))){
                if($test)
                    echo 'Insufficient auth to use removeFromOrder!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            if(!isset($_REQUEST['target'])){
                if($test)
                    echo 'Target must be specified with removeFromOrder!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!isset($_REQUEST['type'])){
                if($test)
                    echo 'Type must be specified with removeFromOrder!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!validateThenRefreshCSRFToken($SessionManager))
                exit(WRONG_CSRF_TOKEN);
            break;
        case 'moveOrder':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_MOVE_ORDER_AUTH))){
                if($test)
                    echo 'Insufficient auth to use moveOrder!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            if(!isset($_REQUEST['from'])){
                if($test)
                    echo 'from must be specified with moveOrder!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!isset($_REQUEST['to'])){
                if($test)
                    echo 'to must be specified with moveOrder!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!validateThenRefreshCSRFToken($SessionManager))
                exit(WRONG_CSRF_TOKEN);
            break;
        case 'swapOrder':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_SWAP_ORDER_AUTH))){
                if($test)
                    echo 'Insufficient auth to use swapOrder!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            if(!isset($_REQUEST['p1'])){
                if($test)
                    echo 'p1 must be specified with swapOrder!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!isset($_REQUEST['p2'])){
                if($test)
                    echo 'p2 must be specified with swapOrder!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!validateThenRefreshCSRFToken($SessionManager))
                exit(WRONG_CSRF_TOKEN);
            break;
        case 'fullInstall':
        case 'install':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_INSTALL_AUTH))){
                if($test)
                    echo 'Insufficient auth to use install!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            if(!isset($_REQUEST['name'])){
                if($test)
                    echo 'Name must be specified with install!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!validateThenRefreshCSRFToken($SessionManager))
                exit(WRONG_CSRF_TOKEN);
            break;
        case 'fullUninstall':
        case 'uninstall':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_UNINSTALL_AUTH))){
                if($test)
                    echo 'Insufficient auth to use uninstall!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            if(!isset($_REQUEST['name'])){
                if($test)
                    echo 'Name must be specified with uninstall!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!validateThenRefreshCSRFToken($SessionManager))
                exit(WRONG_CSRF_TOKEN);
            break;
        case 'update':
            if(!($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_UNINSTALL_AUTH))){
                if($test)
                    echo 'Insufficient auth to use update!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
            if(!isset($_REQUEST['name'])){
                if($test)
                    echo 'Name must be specified with update!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!validateThenRefreshCSRFToken($SessionManager))
                exit(WRONG_CSRF_TOKEN);
            break;
        default:
            if($test)
                echo 'Specified action is not recognized'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
    }

    //You may only set Validate to false if your authorization is 0
    if(isset($_REQUEST['validate'])){
        if( !( ($AuthHandler->isAuthorized() || $AuthHandler->hasAction(PLUGIN_IGNORE_VALIDATION)) ) && !$_REQUEST['validate']){
            if($test)
                echo 'Insufficient authorization to ignore plugin validation'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }

    //Validate name
    if(isset($_REQUEST['name'])){
        if(!\IOFrame\Util\ValidatorFunctions::validateSQLTableName($_REQUEST['name'])){
            if($test)
                echo 'Illegal name!!'.EOL;
        }
    }
    //Validate override
    if(isset($_REQUEST['override'])){
        if(!($_REQUEST['override'] == 'true' || $_REQUEST['override'] == 'false')){
            if($test)
                echo 'override must be true or false!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    //Validate backup
    if(isset($_REQUEST['backup'])){
        if(!($_REQUEST['backup'] == 'true' || $_REQUEST['backup'] == 'false')){
            if($test)
                echo 'backup must be true or false!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    //Validate toTop
    if(isset($_REQUEST['toTop'])){
        if(!($_REQUEST['toTop'] == 'true' || $_REQUEST['toTop'] == 'false')){
            if($test)
                echo 'toTop must be true or false!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    //Validate verify
    if(isset($_REQUEST['verify'])){
        if(!($_REQUEST['verify'] == 'true' || $_REQUEST['verify'] == 'false')){
            if($test)
                echo 'verify must be true or false!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    //Validate "validate"
    if(isset($_REQUEST['validate'])){
        if(!($_REQUEST['validate'] == 'true' || $_REQUEST['validate'] == 'false')){
            if($test)
                echo 'verify must be true or false!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    //Validate from && to
    if(isset($_REQUEST['from'])||isset($_REQUEST['to'])){
        if(!(isset($_REQUEST['from'])&&isset($_REQUEST['to']))){
            if($test)
                echo 'If from or to are set, both must be set!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        if(preg_match('/\D/',$_REQUEST['from']) || preg_match('/\D/',$_REQUEST['to'])){
            if($test)
                echo 'from and to must only contain digits! '.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        if($_REQUEST['from']<0 || $_REQUEST['to']<0 || $_REQUEST['from'] == $_REQUEST['to']){
            if($test)
                echo 'from and to must be non negative (and not equal)! '.$_REQUEST['from'].', '.$_REQUEST['to'].EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    //Validate p1 && p2
    if(isset($_REQUEST['p1'])||isset($_REQUEST['p2'])){
        if(!(isset($_REQUEST['p1'])&&isset($_REQUEST['p2']))){
            if($test)
                echo 'If p1 or p2 are set, both must be set!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        if(preg_match('/\D/',$_REQUEST['p1']) || preg_match('/\D/',$_REQUEST['p2'])){
            if($test)
                echo 'P1 and P2 must only contain digits! '.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        if($_REQUEST['p1']<0 || $_REQUEST['p2']<0 || $_REQUEST['p1'] == $_REQUEST['p2']){
            if($test)
                echo 'P1 and P2 must be non negative (and not equal)! '.$_REQUEST['p1'].', '.$_REQUEST['p2'].EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    //Validate index (from pushToOrder)
    if(isset($_REQUEST['index'])){
        if(preg_match('/\D/',$_REQUEST['index'])){
            if($test)
                echo 'index must only contain digits! '.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        if($_REQUEST['index']<-1){
            if($test)
                echo 'index must be -1 or larger. It cannot be smaller than -1 to avoid coder mistakes.'.$_REQUEST['index'].EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    //Validate target/type in case of removeFromOrder
    if(isset($_REQUEST['target'])||isset($_REQUEST['type'])){
        if(!(isset($_REQUEST['target'])&&isset($_REQUEST['type']))){
            if($test)
                echo 'If target or type are set, both must be set!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        switch($_REQUEST['type']){
            case 'index':
                if(preg_match('/\D/',$_REQUEST['target'])){
                    if($test)
                        echo 'target must only contain digits! '.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                if($_REQUEST['target']<0){
                    if($test)
                        echo 'target must be 0 or larger.'.$_REQUEST['index'].EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;
            case 'name':
                if(!\IOFrame\Util\ValidatorFunctions::validateSQLTableName($_REQUEST['target'])){
                    if($test)
                        echo 'Illegal target name!!'.EOL;
                }
                break;
            default:
                if($test)
                    echo 'Unrecognized type!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
        }
    }
    //Validate options for (un)install
    if(isset($_REQUEST['options'])){
        if(!\IOFrame\Util\PureUtilFunctions::is_json($_REQUEST['options'])){
            if($test)
                echo 'options must be valid JSON! '.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}

//Check input
checkInput($settings,$SQLManager,$SessionManager,new \Monolog\Logger(\IOFrame\Definitions::LOG_PLUGINS_CHANNEL),$test);

if(!checkApiEnabled('plugins',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

//Do what needs to be done
switch($_REQUEST['action']){

    //Assuming we got here, the user is authorized. Now, if his action was "getAvalilable" or "getInfo", he might need
    //to see the plugins' images (icon, thumbnail). So, we make sure to call ensurePublicImages on each.

    case 'getAvailable':
        isset($_REQUEST['name'])?
            $name = $_REQUEST['name'] : $name = '' ;
        $res = $PluginHandler->getAvailable(['name'=>$name]);
        $PluginHandler->ensurePublicImages(array_keys($res));
        echo json_encode($res);
        break;
    case 'getInfo':
        isset($_REQUEST['name'])?
            $name = $_REQUEST['name'] : $name = '' ;
        $res = $PluginHandler->getInfo(['name'=>$name,'test'=>$test]);
        $PluginHandler->ensurePublicImages(array_map(function($pluginInfo){return $pluginInfo['fileName'];},$res));
        echo json_encode($res);
        break;
    case 'getOrder':
        echo json_encode($PluginHandler->getOrder(['local'=>false,'test'=>$test]));
        break;
    case 'pushToOrder':
            $name = $_REQUEST['name'];
        isset($_REQUEST['index'])?
            $index = $_REQUEST['index'] : $index = -1 ;
        isset($_REQUEST['toTop'])?
            $toTop = $_REQUEST['toTop'] : $toTop = false ;
        isset($_REQUEST['verify'])?
            $verify = $_REQUEST['verify'] : $verify = true ;
        isset($_REQUEST['backUp'])?
            $backUp = $_REQUEST['backUp'] : $backUp = true ;
        if(
            $PluginHandler->pushToOrder(
                $name,
                [
                    'index'=> $index,
                    'verify'=> $verify,
                    'backUp'=> $backUp,
                    'local'=> false,
                    'test'=>$test
                ]
            )
        )
            echo 0;
        else
            echo 1;
        break;
    case 'removeFromOrder':
        $target = $_REQUEST['target'];
        $type = $_REQUEST['type'];
        isset($_REQUEST['backUp'])?
            $backUp = $_REQUEST['backUp'] : $backUp = true ;
        $validate = true;
        if(isset($_REQUEST['validate']))
            if(!$_REQUEST['validate'])
                $validate = false;
        echo $PluginHandler->removeFromOrder(
            $target,
            $type,
            ['verify'=>$validate,'backUp'=>$backUp,'local'=>false,'test'=>$test]
        );
        break;
    case 'moveOrder':
        $from = $_REQUEST['from'];
        $to = $_REQUEST['to'];
        isset($_REQUEST['backUp'])?
            $backUp = $_REQUEST['backUp'] : $backUp = false ;
        $validate = true;
        if(isset($_REQUEST['validate']))
            if(!$_REQUEST['validate'])
                $validate = false;
        echo $PluginHandler->moveOrder(
            $from,
            $to,
            ['verify'=>$validate,'backUp'=>$backUp,'local'=>false,'test'=>$test]
        );
        break;
    case 'swapOrder':
        $num1 = $_REQUEST['p1'];
        $num2 = $_REQUEST['p2'];
        isset($_REQUEST['backUp'])?
            $backUp = $_REQUEST['backUp'] : $backUp = false ;
        $validate = true;
        if(isset($_REQUEST['validate']))
            if(!$_REQUEST['validate'])
                $validate = false;
        echo $PluginHandler->swapOrder(
            $num1,
            $num2,
            ['verify'=>$validate,'backUp'=>$backUp,'local'=>false,'test'=>$test]
        );
        break;
    case 'install':
        $name = $_REQUEST['name'];
        isset($_REQUEST['options'])?
            $options = json_decode($_REQUEST['options'],true) : $options = array() ;
        isset($_REQUEST['override'])?
            $override = $_REQUEST['override'] : $override = false;
        echo htmlspecialchars($PluginHandler->install($name,$options,['override'=>$override,'test'=>$test]));
        break;
    case 'fullInstall':
        $name = $_REQUEST['name'];
        $url = $settings->getSetting('absPathToRoot').'plugins/'.$name.'/fullInstall.php';
        if(file_exists($url))
            require($url);
        break;
    case 'uninstall':
        $name = $_REQUEST['name'];
        isset($_REQUEST['options'])?
            $options = json_decode($_REQUEST['options'],true) : $options = array() ;
        isset($_REQUEST['override'])?
            $override = $_REQUEST['override'] : $override = true;
        echo htmlspecialchars($PluginHandler->uninstall($name,$options,['override'=>$override,'test'=>$test]));
        break;
    case 'fullUninstall':
        $name = $_REQUEST['name'];
        $url = $settings->getSetting('absPathToRoot').'plugins/'.$name.'/fullUninstall.php';
        if(file_exists($url))
            require($url);
        break;
    case 'update':
        $name = $_REQUEST['name'];
        (isset($_REQUEST['once']) && $_REQUEST['once'])?
            $iterationLimit = 1 : $iterationLimit = -1;
        echo json_encode($PluginHandler->update($name,['iterationLimit'=>$iterationLimit,'test'=>$test]));
        break;
    default:
        exit('Specified action is not recognized');
}


