<?php
require_once 'functionsClassAssertion.php';

/** Asserts a variable is a valid settings
 * @param mixed $settings The object to validate
 * @return bool|mixed
 */
$assertCorrectSettings = function (mixed $settings) use($assertCorrectClass){
    return $assertCorrectClass($settings,'IOFrame\Handlers\SettingsHandler');
};

/** Asserts a settings object includes specific settings
 * @param IOFrame\Handlers\SettingsHandler $settings The object to validate
 * @param $expectedSettings
 * @return array|true
 */
$assertExistingSettings = function (IOFrame\Handlers\SettingsHandler $settings,$expectedSettings){
    $diff = array_diff( $expectedSettings, array_keys($settings->getSettings()) );
    return count( $diff ) > 0 ? $diff : true;
};