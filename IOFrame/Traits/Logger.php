<?php
namespace IOFrame\Traits{

    use IOFrame\Handlers\SettingsHandler;

    define('IOFrameTraitsLogger',true);

    /** Allows adding a logger without using the interface Abstract\Logger (in case you got $localSettings already from elsewhere)
     */
    trait Logger{
        /** @var ?\Monolog\Logger
         * */
        protected ?\Monolog\Logger $logger = null;
        /**
         * @var ?\IOFrame\Managers\Integrations\Monolog\IOFrameHandler
         */
        protected ?\IOFrame\Managers\Integrations\Monolog\IOFrameHandler $loggerHandler = null;

        /** Logger construction function
         * @param SettingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params A potentially containing an SQLManager and/or a logger.
         */
        function _constructLogger(\IOFrame\Handlers\SettingsHandler $localSettings, array $params = []): void {
            $this->logger = $params['logger'] ?? new \Monolog\Logger($params['logChannel']??\IOFrame\Definitions::LOG_DEFAULT_CHANNEL);
            $this->loggerHandler = $params['logHandler'] ?? new \IOFrame\Managers\Integrations\Monolog\IOFrameHandler($localSettings);
            if(!($params['disableLogging']??false))
                $this->logger->pushHandler($this->loggerHandler);
        }
    }
}