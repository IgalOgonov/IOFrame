<?php

$dynamicIncludeUrls = \IOFrame\Util\FileSystemFunctions::fetchAllFolderAddresses(__DIR__.'/../include/', ['subFolders'=>true,'include'=>['\.php$']]);

foreach ($dynamicIncludeUrls as $url)
    require $url;