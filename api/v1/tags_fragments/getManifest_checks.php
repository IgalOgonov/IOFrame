<?php
if($inputs['updatedAfter'] && !filter_var($inputs['updatedAfter'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'updatedAfter incorrect format'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}