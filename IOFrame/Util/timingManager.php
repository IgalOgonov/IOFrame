<?php

namespace IOFrame\Util{
    define('timingManager',true);
    /**A pretty simple timer. Allows tracking time passed, and waiting for specific intervals / until specific time passes.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class timingManager
    {
        /**
         * @var Int The time a timing manager (current session) gets started IN MICROSECONDS
         */
        protected $startTime = -1;

        /**
         * @var Int The target time given in the last command.
         */
        protected $targetTime;

        /**
         * @var Int The time a timing manager stops (last current session)
         */
        protected $endTime = -1;


        /** Empty for now
         */
        function __construct(){
            ini_set("precision", 16);
        }

        /**Starts the timer.
         * @returns float Time now
         */
        function start($reset = true){
            if($reset)
                $this->reset();
            $this->startTime = microtime(true);
            return $this->startTime;
        }

        /**Sets a stop for the timer, and returns how much time elapsed til this moment.
         * @returns float how much time elapsed since start time, in MICROSECONDS
         */
        function stop(){
            $this->endTime = microtime(true);
            return $this->endTime;
        }

        /** Resets the timer end time
         */
        function reset(){
            $this->endTime = -1;
            $this->startTime = -1;
        }

        /** Returns time elapsed since start time. If the timer was stopped, returns the time it was last stopped
         * @returns int how much time elapsed since start time, in MICROSECONDS
         */
        function timeElapsed($sinceStart = false){
            $currentTime = (!$sinceStart && $this->endTime!==-1) ? $this->endTime : microtime(true);
            $startTime = $this->startTime!==-1 ? $this->startTime : $currentTime;
            return (int)round(( $currentTime - $startTime ) * 1000000);
        }

        /** Waits until the specified time - in UNIX TIMESTAMP.
         *  If the time is an int rather than a float, will wait to the closes second.
         *
         * @param int|float $waitUtil
         */
        function waitUntil($waitUtil){
            $type = gettype($waitUtil);
            if($type === 'integer')
                $waitUtil = (float)$waitUtil;
            $timeToWait = $waitUtil-microtime(true);
            if($timeToWait>0)
                usleep((int)round($timeToWait*1000000));
        }

        /** Waits until a specific amount of time has elapsed
         *
         * @param int|float $timeElapsed If INT, will convert to float. In seconds.
         */
        function waitUntilTimeElapsed($timeElapsed){
            $type = gettype($timeElapsed);
            if($type === 'integer')
                $timeElapsed = (float)$timeElapsed;
            if($this->startTime!==-1 && $this->startTime + $timeElapsed > microtime(true)  )
                usleep((int)round(($this->startTime + $timeElapsed - microtime(true))*1000000));
        }

        /** Waits until a specific amount of time has elapsed, however, if you are passed that time,
         * will wait until the closest multiplication of that time has elapsed.
         * For example, if you called waitUntilIntervalElapsed(1) after 1.1 seconds, it will wait until 2.
         *
         * @param int|float $interval If INT, will convert to float. In seconds.
         */
        function waitUntilIntervalElapsed($interval){
            $type = gettype($interval);
            if($type === 'integer')
                $interval = (float)$interval;
            if($this->startTime!==-1){
                //If we passed the interval by now, wait the remainder to the closest interval
                if($this->startTime + $interval <= microtime(true)){
                    usleep((int)round($interval*1000000 - fmod ((microtime(true) - $this->startTime),$interval)*1000000));
                }
                //Else just wait until the interval ends.
                else{
                    usleep((int)round(($this->startTime + $interval - microtime(true))*1000000));
                }
            }
        }


    }

}

?>