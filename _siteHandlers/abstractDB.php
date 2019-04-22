<?php
namespace IOFrame{

    require_once 'abstractLogger.php';
    require_once 'sqlHandler.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    /**
     * To be extended by modules operate user info (login, register, etc)
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    abstract class abstractDB extends abstractLogger
    {

        /** Starting from this abstract class and up, everything needs to decide it's default settings mode.
         *  This dictates how handlers extending this class will create settings, and should be set in the main function.
         * */
        protected $defaultSettingsParams;

        /**
         * Basic construction function
         * @param settingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params An potentially containing an sqlHandler and/or a logger.
         */
        public function __construct(settingsHandler $localSettings,  $params = []){

            //Set defaults
            if(!isset($params['sqlHandler']))
                $sqlHandler = null;
            else
                $sqlHandler = $params['sqlHandler'];

            if(!isset($params['opMode']))
                $opMode = SETTINGS_OP_MODE_LOCAL;
            else
                $opMode = $params['opMode'];

            //Has to be set before parent construct due to sqlHandler depending on it, and Logger depending on the outcome
            $this->settings=$localSettings;
            $this->sqlHandler = ($sqlHandler == null)? new sqlHandler($this->settings) : $sqlHandler;

            //In case it was missing earlier, it isn't anymore. Make sure to pass it to the Logger
            $params['sqlHandler'] = $this->sqlHandler;

            //Starting from this class, extending classes have to decide their default setting mode.
            $this->defaultSettingsParams['opMode'] = $opMode;
            if($opMode != SETTINGS_OP_MODE_LOCAL)
                $this->defaultSettingsParams['sqlHandler'] = $this->sqlHandler;


            parent::__construct($localSettings,$params);
        }
    }

}