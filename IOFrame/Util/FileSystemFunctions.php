<?php
namespace IOFrame\Util{

    define('IOFrameUtilFileSystemFunctions',true);

    class FileSystemFunctions{

        /**
         * Finds all files inside a folder (and/or sub-folders) that match specific patterns, returns them as a flat array
         * @param string $folder folder in which to include/require files
         * @param array $params of the form:
         *                    'subFolders'=> bool, default false - whether to include subfolders in search
         *                    'returnFolders'=> bool, default false - whether to include folders in results
         *                    'exclude'=> string[], default [] - excludes file that match any of the regexes
         *                    'include'=> string[], default [] - only include file that match all regexes
         *                    'excludeFolders'=> string[], default [] - excludes dir that match any of the regexes
         *                    'includeFolders'=> string[], default [] - only include dir that match all regexes
         * @return array
         */
        public static function fetchAllFolderAddresses(string $folder, array $params = []): array {

            if(!is_dir($folder))
                return [];

            $subFolders = $params['subFolders'] ?? false;
            $returnFolders = $params['returnFolders'] ?? false;
            $exclude = $params['exclude'] ?? [];
            $include = $params['include'] ?? [];
            $excludeFolders = $params['excludeFolders'] ?? [];
            $includeFolders = $params['includeFolders'] ?? [];

            $dirArray = scandir($folder);
            $folderUrls = [];
            $fileUrls = [];

            foreach($dirArray as $url){
                if(($url=='.') || ($url=='..'))
                    continue;

                $toInclude = true;

                $isDir = is_dir($folder.'/'.$url);

                if($isDir){
                    $exChecks = $excludeFolders;
                    $inChecks = $includeFolders;
                }
                else{
                    $exChecks = $exclude;
                    $inChecks = $include;
                }

                foreach($exChecks as $val)
                    if(preg_match('/'.$val.'/',$folder.'/'.$url))
                        $toInclude = false;

                if(!empty($inChecks)){
                    $toInclude = false;
                    foreach($inChecks as $val)
                        if(preg_match('/'.$val.'/',$folder.'/'.$url))
                            $toInclude = true;
                }

                if($toInclude){
                    if($isDir)
                        $folderUrls[] = $folder . '/' . $url;
                    else
                        $fileUrls[] = $folder . '/' . $url;
                }
            }

            if($subFolders)
                foreach ($folderUrls as $url){
                    //Even if we get folders too, doesn't matter, since both arrays get merged in the end
                    $fileUrls = array_merge($fileUrls,self::fetchAllFolderAddresses($url,$params));
                }

            return $returnFolders ? array_merge($folderUrls,$fileUrls) : $fileUrls;
        }

        //Recursive folder copying in PHP - simple function
        public static function folder_copy($src,$dst, array $exclude = []): bool {
            $res = true;
            $dir = opendir($src);
            @mkdir($dst);
            while(false !== ( $file = readdir($dir)) ) {
                if (( $file == '.' ) || ( $file == '..' ))
                    continue;

                foreach ($exclude as $excludeRegex)
                    if(preg_match('/'.$excludeRegex.'/',$src . '/' . $file))
                        continue;

                if ( is_dir($src . '/' . $file) ) {
                    $res = $res && self::folder_copy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
                    $res = $res && copy($src . '/' . $file,$dst . '/' . $file);
                }

            }
            closedir($dir);
            return $res;
        }

        //Recursive folder deletion in PHP - simple function
        public static function folder_delete($dirPath): bool {
            if (! is_dir($dirPath)) {
                throw new \InvalidArgumentException("$dirPath must be a directory");
            }
            if (!str_ends_with($dirPath, '/')) {
                $dirPath .= '/';
            }
            $files = glob($dirPath . '*', GLOB_MARK);
            foreach ($files as $file) {
                if (is_dir($file)) {
                    self::folder_delete($file);
                } else {
                    unlink($file);
                }
            }
            return rmdir($dirPath);
        }

        //file_put_contents, but creates directories in path if they don't exist
        public static function file_force_contents( $fullPath, $contents, $flags = 0 ): bool|int {
            $parts = explode( '/', $fullPath );
            array_pop( $parts );
            $dir = implode( '/', $parts );

            if( !is_dir( $dir ) )
                @mkdir( $dir, 0777, true );
            return ( ($contents === null) || ($contents === '') ) ?
                touch($fullPath) :
                file_put_contents( $fullPath, $contents, $flags )
                ;
        }

        //rename, but creates directories in path if they don't exist
        public static function force_rename($oldname, $newname, $context = null): bool {

            $parts = explode( '/', $newname );
            array_pop( $parts );
            $dir = implode( '/', $parts );
            if( !is_dir( $dir ) )
                mkdir( $dir, 0777, true );

            if($context !== null)
                return rename( $oldname, $newname, $context );
            else
                return rename( $oldname, $newname );
        }


        /** Reads a file $fileName at url $url without waiting for mutex
         * @param string $url Url of specified file
         * @param string $fileName  Name of specified file
         * @param array $params
         * @throws \Exception If lock file can't be opened, mutex was locked over the wait specified duration.
         *
         * @returns string
         *  the file contents,
         *  or throws an exception.
         * */
        public static function readFile(string $url, string $fileName = '', array $params = []): bool|string {

            //Set defaults
            $verbose = $params['verbose'] ?? ($params['test'] ?? false);

            try{
                $myFile = @fopen($url.$fileName,"r");
                if(!@filesize($url.$fileName))
                    return '';
                if(!$myFile)
                    throw new \Exception("Cannot open file ".$url.$fileName);
                $fileContents = fread($myFile,filesize($url.$fileName));
                fclose($myFile);
                return $fileContents;
            }
            catch(\Exception $e){
                if($verbose)
                    echo 'Exception when reading file -> '.$e->getMessage();
                return false;
            }
        }

        /** Reads a file $fileName at url $url after waiting $sec seconds for a mutex.
         * @param string $url Url of specified file
         * @param string $fileName  Name of specified file
         * @param array $params of the form:
         *              'useNative' - bool, default false - Whether to use native PHP lock that is faster, but may not
         *                             work across some platforms
         *              'destroyMutex' - bool, default false - Whether to destroy native / LockHandler mutex at the end
         *              'usleepNative' - int, default 100000 - microseconds to wait before acquiring native lock after first try.
         *              'sec' - int, default 2 - seconds to wait for lock @LockManager
         *              'LockManager' - LockManager, default null - Use an existing LockManager - do not waste resources
         *                              when it's not needed. If null, will create a new one.
         *
         * @throws \Exception If lock file can't be opened, mutex was locked over the wait specified duration.
         *
         * @returns bool|string|array
         *              false if file does not exist
         *              string file contents if not using native mutex, or using native with destroyMutex being true
         *              array [
         *                  'contents': string, file contents
         *                  'fileStream': stream, A file system pointer resource that is typically created using fopen().
         *              ] if not using native mutex with destroyMutex false
         *  the file contents,
         *  or throws an exception.
         * */
        public static function readFileWaitMutex(string $url, string $fileName, array $params = []): bool|array|string {

            //Set defaults
            $verbose = $params['verbose'] ?? ($params['test'] ?? false);
            $useNative = $params['useNative'] ?? false;
            $destroyMutex = $params['destroyMutex'] ?? false;
            $usleepNative = $params['usleepNative'] ?? 10000;
            $sec = $params['sec'] ?? 2;
            $LockManager = $params['LockManager'] ?? null;

            if(!str_ends_with($url, '/'))
                $url .= '/';
            if(!is_file($url.$fileName))
                return false;

            if(!$useNative){
                if($LockManager === null)
                    $LockManager = new \IOFrame\Managers\LockManager($url);

                if($LockManager->waitForMutex(['sec'=>$sec,'destroy'=>$destroyMutex])){
                    try{
                        $myFile = @fopen($url.$fileName,"r");
                        if(!@filesize($url.$fileName))
                            return '';
                        if(!$myFile)
                            throw new \Exception("Cannot open file ".$url.$fileName);
                        $fileContents = fread($myFile,filesize($url.$fileName));
                        fclose($myFile);
                        return $fileContents;
                    }
                    catch(\Exception $e){
                        if($verbose)
                            echo 'Exception when reading file '.$url.$fileName.' -> '.$e->getMessage();
                        return false;
                    }
                }
                else
                    throw new \Exception("Mutex locked for file ".$fileName);
            }
            else{
                try{
                    $myFile = fopen($url.$fileName, 'r');
                    if(!$myFile)
                        throw new \Exception("Cannot open file");
                }
                catch(\Exception $e){
                    if($verbose)
                        echo 'Exception when reading file '.$url.$fileName.' -> '.$e->getMessage();
                    return false;
                }
                $acquiredLock = flock($myFile, LOCK_EX);
                $startTime = time();
                while (!$acquiredLock && ($startTime+$sec > time())){
                    usleep($usleepNative);
                    $acquiredLock = flock($myFile, LOCK_EX);
                }
                if(!$acquiredLock)
                    throw new \Exception("Mutex locked for file ".$fileName);
                else {
                    if(!@filesize($url.$fileName))
                        $fileContents = '';
                    else
                        $fileContents = fread($myFile,filesize($url.$fileName));
                    if($destroyMutex){
                        fclose($myFile);
                        return $fileContents;
                    }
                    else{
                        return [
                            'contents'=>$fileContents,
                            'fileStream'=>$myFile
                        ];
                    }
                }
            }
        }

        /**
         * Writes a file $fileName to url $url after waiting $sec seconds for a mutex.
         *
         * @param string $url Url of specified file
         * @param string $fileName  Name of specified file
         * @param string $content content to write into the file
         * @param array $params of the form:
         *              'sec' - int, default 2 - seconds to wait for lock @LockManager
         *              'createNew' - bool, default true - Whether you want to allow creating new files, or force to check for existing ones.
         *              'createFolders' - bool, default false - Will attempt to create folders when a file does not exist.
         *              'override' - bool, default true - Whether you want to override existing files.
         *              'append' - bool, default false - Whether you want to append to the file's end, or rewrite it.
         *              'backUp' - bool, default false - set to true if you wish to back the file up with default $maxBackup
         *              'useNative' - bool, default false - Whether to use native PHP lock that is faster, but may not
         *                            work across some platforms
         *              'LockManager' - LockManager, default null - Use an existing LockManager - do not waste resources
         *                              when it's not needed. If null, will create a new one.
         *
         * @throws \Exception Generally if either lock file can't be opened, mutex was locked over the wait specified duration.
         *
         * @returns bool
         *      true on success, false on failure.
         * */
        public static function writeFileWaitMutex(string $url, string $fileName, string $content, array $params = []): bool {
            //Set defaults
            $verbose = $params['verbose'] ?? ($params['test'] ?? false);
            $sec = $params['sec'] ?? 2;
            $tries = $params['tries'] ?? 20;
            $append = $params['append'] ?? false;
            $createFolders = $params['createFolders'] ?? $append;
            $createNew = $params['createNew'] ?? true;
            $override = $params['override'] ?? true;
            $backUp = $params['backUp'] ?? false;
            $useNative = $params['useNative'] ?? false;
            $LockManager = $params['LockManager'] ?? null;

            $fileExists = is_file($url.$fileName);

            if(!str_ends_with($url, '/'))
                $url .= '/';

            if(!$createNew && !$fileExists){
                if($verbose)
                    echo $url.$fileName.' is not a file!'.EOL;
                return false;
            }

            if(!$override && $fileExists){
                if($verbose)
                    echo $url.$fileName.' already exists!'.EOL;
                return false;
            }

            if(($createNew || $createFolders) && !$fileExists){
                $couldCreate = false;
                $folders = explode('/',$url.$fileName);
                //Remove the file itself
                array_pop($folders);
                $newUrl = implode('/',$folders);
                $folderExists = is_dir($newUrl);

                if(!$folderExists && !$createFolders)
                    return false;

                elseif(!$folderExists && $createFolders){
                    $folderExists = mkdir($newUrl,0777,true);
                }

                if($folderExists)
                    $couldCreate = touch($url.$fileName);

                if(!$couldCreate)
                    return false;
            }

            //Native lock implementation
            if($useNative){
                ($append)?
                    $mode = 'a' : $mode = 'r+';
                try{
                    $myFile = fopen($url.$fileName, $mode);
                    if(!$myFile)
                        throw new \Exception("Cannot open file");
                }
                catch(\Exception $e){
                    if($verbose)
                        echo 'Exception when reading file '.$url.$fileName.' -> '.$e->getMessage();
                    return false;
                }
                $acquiredLock = flock($myFile, LOCK_EX);
                $startTime = time();
                while (!$acquiredLock && ($startTime+$sec > time())){
                    usleep($sec*1000000 / $tries);
                    $acquiredLock = flock($myFile, LOCK_EX);
                }
                if(!$acquiredLock)
                    throw new \Exception("Couldn't get the lock on ".$url.$fileName);
                else{  // acquire an exclusive lock
                    if($append){
                        fwrite($myFile,$content);
                    }
                    else{
                        ftruncate($myFile, 0);      // truncate file
                        fwrite($myFile, $content);
                        fflush($myFile);            // flush output before releasing the lock
                    }
                }
                flock($myFile, LOCK_UN);    // release the lock
                fclose($myFile);
            }

            //Original implementation
            else try{
                if($LockManager === null)
                    $LockManager = new \IOFrame\Managers\LockManager($url);

                if($LockManager->waitForMutex(['sec'=>$sec])){
                    $LockManager->makeMutex();
                    ($append)?
                        $mode = 'a' : $mode = 'w+';
                    if($backUp)
                        self::backupFile($url, $fileName);
                    try{
                        $myFile = fopen($url.$fileName, $mode);
                    }
                    catch (\Exception $e){
                        $LockManager->deleteMutex();
                        throw new \Exception($e);
                    }
                    if(!$myFile){
                        $LockManager->deleteMutex();
                        throw new \Exception("Cannot open file ".$url.$fileName);
                    }
                    fwrite($myFile,$content);
                    fclose($myFile);
                    $LockManager->deleteMutex();
                }
                else
                    throw new \Exception("Mutex locked for file ".$fileName);
            }
            catch(\Exception $e){
                if($verbose)
                    echo 'Exception when writing to file -> '.$e->getMessage().EOL;
                return false;
            }
            return true;
        }

        /** Creates a backup of a file with the name $filename at $url,
         * with the number of the backup in that folder and a 'backup' file extension.
         *
         * @param string $url Url of specified file
         * @param string $filename
         * @param array $params of the forms:
         *              'maxBackup' - int, default 10 -  Deletes the $maxBackup-th backup, if the limit exists
         */
        public static function backupFile(string $url, string $filename, array $params = []): void {

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

        /** Creates an empty file if one does not exist.
         * The parameter priority is 'fileContentOnCreation' > 'copyExistingFile'.
         *
         * @param string $url Url of specified folder
         * @param array $params
         *               'fileContentOnCreation' - string, default null - If set, will initiate the file with this content
         *               'copyExistingFile' - string, default null - If set, will search for an existing file at this url, and copy it instead of creating an empty file.
         *               'overwrite' - bool, default null - If true, must overwrite existing items. If false, mustn't overwrite exising items. If null (unset), either outcome is ok.
         * @returns int
         *      -1 - failed to create / write to new file or folder
         *       0 - success
         *       1 - file already exists and overwrite is false
         *       2 - file does not exist and overwrite is true
         *       3 - 'copyExistingFile' is passed, but not a valid file
         */
        public static function createOrPopulateFile(string $url, array $params = []): int {

            $verbose = $params['verbose'] ?? ($params['test'] ?? false);
            $fileContentOnCreation = $params['fileContentOnCreation'] ?? null;
            $copyExistingFile = $params['copyExistingFile'] ?? null;
            $overwrite = $params['overwrite'] ?? null;

            //Content to write
            $contentToWrite = '';
            if($fileContentOnCreation)
                $contentToWrite = $fileContentOnCreation;
            elseif($copyExistingFile){
                try {
                    $contentToWrite = self::readFile($copyExistingFile,'',$params);
                }
                catch(\Exception $e){
                    if($verbose)
                        echo 'Could not read existing file, error '.$e->getMessage().EOL;
                    return 3;
                }
            }

            //Check whether the file exists and overwrite
            $targetExists = file_exists($url);
            if($targetExists && ($overwrite === false))
                return 1;
            elseif(!$targetExists && ($overwrite === true))
                return 2;

            //Write to file if we have anything to write
            if(!$targetExists || ($targetExists && $overwrite)){
                try {
                    return self::file_force_contents($url,$contentToWrite) ? 0 : -1;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo 'Could not create file or folder file, error '.$e->getMessage().EOL;
                    return -1;
                }
            }

            return 0;
        }

    }

}