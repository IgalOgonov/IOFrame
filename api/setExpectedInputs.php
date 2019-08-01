<?php

foreach($arrExpected as $expected){
    if(isset($_REQUEST[$expected]) && ($_REQUEST[$expected] === '' || strtolower($_REQUEST[$expected]) === 'null'))
        $inputs[$expected] = null;
    else
        if(isset($_REQUEST[$expected]))
            $inputs[$expected] = $_REQUEST[$expected];
        else
            $inputs[$expected] = null;
}
