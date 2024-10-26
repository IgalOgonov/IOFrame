<script>
    let canAccessCP = <?php echo (($auth->isAuthorized() || $auth->hasAction('CAN_ACCESS_CP')) ? 'true' : 'false')?>;
    if( (document.location.pathname !== document.ioframe.rootURI + 'cp/account') && ((!document.ioframe.loggedIn && !localStorage.getItem('sesID')) || (document.ioframe.loggedIn && !canAccessCP)) )
            document.location = document.ioframe.rootURI + 'cp/login';

<?php
/*This checks whether there is a possible system update, and whether the user is authorized to update*/
$availableVersion = \IOFrame\Util\FileSystemFunctions::readFile($rootFolder.'/meta/', 'ver');
$currentVersion = $siteSettings->getSetting('ver');
if($availableVersion && $currentVersion && ($currentVersion !== $availableVersion) && ($auth->isAuthorized() || $auth->hasAction('CAN_UPDATE_SYSTEM'))){
    $versions = [
        'currentVersion'=>$currentVersion,
        'availableVersion'=>$availableVersion
    ];
    echo 'document["ioframe"] = {...document["ioframe"], ...'.json_encode($versions).'}';
}
unset($availableVersion,$currentVersion,$versions);
?>

</script>
