<?php
/*
 * Will remove given string $strRem from any file spacified in the $url parameter, and replace it
 * $strRep.
 * */
require_once '_util/helperFunctions.php';

//Replace $strRem with $strRep in the file at $url (absolute path)
function cleanseFile($url,$strRemove,$strRep, $test = false,  $report = true){

    if(preg_match('/_siteNameCleanse\.php/',$url) ||
        preg_match('/\/settings/',$url) ||
        preg_match('/\/_install\.php/',$url) ||
        preg_match('/\/_siteHandlers\/ext/',$url) ||
        filesize($url) == 0
    )
        return;


    $myfile = @fopen($url, "r+");

    if(!$myfile) {
        echo 'Couldn\'t open '.$url.EOL;
        return;
    }

    $temp=fread($myfile,filesize($url));

    if($test || $report){
        if(strrpos($temp,$strRemove) !== false)
            echo 'Changed '.$strRemove.' to '.$strRep.' in file '.$url.'<br/>';
    }

    if(!$test){
        $temp = IOFrame\replaceInString($strRemove,$strRep,$temp);
    //TODO To check for concurrency later
    sleep(0.1);

    $myfile = fopen($url, "w") or die("Unable to open file!");
    fwrite($myfile,$temp);
    }

}

function cleanseFolder($url,$strRemove,$strRep,$subFolders = false, $test = false, $report = true){

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
        cleanseFile($fileUrl,$strRemove,$strRep, $test, $report);
    }

    if($subFolders){
        $folderUrls = [];

        foreach($dirArray as $key => $fileUrl){
                if(is_dir ($url.'/'.$fileUrl) && $fileUrl!='.git' && $fileUrl!='.idea')
                    array_push($folderUrls,$url.'/'.$fileUrl);
        }

        foreach($folderUrls as $folderUrl){
                cleanseFolder($folderUrl,$strRemove,$strRep, true, $test, $report);
        }

    }


}

//cleanseFolder(__DIR__,'secHandler','securityHandler',true,true)
?>
