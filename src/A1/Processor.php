<?php
namespace Pctco\Reptile\A1;
class Processor{
    function __construct(){
        $JsonPath = app()->getRootPath().'entrance/static/library/json/reptile/A1.json';
        $config = file_get_contents($JsonPath);
        $this->config = json_decode($config,true);
        $this->config['json'] = [
            'path'  =>  $JsonPath
        ];
        $this->movie = new Movie($this->config);
    }
    public function timers(){
        $movie = $this->movie->timers();
        return $movie;
    }
}
