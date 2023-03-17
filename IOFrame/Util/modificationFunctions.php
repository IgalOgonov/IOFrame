<?php
namespace IOFrame\Util{
    define('modificationFunctions',true);
    if(!defined('helperFunctions'))
        require __DIR__ . '/../../IOFrame/util/helperFunctions.php';

    require_once __DIR__ . '/../../main/definitions.php';


    /**
    Refactor the name of the file or folder into camel case
     * @param string $url needs to be the absolute address of the file or folder to modify
     * @param array $params of the form:[
     *              'delay' => int, default 0.1 - delay in seconds between opening the file (laz concurrency fix).
     *          ]
     */
    function camelCase($url,array $params = []){

        $test = $params['test']?? false;
        $verbose = $params['verbose'] ?? $test;

        //TODO To check for concurrency later
        $urlArr = explode('/',$url);
        $name = str_split(array_pop($urlArr));
        $urlArr = join('/',$urlArr);
        $newName = '';
        for($i=count($name)-1; $i>=0; $i--){
            $char = $name[$i];
            if($i === 0)
                $char = strtolower($char);
            else{
                if($name[$i-1] !== ' ')
                    $char = strtolower($char);
            }
            if($char!==' ')
                $newName .= $char;
        }
        $newName = strrev($newName);
        if($verbose){
            echo $url.PHP_EOL;
            echo $urlArr.'/'.$newName.PHP_EOL;
            echo '----'.PHP_EOL;
        }
        if(!$test)
            rename($url,$urlArr.'/'.$newName);

    }


    /**
     * Refactor the names of all the folders/files in a specific folder, and optionally all its subfolders, to camelCase
     * @param string $url needs to be the absolute address of the folder to modify
     * @param array $params Same as camelCase(), with the addition of:
     *                  'subFolders' => bool, default false - whether to recursively modify all files in the subfolders too
     *
     * Example:
     * refactorCamelCase(
     *   'C:/wamp64/www/TestSite/test',
     *   [
     *     'test'=>true,
     *     'verbose'=>true,
     *     'subFolders'=>true,
     *   ]
     * )
     *
     *
     */
    function refactorCamelCase($url, array $params = []){
        isset($params['subFolders'])?
            $subFolders = $params['subFolders'] : $subFolders = false;
        $dirArray = scandir($url);
        $dirUrls = [];
        $fileUrls = [];
        foreach($dirArray as $key => $itemUrl){
            if($itemUrl=='.' || $itemUrl=='..')
                $dirArray[$key] = NULL;
            else
                if(is_dir ($url.'/'.$itemUrl)){
                    array_push($dirUrls,$url.'/'.$itemUrl);

                    if($subFolders){
                        refactorCamelCase($url.'/'.$itemUrl, $params);
                    }
                }
                else{
                    array_push($fileUrls,$url.'/'.$itemUrl);
                }
        }

        $allUrls = array_merge($dirUrls,$fileUrls);
        if(is_array($allUrls))
            foreach($allUrls as $itemUrl){
                if($itemUrl)
                    camelCase($itemUrl, $params);
            }

    }

    /**
    Replace a string with a different string in a single file
     * @param string $url needs to be the absolute address of the file to modify
     * @param string $strRemove String to replace
     * @param string $strRep String to replace $strRemove with
     * @param array $params of the form:[
     *              'forbidden' => Array of strings. Regex of the $url - meant to be used when this function is called en-masse by something else.
     *                             If the $url matches any patterns in this array, execution will stop.
     *              'required' => Same as 'forbidden', only that the $url MUST match one of the patterns provided (if there are any) for
     *                            execution to continue.
     *              'delay' => int, default 0.1 - delay in seconds between opening the file (laz concurrency fix).
     *
     *          ]
     */
    function replaceInFile($url,$strRemove,$strRep, array $params = []){

        $test = $params['test']?? false;
        $verbose = $params['verbose'] ?? $test;
        $delay = isset($params['delay'])? $params['delay'] : 0.1;

        //Default forbidden and required file regex
        $forbidden = ['modificationFunctions\.php'];
        $required = [];

        $forbidden = isset($params['forbidden'])?
            array_merge($forbidden,$params['forbidden']) : $forbidden;
        $required = isset($params['required'])?
            array_merge($required,$params['required']) : $required;

        if(filesize($url) == 0)
            return;

        if(count($forbidden)>0)
            foreach($forbidden as $forbiddenFileRegex){
                if(preg_match('/'.$forbiddenFileRegex.'/',$url)){
                    if($verbose)
                        echo 'Skipping file '.$url.' - forbidden.'.EOL;
                    return;
                }
            }

        if(count($required)>0)
            foreach($required as $requiredFileRegex){
                if(!preg_match('/'.$requiredFileRegex.'/',$url)){
                    if($verbose)
                        echo 'Skipping file '.$url.' - not one of the required files.'.EOL;
                    return;
                }
            }


        $myfile = @fopen($url, "r+");

        if(!$myfile || !@filesize($myfile)) {
            if($verbose)
                echo 'Couldn\'t open '.$url.EOL;
            return;
        }

        $temp=fread($myfile,filesize($url));

        if((strrpos($temp,$strRemove) === false)){
            if($verbose)
                echo 'Skipping file '.$url.' - nothing to change.'.EOL;
            return;
        }
        else{
            if($verbose)
                echo 'Changing '.$strRemove.' to '.$strRep.' in file '.$url.EOL;
        }

        $temp = replaceInString($strRemove,$strRep,$temp);

        if(!$test){
            //TODO To check for concurrency later
            sleep($delay);

            $myfile = fopen($url, "w") or die("Unable to open file ".$url);
            fwrite($myfile,$temp);
        }

    }

    /**
    * Replace a string with a different string in all the files in a specific folder, and optionally all its subfolders
     * @param string $url needs to be the absolute address of the folder to modify
     * @param string $strRemove String to replace
     * @param string $strRep String to replace $strRemove with
     * @param array $params Same as replaceInFile(), with the addition of:
     *                  'subFolders' => bool, default false - whether to recursively modify all files in
     *
     * Example:
     * replaceInFolder(
     *   'C:/wamp64/www/TestSite/test',
     *   '/../../IOFrame/Handlers/IOFrameTest1Handler.php',
     *   '/../IOFrame/Handlers/IOFrameTest2Handler.php',
     *   [
     *     'test'=>true,
     *     'verbose'=>true,
     *     'subFolders'=>true,
     *     'forbidden'=>[],
     *     'required'=>[],
     *   ]
     * )
     *
     *
     */
    function replaceInFolder($url,$strRemove,$strRep, array $params = []){
        isset($params['subFolders'])?
            $subFolders = $params['subFolders'] : $subFolders = false;
        $dirArray = scandir($url);
        $fileUrls = [];
        foreach($dirArray as $key => $fileUrl){
            if($fileUrl=='.' || $fileUrl=='..')
                $dirArray[$key] = 'NULL';
            else
                if(!is_dir ($url.'/'.$fileUrl))
                    array_push($fileUrls,$url.'/'.$fileUrl);
        }

        foreach($fileUrls as $fileUrl){
            replaceInFile($fileUrl,$strRemove,$strRep, $params);
        }

        if($subFolders){
            $folderUrls = [];
            foreach($dirArray as $key => $fileUrl){
                if(is_dir ($url.'/'.$fileUrl) && $fileUrl!='.git' && $fileUrl!='.idea')
                    array_push($folderUrls,$url.'/'.$fileUrl);
            }

            foreach($folderUrls as $folderUrl){
                replaceInFile($folderUrl,$strRemove,$strRep, $params);
            }

        }

    }
}
