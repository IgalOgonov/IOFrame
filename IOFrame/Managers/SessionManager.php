<?php
namespace IOFrame\Managers{
    define('IOFrameManagersSessionManager',true);

    /** Handles client sessions.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class SessionManager extends \IOFrame\Abstract\DBWithCache{

        /**
         * Basic construction function
         * @param \IOFrame\Handlers\SettingsHandler $localSettings regular settings handler.
         * @param array $params
         *              'siteSettings' - Optionally pass prepared siteSettings
         * @throws \Exception
         * @throws \Exception
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $localSettings, array $params = []){
            parent::__construct($localSettings,$params);

            if(isset($params['siteSettings']))
                $this->siteSettings = $params['siteSettings'];
            else
                $this->siteSettings = new \IOFrame\Handlers\SettingsHandler(
                    $localSettings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
                    $this->defaultSettingsParams
                );
        }

        /** Checks whether the session expired and logs user out (without damaging the "rememberMe" parameters) if it did
         * @return bool Returns true if the session is up to date, else false
         * */
        function checkSessionNotExpired(): bool {
            $now = time();
            $res = true;

            //If session is too old, this session has worn out its welcome; kill it and start a brand new one.
            // Also log out user who is registered to this session - wouldn't want a delayed session hijack, now would we?
            if (isset($_SESSION['discard_after']) && ( $now > $_SESSION['discard_after']) ) {

                //Create a UsersHandler
                $UsersHandler = new \IOFrame\Handlers\UsersHandler(
                    $this->settings,
                    $this->defaultSettingsParams
                );
                //Logout - without forgetting the relog credentials, unless user passed rememberMeLimit
                $shouldRelog = ( $UsersHandler->userSettings->getSetting('rememberMeLimit')  ?? 31536000 ) > ( $now - $_SESSION['discard_after'] );
                $UsersHandler->logOut( [ 'forgetMe'=> !$shouldRelog ] );
                unset($UsersHandler);
                //Return will be false
                $res = $shouldRelog;
            }
            //Either way, this next session will be valid for maxInacTime seconds from now
            $_SESSION['discard_after'] = $now + $this->siteSettings->getSetting('maxInacTime');

            return $res;
        }

        /*Creates a new CSRF token*/
        function reset_CSRF_token(): void {
            $hex_secure = false;
            $hex = '';
            while(!$hex_secure)
                $hex=bin2hex(openssl_random_pseudo_bytes(16,$hex_secure));
            $_SESSION['CSRF_token'] = $hex;
        }
    }

}