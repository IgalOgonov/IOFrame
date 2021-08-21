<?php
if($inputs['id'] !== null){
    if(!preg_match('/'.IDENTIFIER_REGEX.'/',$inputs['id'])){
        if($test)
            echo 'ID must match '.IDENTIFIER_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else{
    if($test)
        echo 'ID must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($inputs['firstName'] !== null){
    if(!preg_match('/'.NAME_REGEX.'/',$inputs['firstName'])){
        if($test)
            echo 'firstName must match '.NAME_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['lastName'] !== null){
    if(!preg_match('/'.NAME_REGEX.'/',$inputs['lastName'])){
        if($test)
            echo 'lastName must match '.NAME_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['email'] !== null){
    if(!filter_var($inputs['email'],FILTER_VALIDATE_EMAIL)){
        if($test)
            echo 'Email must be valid!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['phone'] !== null){
    if(!preg_match('/'.PHONE_REGEX.'/',$inputs['phone'])){
        if($test)
            echo 'phone must match '.PHONE_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['fax'] !== null){
    if(!preg_match('/'.PHONE_REGEX.'/',$inputs['fax'])){
        if($test)
            echo 'fax must match '.PHONE_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['country'] !== null){
    if(!preg_match('/'.COUNTRY_REGEX.'/',$inputs['country'])){
        if($test)
            echo 'country must match '.COUNTRY_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['state'] !== null){
    if(!preg_match('/'.STATE_REGEX.'/',$inputs['state'])){
        if($test)
            echo 'state must match '.STATE_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['city'] !== null){
    if(!preg_match('/'.CITY_REGEX.'/',$inputs['city'])){
        if($test)
            echo 'city must match '.CITY_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['street'] !== null){
    if(!preg_match('/'.STREET_REGEX.'/',$inputs['street'])){
        if($test)
            echo 'street must match '.STREET_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['zipCode'] !== null){
    if(!preg_match('/'.ZIP_CODE_REGEX.'/',$inputs['zipCode'])){
        if($test)
            echo 'zipCode must match '.ZIP_CODE_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['companyName'] !== null){
    if(!preg_match('/'.COMPANY_REGEX.'/',$inputs['companyName'])){
        if($test)
            echo 'companyName must match '.COMPANY_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['companyID'] !== null){
    if(!preg_match('/'.COMPANY_ID_REGEX.'/',$inputs['companyID'])){
        if($test)
            echo 'companyID must match '.COMPANY_ID_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}