<?php

require_once SRRL_ROOT.'/requests/SimpleYql.php';

class TestYql extends SimpleModule
{
    protected $cacheKey = 'music.artist.popular';
    protected $artists = array();
    protected $artistCount = null;
    protected $releases = array();
    
    public function setupParams()
    {
        parent::setupParams();
        $this->addSettable('table');
    }
    
    public function initCacheObject()
    {
        return new SimpleCache($this->cacheKey, __DIR__.'/../_cache/modules/');
    }
    
    public function getData($data = null)
    {
        if(is_null($data))
        {
            $yql = new SimpleYql();
            //$yql->enableDebug(); $yql->setDebugDetails(array('url'));
            $yql->setupHandle('music.artist.popular', array('parseResponse'=>1));
            
            $this->final = false;
            
            return $yql;
        }
        else if(is_null($this->artistCount))
        {
            $reqs = array();
            $artists = SimpleYql::getResults($data);
            
            $this->artistCount = SimpleYql::getCount($data);
            
            if(empty($artists) || empty($this->artistCount))
            {
                return $reqs;
            }
            
            foreach($artists as $artist)
            {
                $yql = new SimpleYql();
                $reqs[] = $yql->setupRequest('music.release.artist', array('where'=>'id="'.$artist['id'].'"', 'parseResponse'=>1));
                
                $id = 'id'.$artist['id'];
                $this->artists[$id] = $artist;
            }
            
            return $reqs;
        }
        else
        {
            $releases = SimpleYql::getResults($data);
            foreach($releases as $release)
            {
                $id = 'id'.SimpleUtil::getItemByPath($release, 'Artist.id');
                if(isset($this->artists[$id]))
                {
                    $this->artists[$id]['releases'][] = $release;
                }
            }
            
            if(--$this->artistCount == 0)
            {
                $this->final = true;
            }
            
            return true;
        }
    }
    
    public function render($data)
    {
        $this->setPageTitle('Top Music Artists');
        
        $artists = array();
        foreach($this->artists as $artist)
        {
            $releases = array();
            foreach(SimpleUtil::getValue($artist, 'releases', array()) as $release)
            {
                $releases[] = $this->html->a(SimpleUtil::getValue($release, 'title'), SimpleUtil::getValue($release, 'url'));
            }
            
            $artists[] =
                $this->html->a(SimpleUtil::getValue($artist, 'name'), SimpleUtil::getValue($artist, 'website')).' '.
                $this->html->small('('.$this->html->a('on Yahoo!', SimpleUtil::getValue($artist, 'url')).')').' '.
                $this->html->ul($releases);
        }
        
        return $this->html->ul($artists);
    }
}

