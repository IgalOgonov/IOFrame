<?php
namespace IOFrame\Util{
    define('modificationFunctions',true);
    if(!defined('helperFunctions'))
        require __DIR__ . '/../../IOFrame/util/helperFunctions.php';

    require_once __DIR__ . '/../../main/definitions.php';



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

        $test = isset($params['test'])? $params['test'] : false;
        $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
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

        if(!$myfile) {
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
            cleanseFile($fileUrl,$strRemove,$strRep, $params);
        }

        if($subFolders){
            $folderUrls = [];
            foreach($dirArray as $key => $fileUrl){
                if(is_dir ($url.'/'.$fileUrl) && $fileUrl!='.git' && $fileUrl!='.idea')
                    array_push($folderUrls,$url.'/'.$fileUrl);
            }

            foreach($folderUrls as $folderUrl){
                cleanseFolder($folderUrl,$strRemove,$strRep, $params);
            }

        }


    }
}
