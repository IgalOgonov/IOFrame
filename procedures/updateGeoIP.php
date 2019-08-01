<?php
if(!defined('helperFunctions'))
    require __DIR__ . '/../IOFrame/Util/helperFunctions.php';

function updateGeoIP($rootDir){
    set_time_limit(0); // unlimited max execution time
//Make temp directory
    if(!is_dir($rootDir.'localFiles/temp/geoIP')){
        if(!mkdir($rootDir.'localFiles/temp/geoIP'))
            die('Cannot create temp directory for some reason - most likely insufficient user privileges, or it already exists');
    }
    file_put_contents(
        $rootDir.'localFiles/temp/geoIP/GeoLite2-Country.tar.gz',
        file_get_contents('https://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.tar.gz')
    );
    $a = new PharData($rootDir.'localFiles/temp/geoIP/GeoLite2-Country.tar.gz');
//Extract the root folder from the tar
    $a->extractTo($rootDir.'localFiles/temp/geoIP');
//Remove temp file
    unlink($rootDir.'localFiles/temp/geoIP/GeoLite2-Country.tar.gz');
//Find the name of the folder we extracted
    $extractedFolder = scandir($rootDir."/localFiles/temp/geoIP")[2];
    $extractedFolderUrl = $rootDir.'/localFiles/temp/geoIP/'.$extractedFolder;
//Copy the file to localFiles
    file_put_contents(
        $rootDir.'localFiles/geoip-db/GeoLite2-Country.mmdb',
        file_get_contents($extractedFolderUrl.'/GeoLite2-Country.mmdb')
    );
//Delete the folder we extracted
    IOFrame\Util\folder_delete($extractedFolderUrl);
    rmdir($rootDir.'localFiles/temp/geoIP');
}