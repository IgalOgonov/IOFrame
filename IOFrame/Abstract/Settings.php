<?php

namespace IOFrame\Abstract{
    define('IOFrameAbstractSettings',true);

    /** Just to be used by abstract classes that require $settings to work
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */

    abstract class Settings
    {
        public \IOFrame\Handlers\SettingsHandler $settings;

        /**
         * Basic construction function
         * @param \IOFrame\Handlers\SettingsHandler $settings Any type of settings
         */
        function setSettingsHandler(\IOFrame\Handlers\SettingsHandler $settings): void {
            $this->settings=$settings;
        }

        public function __construct(\IOFrame\Handlers\SettingsHandler $settings){
            $this->settings=$settings;
        }

    }


}