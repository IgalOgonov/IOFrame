<?php

$params = [
    'test'=>$test,
    'update'=>$inputs['update'],
    'override'=>$inputs['override'],
];

$inputs = [
    'id'=> $inputs['id'],
    'firstName' => $inputs['firstName'],
    'lastName' => $inputs['lastName'],
    'email' => $inputs['email'],
    'phone' => $inputs['phone'],
    'fax' => $inputs['fax'],
    'country' => $inputs['country'],
    'state' => $inputs['state'],
    'city' => $inputs['city'],
    'street' => $inputs['street'],
    'zipCode' => $inputs['zipCode'],
    'companyName' => $inputs['companyName'],
    'companyID' => $inputs['companyID'],
    'contactInfo' => $inputs['contactInfo'],
    'address' => $inputs['address'],
    'extraInfo' => $inputs['extraInfo']
];

$result = $ContactHandler->setContact($inputs['id'],$inputs,$params);