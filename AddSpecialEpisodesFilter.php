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

class AddSpecialEpisodesFilter implements FilterInterface
{
    const LOCATION = 'location';
    protected $client;
    protected $cache;
    protected $jwtManager;

    /**
     * AddSpecialEpisodesFilter constructor.
     */
    public function __construct()
    {
        $this->cache = new Repository(new FileStore(new Filesystem(), __DIR__ . '/cache'));

        $this->jwtManager = new JwtManager(
            new Client(['base_uri' => 'https://api.thetvdb.com/']),
            new JsonAuthStrategy([
                'username'    => trim(file_get_contents(__DIR__ . '/tvdb.apikey')),
                'json_fields' => ['apikey', 'userkey'],
            ]),
            [
                'token_url' => '/login',
            ]
        );
        $this->jwtManager->setCache($this->cache);

        $handlerStack = HandlerStack::create();
        $handlerStack->push(new JwtMiddleware($this->jwtManager));
        $this->client = new Client([
            'handler'  => $handlerStack,
            'base_uri' => 'https://api.thetvdb.com/',
        ]);
    }

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
            if (isset($content->seasons[0]) && !isset($content->seasons[0]->seasonNumber)) {
                /** @var \Illuminate\Support\Collection $specials */
                $specials = $this->cache->remember($id, Carbon::now()->addHours(12), function () use ($id) {
                    return $this->getSpecialEpisodes($id);
                });

                foreach ($content->episodes as $episode) {
                    if ($special = $specials->firstWhere('id', $episode->tvdbId)) {
                        $episode->displaySeasonNumber  = $special['airsAfterSeason'] ?? $special['airsBeforeSeason'];
                        $episode->displayEpisodeNumber = $special['airsBeforeEpisode'];
                    }
                }

                $response = $response->withBody(stream_for(
                    json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ));
            }
        }

        return $response;
    }

    protected function getSpecialEpisodes($id)
    {
        return $this->getEpisodes($id)->where('airedSeason', 0)->values();
    }

    protected function getEpisodes($id)
    {
        $page     = 1;
        $episodes = [];

        do {
            $result   = $this->getPagedEpisodes($id, $page);
            $page     = $result['links']['next'];
            $episodes = array_merge($episodes, $result['data']);
        } while ($page);

        return collect($episodes);
    }

    protected function getPagedEpisodes($id, $page)
    {
        try {
            return $this->getPagedEpisodesWithGuzzle($id, $page);
        } catch (\GuzzleHttp\Exception\ClientException $exception) {
            if($exception->getCode() == 401){
                $this->jwtManager->invalidate();
                return $this->getPagedEpisodesWithGuzzle($id, $page);
            }

            throw $exception;
        }
    }

    protected function getPagedEpisodesWithGuzzle($id, $page)
    {
        return \GuzzleHttp\json_decode(
            $this->client->get('https://api.thetvdb.com/series/' . $id . '/episodes?page=' . $page)->getBody(),
            true
        );
    }
}
