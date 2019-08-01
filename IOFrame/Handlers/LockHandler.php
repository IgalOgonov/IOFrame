<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('LockHandler',true);

    /**Handles local cross platform concurrency in IOFrame
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


        /**
         * Basic construction function
         * @param string $str Full fileapth of the locked FOLDER.
         * @param string $type type of lock
         */
        function __construct(string $str, string $type = 'mutex'){
            $this->lockUrl = $str;
            $this->type = $type;
        }


        /** Will try to create a mutex over $secs seocnds in $tries attempts, return true if succeeded else return false.
         * It will only fail if the resource is being used by something else all this time.
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

            if($this->waitForMutex(['sec'=>$sec,'tries'=>$tries])){
                @$myfile = fopen($this->lockUrl.'_mutex', "w");
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

            $sTime = (int)($sec*1000000/$tries);

            for($i=0;$i<$tries;$i++){
                try{
                    @unlink($this->lockUrl.'_mutex');
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
         * @param int $secs Waits up to $secs seconds
         * @param int $tries Does $tries checks over $secs
         * @param bool $destroy if $destroy is true, destroys the mutex at the end of the wait.
         * @param int $ignore $ignore isn't a number (or is set to be 0) Will ignore and try to delete the mutex if it's over $ignore seconds old
         *
         * @returns bool true on success and false on failure;
         */
        function waitForMutex($params = [], &$mutexExisted = null){

            //Set defaults
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
                $myfile = @fopen($this->lockUrl.'_mutex', "r");

                if($myfile){
                    if($mutexExisted != null)
                        $mutexExisted = true;
                    //If the mutex is too old, delete it and return true;
                    $lastUpdate = fread($myfile,100);
                    fclose($myfile);
                    if($ignore != 0 && gettype($ignore) == 'integer' && (int)$lastUpdate < time()+10){
                        if($destroy)
                            $this->deleteMutex();
                        return true;
                    }
                    //Sleep like we should
                    usleep($sTime);
                }
                else {
                    if($destroy)
                        $this->deleteMutex();
                    return true;
                }
            }

            return false;
        }

    }

}