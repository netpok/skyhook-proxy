<?php

use Carbon\Carbon;
use Eljam\GuzzleJwt\JwtMiddleware;
use Eljam\GuzzleJwt\Strategy\Auth\JsonAuthStrategy;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Proxy\Filter\FilterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function \GuzzleHttp\json_decode;
use function \GuzzleHttp\json_encode;
use function \GuzzleHttp\Psr7\stream_for;

class AddAliasFilter implements FilterInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {
        $response = $next($request, $response);

        if ($response->getStatusCode() == 200 && '/v1/scenemapping' == $request->getUri()->getPath()) {
            $content = json_decode($response->getBody());

            $content = array_merge(json_decode(
                @file_get_contents(__DIR__ . '/custom-mappings.json') ?: '[]'
            ), $content);

            $response = $response->withBody(stream_for(
                json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ));
        }

        return $response;
    }
}
