<?php
require('vendor/autoload.php');
require('AddSpecialEpisodesFilter.php');
require('JwtManager.php');

use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Zend\Diactoros\ServerRequestFactory;

if (class_exists(\Whoops\Run::class)) {
    $whoops = new \Whoops\Run;
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
    $whoops->register();
}

// Create a PSR7 request based on the current browser request.
$request = ServerRequestFactory::fromGlobals();

// Create a guzzle client
$guzzle = new GuzzleHttp\Client(['http_errors' => false]);

// Create the proxy instance
$proxy = new Proxy(new GuzzleAdapter($guzzle));

// Add a response filter that removes the encoding headers.
$proxy->filter(new RemoveEncodingFilter());
$proxy->filter(new AddSpecialEpisodesFilter());

// Forward the request and get the response.
$response = $proxy->forward($request)->to('http://skyhook.sonarr.tv');

// Output response to the browser.
//var $response;
(new Zend\Diactoros\Response\SapiEmitter)->emit($response);

