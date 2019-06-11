<?php

namespace IOFrame{
    define('abstractLogger',true);

    require_once 'ext/monolog/vendor/autoload.php';
    if(!defined('abstractSettings'))
        require 'abstractSettings.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    //Define the default log chanel if one isn't defined yet
    if(!defined('LOG_DEFAULT_CHANNEL'))
        define('LOG_DEFAULT_CHANNEL','IOFrame_Def');
    /** Just to be used by abstract classes that require a logger to work
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    abstract class abstractLogger extends abstractSettings
    {
        protected $logger;
        protected $loggerHandler;

        /**
         * Basic construction function
         * @param settingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params An potentially containing an sqlHandler and/or a logger.
         */
        public function __construct(settingsHandler $localSettings,  $params = []){
            parent::__construct($localSettings);

            //Set defaults
            if(!isset($params['sqlHandler']))
                $sqlHandler = null;
            else
                $sqlHandler = $params['sqlHandler'];

            if(!isset($params['logger']))
                $logger = null;
            else
                $logger = $params['logger'];

            if($logger != null)
                $this->logger = $logger;
            elseif($sqlHandler != null){
                $this->loggerHandler = new IOFrameHandler($this->settings, $sqlHandler);
                $this->logger = new Logger(LOG_DEFAULT_CHANNEL);
                $this->logger->pushHandler($this->loggerHandler);
            }
        }
    }


}