<?php
namespace IOFrame{

    require_once 'abstractDB.php';
    require_once 'redisHandler.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    /**
     * To be extended by modules operate user info (login, register, etc)
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    abstract class abstractDBWithCache extends abstractDB
    {
        /** @var redisHandler $redisHandler a redis-PHP handler
        */
        protected $redisHandler;

        /**
         * Basic construction function
         * @param settingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params An potentially containing an sqlHandler and/or a logger and/or a redisHandler.
         */
        public function __construct(settingsHandler $localSettings,  $params = []){

            parent::__construct($localSettings,$params);

            //Set defaults
            if(!isset($params['redisHandler']))
                $this->redisHandler = null;
            else
                $this->redisHandler = $params['redisHandler'];

            if($this->redisHandler != null){
                $this->defaultSettingsParams['redisHandler'] = $this->redisHandler;
                $this->defaultSettingsParams['useCache'] = true;
            }


        }

    }

}