<?php
namespace Pctco\Reptile\Website;
use Pctco\Reptile\Tools;
use QL\QueryList;
class SmashingmagazineCom{
   function __construct(){
      $this->domain = 'https://www.smashingmagazine.com';
      $this->task = [];
      $this->tools = new Tools();
   }
   public function article(){
      $task = $this->task;
      $rules = [
         'title' => ['h1.article--post__title >a','text','',function($title){
            return $title;
         }],
         'page' => ['h1.article--post__title >a','href','',function($page){
            return $page;
         }]
      ];
      $query = QueryList::get($task->url)->rules($rules)->query()->getData()->all();

      return $this->tools->data($query,$task);
   }

   public function task(){
      $task = $this->tools->list($this->domain);

      if ($task->parameter['function'] === 'article') {
         $this->task = $task;
         return $this->article();
      }

      return '['.date('H:i:s').'] $this->'.$task['parameter']['function'].'(error);';
   }
}
