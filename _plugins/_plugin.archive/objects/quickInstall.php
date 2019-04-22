<?php

if($test)
    echo 'quickInstall of objects activates here! Options are '.json_encode($options).EOL;
else{

//Handle the files
    $urlsToCopy = array();
    array_push(
        $urlsToCopy,
        [
            $this->settings->getSetting('absPathToRoot').'_plugins/objects/js/objects.js',
            $this->settings->getSetting('absPathToRoot').'js/plugins/objects.js'
        ]
    );
    foreach($urlsToCopy as $urls) {
        if (file_exists($urls[0]))
            if (!$test)
                copy($urls[0], $urls[1]);
            else
                echo 'Copying ' . $urls[0] . ' to ' . $urls[1] . EOL;
    }
}















?>