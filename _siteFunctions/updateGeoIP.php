<?php
if(!defined('helperFunctions'))
    require __DIR__.'/../_util/helperFunctions.php';

function updateGeoIP($rootDir){
    set_time_limit(0); // unlimited max execution time
//Make temp directory
    if(!is_dir($rootDir.'_siteFiles/temp/geoIP')){
        if(!mkdir($rootDir.'_siteFiles/temp/geoIP'))
            die('Cannot create temp directory for some reason - most likely insufficient user privileges, or it already exists');
    }
    file_put_contents(
        $rootDir.'_siteFiles/temp/geoIP/GeoLite2-Country.tar.gz',
        file_get_contents('https://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.tar.gz')
    );
    $a = new PharData($rootDir.'_siteFiles/temp/geoIP/GeoLite2-Country.tar.gz');
//Extract the root folder from the tar
    $a->extractTo($rootDir.'_siteFiles/temp/geoIP');
//Remove temp file
    unlink($rootDir.'_siteFiles/temp/geoIP/GeoLite2-Country.tar.gz');
//Find the name of the folder we extracted
    $extractedFolder = scandir($rootDir."/_siteFiles/temp/geoIP")[2];
    $extractedFolderUrl = $rootDir.'/_siteFiles/temp/geoIP/'.$extractedFolder;
//Copy the file to _siteFiles
    file_put_contents(
        $rootDir.'_siteFiles/geoip-db/GeoLite2-Country.mmdb',
        file_get_contents($extractedFolderUrl.'/GeoLite2-Country.mmdb')
    );
//Delete the folder we extracted
    IOFrame\folder_delete($extractedFolderUrl);
    rmdir($rootDir.'_siteFiles/temp/geoIP');
}