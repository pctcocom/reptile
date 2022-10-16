<?php
namespace Pctco\Reptile\A5;
use Pctco\Reptile\Tools;
use QL\QueryList;
use Pctco\File\Markdown;
class Learnku{
    function __construct($config = []){
        $this->tools = new Tools();

        $this->markdown = new Markdown([
            'terminal'  =>  [
                'status'  =>  false,
                'template'  =>  'MacOS'
            ],
            'model'  => [
                'status'  =>  false
            ]
        ]);
    }
    public function test(){
        $md = app()->getRootPath().'entrance/static/library/json/reptile/A5/learnku.md';
        return $md;
        // $markdown->text($content);
        // $toc = $markdown->contentsList($type_return = 'string');
        // dump($toc);
    }
}