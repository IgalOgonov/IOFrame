<?php

/** Asserts a variable is a valid class object
 * @param mixed $settings The object to validate
 * @param string $expectedClass Name of the class
 * @returns bool
 * */
$assertCorrectClass = function (mixed $settings, string $expectedClass){
    return !empty($settings) &&
        is_object($settings) &&
        (get_class($settings) === $expectedClass);
};