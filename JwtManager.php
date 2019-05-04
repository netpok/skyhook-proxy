<?php

class JwtManager extends \Eljam\GuzzleJwt\Manager\JwtManager
{
    /** @var \Illuminate\Cache\Repository */
    protected $cache;

    public function getJwtToken()
    {
        if(!$this->token){
            $this->token = $this->cache->get('token');
        }

        if($this->token && $this->token->isValid()){
            return $this->token;
        }

        return $this->cache->rememberForever('token', function () {
            return parent::getJwtToken();
        });
    }

    public function setCache(\Illuminate\Cache\Repository $cache)
    {
        $this->cache = $cache;
    }
}
