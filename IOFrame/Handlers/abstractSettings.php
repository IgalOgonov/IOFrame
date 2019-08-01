<?php

namespace IOFrame{
    define('abstractSettings',true);

    /** Just to be used by abstract classes that require $settings to work
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */

    //Defining this in the root include - though all those definitions should probably go to an earlier include for just definitions
    if(!defined('EOL')){
        if (php_sapi_name() == "cli") {
            define("EOL",PHP_EOL);
        } else {
            define("EOL",'<br>');;
        }
    }

    abstract class abstractSettings
    {
        protected $settings;

        /**
         * Basic construction function
         * @param Handlers\SettingsHandler $settings Any type of settings
         */
        function setSettingsHandler(Handlers\SettingsHandler $settings){
            $this->settings=$settings;
        }

        public function __construct(Handlers\SettingsHandler $settings){
            $this->settings=$settings;
        }

    }


}