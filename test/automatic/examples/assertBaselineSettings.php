<?php
require_once __DIR__.'/../common/functionsSettingsAssertion.php';

/** Validates that the base local settings set on installation actually exist
*/
$_test = function($inputs,&$errors,$context,$params)
use($assertCorrectSettings,$assertExistingSettings)
{
    if(!$assertCorrectSettings($params['defaultParams']['localSettings'])){
        $errors['invalid-settings-input'] = true;
        return false;
    }

    if(!$assertExistingSettings($params['defaultParams']['localSettings'],['absPathToRoot','pathToRoot','opMode'])){
        $errors['missing-expected-settings'] = $assertExistingSettings;
        return false;
    }
    return true;
};