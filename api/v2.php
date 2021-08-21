<?php

require __DIR__.'/../vendor/autoload.php';
use Symfony\Component\HttpFoundation\Response;

$response = new Response();
$response->setStatusCode(404);
$response->setContent('test');
$response->send();