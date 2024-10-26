<?php
/*This example update will expand test's include file to initiate another session variable -
  one that contains the current version*/
$existingInclude = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($url,'include.php');
if(!$existingInclude)
    throw new \Exception("Failed to read existing include!");
if($verbose)
    echo 'Existing include is '.htmlspecialchars($existingInclude).', Cur version: '.$currentVersion.', Target version: '.$targetVersion.EOL;

//Based on the version, we decide on whether adding new initiation commands or modifying old ones.
if($currentVersion === 1){
    $existingInclude = str_replace('?>','$_SESSION[\'currentVersion\'] = '.$targetVersion.';'.EOL_FILE.'?>',$existingInclude);
}
else{
    $existingInclude = str_replace('$_SESSION[\'currentVersion\'] = '.$currentVersion,'$_SESSION[\'currentVersion\'] = '.$targetVersion,$existingInclude);
}

//Write overwrite the old include, if not testing.
if($verbose)
    echo 'New include is '.htmlspecialchars($existingInclude).EOL;

if(!$test)
    if(!\IOFrame\Util\FileSystemFunctions::writeFileWaitMutex($url,'include.php',$existingInclude,$params))
        throw new \Exception("Failed to write new include!");
