<?php

use Proxy\Filter\FilterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function \GuzzleHttp\json_decode;
use function \GuzzleHttp\json_encode;
use function \GuzzleHttp\Psr7\stream_for;

class AddSpecialEpisodesFilter implements FilterInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {
        $response = $next($request, $response);

        if ($response->getStatusCode() == 200 &&
            preg_match('#v1/tvdb/shows/en/([0-9]+)#', $request->getUri()->getPath(), $id)) {
            $id = $id[1];

            $content = json_decode($response->getBody());

            foreach ($content->episodes as $episode) {
                if (isset($episode->airedAfterSeasonNumber)) {
                    $episode->displaySeasonNumber  = $episode->airedAfterSeasonNumber;
                    $episode->displayEpisodeNumber = null;
                } elseif (isset($episode->airedBeforeSeasonNumber)) {
                    $episode->displaySeasonNumber  = $episode->airedBeforeSeasonNumber;
                    $episode->displayEpisodeNumber = $episode->airedBeforeEpisodeNumber;
                }

            }

            $response = $response->withBody(stream_for(
                json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ));
        }

        return $response;
    }
}
