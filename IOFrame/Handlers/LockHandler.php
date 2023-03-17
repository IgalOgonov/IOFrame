<?php
namespace IOFrame\Handlers{
    use IOFrame;

    define('LockHandler',true);

    /**Handles local cross platform concurrency in IOFrame.
     * Also able to create redis mutexes - works properly when running a single Redis node.
    * @author Igal Ogonov <igal1333@hotmail.com>
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */
    class LockHandler{

        /** @var string Where you want the lock you're watching to reside
         * */
        private $lockUrl;

        /** @var string What type of lock you want - at the moment, only mutex TODO add more types
         * */
        private $type;

        /** @var IOFrame\Handlers\RedisHandler RedisHandler - relevant when using a redis SINGLE NODE mutex
         * */
        private $RedisHandler = null;


        /**
         * Basic construction function
         * @param string $str Full fileapth of the locked FOLDER.
         * @param string $type type of lock
         */
        function __construct(string $str, string $type = 'mutex', $params = []){
            $this->lockUrl = $str;
            $this->type = $type;
            if(isset($params['RedisHandler']))
                $this->RedisHandler = $params['RedisHandler'];

        }


        /** Will try to create a mutex over $secs seocnds in $tries attempts, return true if succeeded else return false.
         * It will only fail if the resource is being used by something else all this time.
         * @param string $urlSuffix at @waitForMutex
         * @param int $secs at @waitForMutex
         * @param int $tries at @waitForMutex
         * @returns bool true on success and false on failure;
         */
        function makeMutex($params = []){
            //Set defaults
            if(!isset($params['sec']))
                $sec = 2;
            else
                $sec = $params['sec'];

            if(!isset($params['tries']))
                $tries = 100;
            else
                $tries = $params['tries'];

            if(!isset($params['urlSuffix']))
                $urlSuffix = '';
            else
                $urlSuffix = $params['urlSuffix'];

            if($this->waitForMutex(['sec'=>$sec,'tries'=>$tries])){
                @$myfile = fopen($this->lockUrl.$urlSuffix.'_mutex', "w");
                if($myfile){
                    fwrite($myfile,time());
                    fclose($myfile);
                    return true;
                };
            }

            return false;
        }

        /** Deletes a mutex from the settings folder
         * It will only fail if the resource is being used by something else all this time.
         * @param string $urlSuffix at @waitForMutex
         * @param int $secs at @waitForMutex
         * @param int $tries at @waitForMutex
         * @returns bool true on success and false on failure;
         */
        function deleteMutex($params = []){
            //Set defaults
            if(!isset($params['sec']))
                $sec = 2;
            else
                $sec = $params['sec'];

            if(!isset($params['tries']))
                $tries = 100;
            else
                $tries = $params['tries'];

            if(!isset($params['urlSuffix']))
                $urlSuffix = '';
            else
                $urlSuffix = $params['urlSuffix'];

            $sTime = (int)($sec*1000000/$tries);

            for($i=0;$i<$tries;$i++){
                try{
                    @unlink($this->lockUrl.$urlSuffix.'_mutex');
                    return true;
                }
                catch(\Exception $e){
                    usleep($sTime);
                }
            }

            return false;
        }

        /** Waits up to $secs seconds, doing $tries checks over them. If $destroy is true, destroys the mutex at the end of the wait.
         * For example, waitForMutex(3,30) would do 30 checks over 3 seconds, aka a check every 0.1 second.
         * Unless $ignore isn't a number (or is set to be 0) Will ignore and try to delete the mutex if it's over $ignore seconds old and return true,
         * because that'd mean there is a problem with the code somewhere else, and it's holding a mutex too long.
         *
         * @param string $urlSuffix Added at the end of the URL - can be used to extend it.
         * @param int $secs Waits up to $secs seconds
         * @param int $tries Does $tries checks over $secs
         * @param bool $destroy if $destroy is true, destroys the mutex at the end of the wait.
         * @param int $ignore $ignore isn't a number (or is set to be 0) Will ignore and try to delete the mutex if it's over $ignore seconds old
         *
         * @returns bool true on success and false on failure;
         */
        function waitForMutex($params = [], &$mutexExisted = null){

            //Set defaults
            if(!isset($params['urlSuffix']))
                $urlSuffix = '';
            else
                $urlSuffix = $params['urlSuffix'];

            if(!isset($params['sec']))
                $sec = 2;
            else
                $sec = $params['sec'];

            if(!isset($params['tries']))
                $tries = 20;
            else
                $tries = $params['tries'];

            if(!isset($params['destroy']))
                $destroy = false;
            else
                $destroy = $params['destroy'];

            if(!isset($params['ignore']))
                $ignore = 10;
            else
                $ignore = $params['ignore'];

            if($sec < 0)
                $sec = 0;

            $sTime = (int)($sec*1000000/$tries);

            if($mutexExisted != null)
                $mutexExisted = false;

            for($i=0;$i<$tries;$i++){
                $myfile = @fopen($this->lockUrl.$urlSuffix.'_mutex', "r");

                if($myfile){
                    if($mutexExisted != null)
                        $mutexExisted = true;
                    //If the mutex is too old, delete it and return true;
                    $lastUpdate = fread($myfile,100);
                    fclose($myfile);
                    if($ignore != 0 && gettype($ignore) == 'integer' && (int)$lastUpdate < time()+10){
                        if($destroy)
                            $this->deleteMutex($params);
                        return true;
                    }
                    //Sleep like we should
                    usleep($sTime);
                }
                else {
                    if($destroy)
                        $this->deleteMutex($params);
                    return true;
                }
            }

            return false;
        }

        /**Tries to create a new Redis mutex
         * @param  string $key Shared Identifier of the resource to lock
         * @param  string $value Potential value to use as identifier for verifying lock.
         * @param  array $params of the form:
         *              'sec' => int, default 2 - How many seconds to hold they key for at most
         *              'maxWait' => int, default 4 - How many seconds to try until timeout
         *              'randomDelay' => int, default 100,000 - Up to how many MICROSECONDS to wait before checking - e.g 1,000,000 is 1 second.
         *              'tries' => int, default 10 - How many times to try to get the mutex until timeout
         * @return int|string
         *              -1 - could not got a mutex due to RedisHandler not set, or failure to connect to Redis
         *              0 - Got the mutex --IF $value was provided
         *              <number larger than 0> - How long, im milliseconds, an existing mutex still has left to live
         *              <32 character string> - value of locked identifier on success --IF $value was NOT provided
         */
        function makeRedisMutex($key, $value = null, $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Set defaults
            $sec = isset($params['sec']) ? (int)$params['sec'] : 2;
            $maxWait = isset($params['maxWait']) ? (int)$params['maxWait'] : 4;
            $randomDelay = isset($params['randomDelay']) ? (int)$params['randomDelay'] : 100000;
            $tries = isset($params['tries']) ? (int)$params['tries'] : 10;
            $sTime = (int)($maxWait*1000000/$tries);
            $valueWasProvided = !empty($value);
            if(!$valueWasProvided){
                try{
                    $bytes = random_bytes(16);
                }
                catch(\Exception $e){
                    return -1;
                }
                $value = bin2hex($bytes);
            }

            if($this->RedisHandler === null || !$this->RedisHandler->isInit)
                return -1;

            //Sleep for a bit
            usleep(rand(0,$randomDelay));

            //Whether we got the lock
            $gotLock = false;

            for($i = 0; $i<$tries; $i++){
                if($gotLock)
                    break;

                //Try to lock the key IF IT DOESNT EXIST
                if(!$test){
                    $result = $this->RedisHandler->call('set',[$key, $value,['nx', 'ex'=>$sec]]);
                    if(!$result)
                        continue;
                }
                if($verbose && ($i==0 || $i===$tries-1))
                    echo 'Setting '.$key.' to '.$value.EOL;

                //See if we got the mutex
                $result = $this->RedisHandler->call('get',[$key]);
                if($verbose && ($i==0 || $i===$tries-1))
                    echo 'Got '.$result.EOL;

                if(!$test && ($result === false || $result !== $value) ){
                    if($i < $tries-1)
                        usleep($sTime);
                }
                else
                    $gotLock = true;
            }
            if(!$gotLock){
                $ttlMS = $this->RedisHandler->call('pttl',$key);
                return $ttlMS > 0 ? $ttlMS : 1;
            }
            else{
                return $valueWasProvided ? 0 : $value;
            }
        }

        /** Releases redis mutex
         * @param  string $key Shared Identifier of the resource to lock
         * @param  string $value Potential value to check - if not provided, wont check the value
         * @param  array $params of the form:
         * @return int|string
         *              -1 - could not got a mutex due to RedisHandler not set, or failure to connect to Redis
         *              0 - Released the mutex
         *              1 - could not release a mutex due to $value not matching current value --IF $value was NOT provided
         */
        function releaseRedisMutex($key, $value = null, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if($this->RedisHandler === null || !$this->RedisHandler->isInit)
                return -1;

            $result = 0;

            $valueWasProvided = !empty($value);
            if($valueWasProvided){
                $result = $this->RedisHandler->call('get',$key);
                if($verbose)
                    echo 'Got result '.$result.EOL;
                if($result && $result!==$value)
                    return 1;
                elseif($result === false)
                    return -1;
            }

            if(!$test)
                $result = $this->RedisHandler->call('del',$key);
            if($verbose)
                echo 'Deleting from cache - '.$key.EOL;

            if($result > 0)
                return 0;
            else
                return -1;
        }
    }

}