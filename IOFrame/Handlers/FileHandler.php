<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('FileHandler',true);
    if(!defined('helperFunctions'))
        require __DIR__ . '/../Util/helperFunctions.php';
    if(!defined('LockHandler'))
        require 'LockHandler.php';

    /**Handles local file operations in IOFrame
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */
    class FileHandler
    {
        /**Literally nothing to construct.
        */
        function __construct()
        {
        }

        /** Reads a file $fileName at url $url after waiting $sec seconds for a mutex.
         * @param string $url Url of specified file
         * @param string $fileName  Name of specified file
         * @param array $params of the form:
         *              'sec' - int, default 2 - seconds to wait for lock @LockHandler
         *              'LockHandler' - LockHandler, default null - Use an existing LockHandler - do not waste resources
         *                              when it's not needed. If null, will create a new one.
         *
         * @throws \Exception If lock file can't be opened, mutex was locked over the wait specified duration.
         *
         * @returns string
         *  the file contents,
         *  or throws an exception.
         * */
        function readFileWaitMutex(string $url, string $fileName, $params = []){

            //Set defaults
            if(!isset($params['sec']))
                $sec = 2;
            else
                $sec = $params['sec'];

            if(!isset($params['LockHandler']))
                $LockHandler = null;
            else
                $LockHandler = $params['LockHandler'];

            if(substr($url,-1) != '/')
                $url .= '/';
            if(!is_file($url.$fileName))
                return false;
            if($LockHandler === null)
                $LockHandler = new LockHandler($url);
            if($LockHandler->waitForMutex(['sec'=>$sec])){
                try{
                    $myFile = @fopen($url.$fileName,"r");
                    if(filesize($url.$fileName) == 0)
                        return '';
                    if(!$myFile)
                        throw new \Exception("Cannot open file ".$url.$fileName);
                    $fileContents = fread($myFile,filesize($url.$fileName));
                    fclose($myFile);
                    return $fileContents;
                }
                catch(\Exception $e){
                    return false;
                }
            }
            else
                throw new \Exception("Mutex locked for file ".$fileName);
        }

        /**
         * Writes a file $fileName to url $url after waiting $sec seconds for a mutex.
         *
         * @param string $url Url of specified file
         * @param string $fileName  Name of specified file
         * @param string $content content to write into the file
         * @param array $params of the form:
         *              'sec' - int, default 2 - seconds to wait for lock @LockHandler
         *              'append' - bool, default false - Whether you want to append to the file's end, or rewrite it.
         *              'backUp' - bool, default false - set to true if you wish to back the file up with default $maxBackup
         *              'useNative' - bool, default false - Whether to use native PHP lock that is faster, but may not
         *                            work across some platforms
         *              'LockHandler' - LockHandler, default null - Use an existing LockHandler - do not waste resources
         *                              when it's not needed. If null, will create a new one.
         *
         * @throws \Exception Generally if either lock file can't be opened, mutex was locked over the wait specified duration.
         *
         * @returns bool
         *      true on success, false on failure.
         * */
        function writeFileWaitMutex(string $url, string $fileName, string $content, $params = []){
            //Set defaults
            if(!isset($params['sec']))
                $sec = 2;
            else
                $sec = $params['sec'];

            if(!isset($params['append']))
                $append = false;
            else
                $append = $params['append'];

            if(!isset($params['backUp']))
                $backUp = false;
            else
                $backUp = $params['backUp'];

            if(!isset($params['useNative']))
                $useNative = false;
            else
                $useNative = $params['useNative'];

            if(!isset($params['LockHandler']))
                $LockHandler = null;
            else
                $LockHandler = $params['LockHandler'];


            if(substr($url,-1) != '/')
                $url .= '/';
            if(!is_file($url.$fileName))
                return false;
            //Native lock implementation
            if($useNative){
                ($append)?
                    $mode = 'a' : $mode = 'r+';
                $myFile = fopen($url.$fileName, $mode);
                if (flock($myFile, LOCK_EX)) {  // acquire an exclusive lock
                    if($append){
                        fwrite($myFile,$content);
                    }
                    else{
                        ftruncate($myFile, 0);      // truncate file
                        fwrite($myFile, $content);
                        fflush($myFile);            // flush output before releasing the lock
                    }
                } else {
                    throw new \Exception("Couldn't get the lock on ".$url.$fileName);
                }
                flock($myFile, LOCK_UN);    // release the lock
                fclose($myFile);
            }

            //Original implementation
            else try{
                if($LockHandler === null)
                    $LockHandler = new LockHandler($url);

                if($LockHandler->waitForMutex(['sec'=>$sec])){
                    $LockHandler->makeMutex();
                    ($append)?
                        $mode = 'a' : $mode = 'w+';
                    if($backUp)
                        $this->backupFile($url, $fileName);
                    try{
                        $myFile = fopen($url.$fileName, $mode);
                    }
                    catch (\Exception $e){
                        $LockHandler->deleteMutex();
                        throw new \Exception($e);
                    }
                    if(!$myFile){
                        $LockHandler->deleteMutex();
                        throw new \Exception("Cannot open file ".$url.$fileName);
                    }
                    fwrite($myFile,$content);
                    fclose($myFile);
                    $LockHandler->deleteMutex();
                }
                else
                    throw new \Exception("Mutex locked for file ".$fileName);
            }
            catch(\Exception $e){
                return false;
            }
            return true;
        }

        /** Creates a backup of a file with the name $filename at $url,
         * with the number of the backup in that folder and a 'backup' file extension.
         *
         * @param string $url Url of specified file
         * @param string $fileName  Name of specified file
         * @param array $params of the forms:
         *              'maxBackup' - int, default 10 -  Deletes the $maxBackup-th backup, if the limit exists
         * */
        function backupFile(string $url, string $filename, $params = []){

            //Set defaults
            if(!isset($params['maxBackup']))
                $maxBackup = 10;
            else
                $maxBackup = $params['maxBackup'];

            for($i=$maxBackup; $i>0;$i--){
                if(is_file($url.$filename.'.backup'.$i)){
                    if($i==$maxBackup){
                        unlink($url.$filename.'.backup'.$i);
                    }
                    else{
                        rename($url.$filename.'.backup'.$i, $url.$filename.'.backup'.($i+1));
                    }
                }
            }
            copy($url.$filename, $url.$filename.'.backup1');
        }




    }
}
?>