<?php
/* A simple watcher that watches files, and updates the destination files when the original ones change.
 * Requires you create a json file, which constitutes an array of objects of the form:
 * [
 *  {
 *      "target":<string, target file to watch RELATIVE to -r (Defaults to IOFrame root)>
 *      "destination":<string, target file to update on target changes RELATIVE to -r (Defaults to IOFrame root)>
 *      ["depth":<int, Depth when watching folders. Defaults to 1 (immediate children). Increase may lead to exponential load increase.>]
 *  }
 * ]
 *
 * example:
 *  php simpleWatcher.php -f watcher-json/test.json
 *
 * */
if(php_sapi_name() != "cli"){
    die('This file must be accessed through the CLI!');
}

require 'defaultInclude.php';

//--------------------Initialize Root DIR--------------------
$baseUrl = $settings->getSetting('absPathToRoot');

//--------------------Initialize EOL --------------------
if(!defined("EOL"))
    define("EOL",PHP_EOL);

echo EOL.'----IOFrame Simple Watcher----'.EOL.EOL;

//-------------------- Get user options --------------------
$test = false;
$flags = getopt('f:r:i:o:d:hxts',['fulloath']);
//Help message
if(isset($flags['h']))
    die('Available flags are:'.EOL.'
    -h displays this Help message'.EOL.'
    -f location of json File RELATIVE to this folder [REQUIRED]'.EOL.'
    -r Root prefixed to all watched (and updated) files. Defaults to IOFrame root'.EOL.'
    -i Interval for checks in seconds. Defaults to 2 seconds. Minimum 1'.EOL.'
    -d Default depth to watch folders. Defaults to 1 (direct children only)'.EOL.'
    -o timeOut - number of times a changed file that failed to update tries again. Minimum 0, maximum 100, default 3. '.EOL.'
    -s Silent mode - will not print changes made to files - Defaults to false'.EOL.'
    -t Test mode - will not change files, only print would-be changes (overrides silent mode)'.EOL.'
    -x Overrides files (even if not newer)'.EOL.'
    ');

if(!isset($flags['f']))
    die('File location must be provided - option name -f'.EOL);

if(isset($flags['r']))
    $baseUrl = $flags['r'];

if(isset($flags['i']))
    $interval = max((int)$flags['i'],1);
else
    $interval = 2;

if(isset($flags['d']))
    $depth = min(max((int)$flags['d'],1),10);
else
    $depth = 1;

if(isset($flags['o']))
    $timeout = min(max((int)$flags['o'],0),100);
else
    $timeout = 3;

if(isset($flags['s']))
    $silent = true;
else
    $silent = false;

if(isset($flags['t'])){
    $test = true;
    $silent = false;
}

$overwrite = isset($flags['x']);

$jsonPath = __DIR__.'/'.$flags['f'];

if(!is_file($jsonPath))
    die('Provided json file address does not exist!'.EOL);

if(!is_file($jsonPath))
    die('Provided json file does not exist!'.EOL);

$FileHandler = new IOFrame\Handlers\FileHandler();
try{
    $JSONFile = $FileHandler->readFile($jsonPath,'',[]);
}
catch (Exception $e){
    die('Failed to open JSON file, exception '.$e->getMessage().EOL);
}

if(!\IOFrame\Util\is_json($JSONFile))
    die('Provided file is not a JSON!'.EOL);

$inputs = json_decode($JSONFile,true);
$watchArray = [];

//The following function is here to allow recursively watching folders
function populateWatchArray($inputs,&$watchArray,$depth,$silent,$test,$baseUrl,$overwrite = false){
    foreach($inputs as $index => $input){
        if(empty($input['target'])){
            if(!$silent)
                echo 'Watched file '.$index.' has no target!'.EOL;
            continue;
        }
        if(empty($input['destination'])){
            if(!$silent)
                echo 'Watched file '.$index.' has no destination!'.EOL;
            continue;
        }
        $fullTarget = $baseUrl.$input['target'];
        $fullDestination = $baseUrl.$input['destination'];
        $changeTime = @filemtime($fullTarget);
        if(!$changeTime){
            if(!$silent)
                echo 'Watched file '.$index.' target file '.$fullTarget.' does not exist!'.EOL;
            continue;
        }
        elseif(is_file($fullTarget)){
            if(!$silent)
                echo '['.$changeTime.'] '.$fullTarget.' added to watchlist'.EOL;
            if((!is_file($fullDestination) || @filemtime($fullDestination) < $changeTime) || $overwrite){
                if(!$silent){
                    if(is_file($fullDestination))
                        echo 'Watched file '.$index.' target file '.$fullDestination.' does not exist, copying file!'.EOL;
                    else
                        echo 'Watched file '.$index.' target file '.$fullDestination.' is older than source, copying file!'.EOL;
                }
                if(!$test)
                    @copy($fullTarget,$fullDestination);
            }
            $input['changedAt'] = $changeTime;
            $input['failedAttempts'] = 0;
            $input['fullTarget'] = $fullTarget;
            $input['fullDestination'] = $fullDestination;
            array_push($watchArray,$input);
        }
        elseif(is_dir($fullTarget)){
            $currentDepth = isset($input['depth']) ? $input['depth'] : $depth;
            if($currentDepth < 1){
                if(!$silent)
                    echo '['.time().'] '.$fullTarget.' cannot be added, exceeding maximum depth.'.EOL;
                continue;
            }

            if(is_file($fullDestination)){
                if(!$silent)
                    echo 'Watched file '.$index.' target folder '.$fullDestination.' is a file, cannot watch!'.EOL;
                continue;
            }
            elseif(!is_dir($fullDestination)){
                if(!$silent)
                    echo 'Watched file '.$index.' target folder '.$fullDestination.' does not exist, creating empty dir!'.EOL;
                if(!$test)
                    mkdir( $fullDestination, 0777, true );
            }

            $targetDirFiles = $scanned_directory = array_diff(scandir($fullTarget), array('..', '.'));;
            $dirInputs = [];
            foreach($targetDirFiles as $newTarget){
                array_push($dirInputs,[
                    'target'=>$input['target'].'/'.$newTarget,
                    'destination'=>$input['destination'].'/'.$newTarget,
                    'depth'=>$currentDepth-1,
                ]);
            }
            populateWatchArray($dirInputs,$watchArray,$depth-1,$silent,$test,$baseUrl,$overwrite);
        }
        else{
            if(!$silent)
                echo $fullTarget.' is neither a file nor a directory!'.EOL;
        }
    }
}
populateWatchArray($inputs,$watchArray,$depth,$silent,$test,$baseUrl,$overwrite);

//Input related - not on windows
$windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
if(!$windows){
    stream_set_blocking(STDIN, 0);
    stream_set_read_buffer(STDIN, 0);
    function non_block_read($fd, &$data) {
        $read = array($fd);
        $write = array();
        $except = array();
        $result = stream_select($read, $write, $except, 0);
        if($result === false) throw new Exception('stream_select failed');
        if($result === 0) return false;
        $data = stream_get_line($fd, 1);
        return true;
    }
}

if(!isset($flags['fulloath']))
    echo "-- And now my watch begins.. --".EOL;
else
    echo 'Changes gather, and now my watch begins.'.EOL.
        'It shall not end until my process is terminated.'.EOL.
        'I shall make no forks, throw no exceptions and spawn no children.'.EOL.
        'I shall make no pushes and merge no commits.'.EOL.
        'I shall live and die at my post.'.EOL.
        'I am the script in the darkness.'.EOL.
        'I am the watcher of the files.'.EOL.
        'I am the tool that improves quality of life.'.EOL.
        'I pledge my life and honor to this project,'.EOL.
        'for this sprint and all the sprints to come.'.EOL;
echo "Press 'q' to quit on Linux, Ctrl+'c' on windows".EOL;
$cycleStart = time();
while(true){
    if($windows)
        sleep($interval);
    else
        usleep(200000); // 5 checks per second is enough
    if(!$windows || ($cycleStart+$interval > time())){
        $x = "";
        if(!$windows && non_block_read(STDIN, $x)) {
            if($x === 'q')
                break;
        }
    }
    else{
        if(count($watchArray) == 0){
            if(!$silent)
                echo 'Exiting because no more files left to watch!'.EOL.time().EOL;
            break;
        }

        foreach($watchArray as $index => $input){
            //filemtime apparently doesn't always registers when files get deleted/moved after the first time - on windows at least
            if(!file_exists($input['fullTarget'])){
                if(!$silent)
                    echo '['.time().'] '.$input['target'].' no longer exists!'.EOL;
                array_splice($watchArray,$index,1);
                continue;
            }
            $changeTime = @filemtime($input['fullTarget']);
            if($input['changedAt'] !== $changeTime){
                if(!$silent)
                    echo '['.$changeTime.'] '.$input['target'].' changed.'.EOL;

                if(!$test)
                    $copy = @copy($input['fullTarget'],$input['fullDestination']);
                else
                    $copy = true;

                if(!$copy) {

                    if(!$silent){
                        echo '['.time().'] '.$input['destination'].' failed to update'.EOL;
                        echo "Error: ".error_get_last().EOL;
                    }

                    if(++$watchArray[$index]['failedAttempts'] > $timeout){
                        if(!$silent)
                            echo '['.time().'] '.'Last failure attempt, no longer watching file!'.EOL;
                        array_splice($watchArray,$index,1);
                    }

                }
                else {

                    if(!$silent)
                        echo '['.time().'] '.$input['destination'].' updated.'.EOL;

                    $watchArray[$index]['changedAt'] = $changeTime;
                    $watchArray[$index]['failedAttempts'] = 0;

                }

            }
        }

        $cycleStart = time();
    }
}

echo "-- .. and now my watch has ended. --".EOL;



?>