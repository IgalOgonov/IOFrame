<?php
/*
 * Will remove given string $strRem from any file spacified in the $url parameter, and replace it
 * $strRep.
 * */
if(!defined('helperFunctions'))
    require __DIR__ . '/../IOFrame/util/helperFunctions.php';

//Replace $strRem with $strRep in the file at $url (absolute path)
function cleanseFile($url,$strRemove,$strRep, array $params = []){

    $test = isset($params['test'])? $params['test'] : false;
    $verbose = isset($params['verbose'])?
        $params['verbose'] : $test ?
            true : false;

    //Default forbidden and required file regex
    $forbidden = ['siteNameCleanse\.php','\/localFiles','\/Handlers\/ext'];
    $required = [];

    $forbidden = isset($params['forbidden'])?
        array_merge($forbidden,$params['forbidden']) : $forbidden;
    $required = isset($params['required'])?
        array_merge($required,$params['required']) : $required;

    if(filesize($url) == 0)
        return;

    if(count($forbidden)>0)
        foreach($forbidden as $forbiddenFileRegex){
            if(preg_match('/'.$forbiddenFileRegex.'/',$url))
                return;
        }

    if(count($required)>0)
        foreach($required as $requiredFileRegex){
            if(!preg_match('/'.$requiredFileRegex.'/',$url))
                return;
        }


    $myfile = @fopen($url, "r+");

    if(!$myfile) {
        if($verbose)
            echo 'Couldn\'t open '.$url.EOL;
        return;
    }

    $temp=fread($myfile,filesize($url));

    if($verbose){
        if(strrpos($temp,$strRemove) !== false)
            echo 'Changed '.$strRemove.' to '.$strRep.' in file '.$url.'<br/>';
    }

    if(!$test){
        $temp = IOFrame\Util\replaceInString($strRemove,$strRep,$temp);
    //TODO To check for concurrency later
    sleep(0.1);

    $myfile = fopen($url, "w") or die("Unable to open file!");
    fwrite($myfile,$temp);
    }

}

function cleanseFolder($url,$strRemove,$strRep, array $params = []){
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

/*
cleanseFolder(
    substr(__DIR__,0,-10).'/api',
    'exit(\'-3\')',
    'exit(WRONG_CSRF_TOKEN)',
    [
        'test'=>true,
        'verbose'=>true,
        'subFolders'=>true,
        'forbidden'=>[],
        'required'=>[],
    ]
)*/

?>
