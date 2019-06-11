<?php
namespace IOFrame{
    define('sessionHandler',true);

    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;


    /** Handles client sessions.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class sessionHandler extends abstractDBWithCache{

        /**
         * Basic construction function
         * @param settingsHandler $settings regular settings handler.
         * @param sqlHandler $sqlHandler regular sqlHandler handler.
         * @param Logger $logger regular Logger
         */
        function __construct(settingsHandler $localSettings, $params = []){
            parent::__construct($localSettings,$params);

            if(isset($params['siteSettings']))
                $this->siteSettings = $params['siteSettings'];
            else
                $this->siteSettings = new settingsHandler(
                    $localSettings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
                    $this->defaultSettingsParams
                );
        }

        /** Checks whether the session expired and logs user out (without damaging the "rememberMe" parameters) if it did
         * @return bool Returns true if the session is up to date, else false
         * */
        function checkSessionNotExpired(){
            $now = time();
            $res = true;

            //If session is too old, this session has worn out its welcome; kill it and start a brand new one.
            // Also log out user who is registered to this session - wouldn't want a delayed session hijack, now would we?
            if (isset($_SESSION['discard_after']) && $now > $_SESSION['discard_after']) {

                //Create a userHandler
                if(!defined('userHandler'))
                    require 'userHandler.php';
                $userHandler = new userHandler(
                    $this->settings,
                    $this->defaultSettingsParams
                );
                //Logout - without forgetting the relog credentials!
                $userHandler->logOut(['forgetMe'=>false]);
                unset($userHandler);
                //Return will be false
                $res = false;
            }
            //Either way, this next session will be valid for maxInacTime seconds from now
            $_SESSION['discard_after'] = $now + $this->siteSettings->getSetting('maxInacTime');

            return $res;
        }
    }

}
?>