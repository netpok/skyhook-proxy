<?php

class JwtManager extends \Eljam\GuzzleJwt\Manager\JwtManager
{
    /** @var \Illuminate\Cache\Repository */
    protected $cache;

    public function getJwtToken()
    {
        if (!$this->token) {
            $this->token = $this->cache->get('token');
        }

        if (!$this->token || !$this->token->isValid()) {
            $this->cache->forever('token', parent::getJwtToken());
        }

        return $this->token;
    }

    public function invalidate()
    {
        $this->token = null;
        $this->cache->forget('token');
    }

    public function setCache(\Illuminate\Cache\Repository $cache)
    {
        $this->cache = $cache;
    }
}
