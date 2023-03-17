<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('RedisHandler',true);
    if(!defined('helperFunctions'))
        require __DIR__ . '/../Util/helperFunctions.php';
    if(!defined('abstractSettings'))
        require 'abstractSettings.php';

    /**Handles interfacing with redis -relies on Phpredis.
     * Supports PHPRedis v >=4.0.0, with the newest Redis as of 2019.02
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */
    class RedisHandler extends IOFrame\abstractSettings{
        /** @param bool $isInit Is true when the handler is initiated. Will be false when Redisphp isn't present.
         */
        public bool $isInit = false;
        /** @var \Redis $r A redis class as defined in Phpredis. Not to be used directly*/
        protected ?\Redis $r = null;

        function __construct($redisSettings){
            if(!class_exists('Redis')){
                $this->isInit = false;
                return false;
            }
            parent::__construct($redisSettings);
            $redisAddress = $this->settings->getSetting('redis_addr');
            $redisPort = $this->settings->getSetting('redis_port');
            $redisTimeout = $this->settings->getSetting('redis_timeout');
            $redisPersist = $this->settings->getSetting('redis_default_persistent');
            $redisPass = $this->settings->getSetting('redis_password');
            $redisPrefix = $this->settings->getSetting('redis_prefix');
            $redisSerializer = $this->settings->getSetting('redis_serializer');
            $redisScanRetry = $this->settings->getSetting('redis_scan_retry');

            //Set defaults (and return when required settings not found)
            if($redisAddress === null){
                //error_log ( 'Attempting to use RedisHandler without an valid address!' , E_USER_ERROR );
                return false;
            }
            if($redisPort === null)
                $redisPort = 6379;
            if($redisTimeout === null)
                $redisTimeout = 5;

            $this->r = new \Redis();

            //Caching is optional, so failure to connect should not terminate the program
            try{
                if($redisPersist)
                    $this->r->pconnect($redisAddress,$redisPort,$redisTimeout);
                else
                    $this->r->connect($redisAddress,$redisPort,$redisTimeout);
            }
            catch(\Exception $e){
                //error_log ( 'Redis connection failed! Error: '.$e , E_USER_ERROR );
                return false;
            }

            //Authenticate if needed (although a plaintext password is useless either way)
            if($redisPass !== null)
                $this->r->auth($redisPass);

            //At this point, we are ready to go, so we should be marked as initiated
            $this->isInit = true;

            //Set options
            if($redisPrefix !== null)
                $this->r->setOption(\Redis::OPT_PREFIX,$redisPrefix);
            if($redisSerializer !== null)
                $this->r->setOption(\Redis::OPT_SERIALIZER,$redisSerializer);
            if($redisScanRetry !== null)
                $this->r->setOption(\Redis::OPT_SCAN,$redisScanRetry);

            return true;
        }

        /** Function to use instead of interacting with Phpredis directly.
         * The reason it is here is because calls need to fail when the instance is not initiated,
         * which would throw a RedisException if called directly with an invalid connection.
         * My apologies in advance to all the IDE users who are used to auto-complete (including me).
         *
         * @param string $functionName The function to call
         * @param mixed $params Either a single parameter, or an array of multiple parameters.
         *
         * @returns mixed The result of the Redis function, whatever that might be.
        */
        function call(string $functionName, $params){
            if(!$this->isInit){
                //error_log ( 'Attempting to use RedisHandler without an active connection!' , E_USER_ERROR );
                return false;
            }

            if(!is_array($params))
                $params = [$params];
            return call_user_func_array(array($this->r, $functionName), $params);
        }
    }
}
?>