<?php
namespace IOFrame\Managers{

    define('IOFrameManagersLockManager',true);

    /**Handles local concurrency in IOFrame.
     * Was written in a time when using native mutex implementations was via PHP was "dangerous" on some linux distros.
     * however, the following comment on php.net/flock also justifies using it:
     *
     * When a file is closed the lock will be released by the system anyway, even if PHP
     * doesn't do it explicitly anymore since 5.3.2 (the documentation is very confusing about this).
     * However, I had a situation on an apache/PHP server where an out-of-memory error in PHP caused file handles
     * to not be closed and therefore the locks where kept even thought PHP execution had ended and the process had
     * returned to apache to serve other requests. The lock was kept alive until apache recycled those processes.
     *
     * This lack of proper clean up basically makes flock() completely unreliable.
    * @author Igal Ogonov <igal1333@hotmail.com>
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */
    class LockManager{

        /** @var string Where you want the lock you're watching to reside
         * */
        private string $lockUrl;


        /**
         * Basic construction function
         * @param string $str Full filepath of the locked FOLDER.
         */
        function __construct(string $str){
            $this->lockUrl = $str;
        }


        /** Will try to create a mutex over $secs seconds in $tries attempts, return true if succeeded else return false.
         * It will only fail if the resource is being used by something else all this time.
         * @param array $params passed to waitForMutex
         * @returns bool true on success and false on failure;
         */
        function makeMutex(array $params = []): bool {
            //Set defaults

            if(!isset($params['urlSuffix']))
                $urlSuffix = '';
            else
                $urlSuffix = $params['urlSuffix'];

            if($this->waitForMutex($params)){
                @$myfile = fopen($this->lockUrl.$urlSuffix.'_mutex', "w");
                if($myfile){
                    fwrite($myfile,time());
                    fclose($myfile);
                    return true;
                }
            }

            return false;
        }

        /** Deletes a mutex from the folder
         * It will only fail if the resource is being used by something else all this time.
         * @param array $params
         * @return bool
         */
        function deleteMutex($params = []): bool {
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
                catch(\Exception){
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
         * @param array $params
         *              urlSuffix - string, default '' - Added at the end of the URL - can be used to extend it.
         *              sec - int, default 2 - Waits up to $secs seconds
         *              tries - int, default 20 - Does $tries checks over $sec
         *              destroy - bool, default false - if $destroy is true, destroys the mutex at the end of the wait.
         *              ignore - int, default 10 - Will ignore and try to delete the mutex if it's over $ignore seconds old
         *
         * @returns bool true on success and false on failure;
         */
        function waitForMutex(array $params = [], &$mutexExisted = null): bool {

            //Set defaults
            $urlSuffix = $params['urlSuffix'] ?? '';
            $sec = $params['sec'] ?? 2;
            $tries = $params['tries'] ?? 20;
            $destroy = $params['destroy'] ?? false;
            $ignore = $params['ignore'] ?? 10;

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
                    if($ignore != 0 && gettype($ignore) == 'integer' && (int)$lastUpdate < time()+$ignore){
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
    }

}