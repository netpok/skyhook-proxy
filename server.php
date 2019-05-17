<?php
require('vendor/autoload.php');

if (class_exists(\Whoops\Run::class)) {
    $whoops = new \Whoops\Run;
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
    $whoops->register();
}

require('AddSpecialEpisodesFilter.php');
require('AddAliasFilter.php');
require('JwtManager.php');

use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Zend\Diactoros\ServerRequestFactory;

// Create a PSR7 request based on the current browser request.
$request = ServerRequestFactory::fromGlobals();

// Create a guzzle client
$guzzle = new GuzzleHttp\Client(['http_errors' => false]);

// Create the proxy instance
$proxy = new Proxy(new GuzzleAdapter($guzzle));

// Add a response filter that removes the encoding headers.
$proxy->filter(new RemoveEncodingFilter());

switch (explode('.', $request->getUri()->getHost())[0]) {
    case 'skyhook':
        $proxy->filter(new AddSpecialEpisodesFilter());
        $response = $proxy->forward($request)->to('http://skyhook.sonarr.tv');
        break;
    case 'sonarr-services':
        $proxy->filter(new AddAliasFilter());
        $response = $proxy->forward($request)->to('http://services.sonarr.tv');
        break;
    default:
        $response = new \Zend\Diactoros\Response\JsonResponse(['message' => 'Not found'], 404);
}
// Output response to the browser.
//var $response;
(new Zend\Diactoros\Response\SapiEmitter)->emit($response);

