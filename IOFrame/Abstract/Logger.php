<?php

namespace IOFrame\Abstract{

    use IOFrame\Handlers\SettingsHandler;

    define('IOFrameAbstractLogger',true);

    //Define the default log chanel if one isn't defined yet
    /** Just to be used by abstract classes that require a logger to work
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    abstract class Logger extends \IOFrame\Abstract\Settings
    {
        /** @var \Monolog\Logger
         * */
        public \Monolog\Logger $logger;
        /**
         * @var \IOFrame\Managers\Integrations\Monolog\IOFrameHandler
         */
        public \IOFrame\Managers\Integrations\Monolog\IOFrameHandler $loggerHandler;

        /**
         * Basic construction function
         * @param SettingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params A potentially containing an SQLManager and/or a logger.
         */
        public function __construct(\IOFrame\Handlers\SettingsHandler $localSettings, array $params = []){
            parent::__construct($localSettings);

            $this->logger = $params['logger'] ?? new \Monolog\Logger($params['logChannel']??\IOFrame\Definitions::LOG_DEFAULT_CHANNEL);
            $this->loggerHandler = $params['logHandler'] ?? new \IOFrame\Managers\Integrations\Monolog\IOFrameHandler($this->settings);
            if(!($params['disableLogging']??false))
                $this->logger->pushHandler($this->loggerHandler);
        }
    }


}