<?php
namespace Pctco\Reptile;
use app\model\ReptileList;
use app\model\ReptileData;
class Tools{
   /**
   * @name list
   * @describe 任务列表
   **/
   public function list($domain){
      $task =
      ReptileList::where([
         'domain'   =>   $domain,
         'status'   =>   1
      ])
      ->order('utime')
      ->find();

      $task->parameter = json_decode($task->parameter,true);

      foreach ($task->parameter as $kp => $vp) {
         $task->page = str_replace('{'.$kp.'}',$vp,$task->page,$i);
      }
      $task->url = $task->domain.$task->page;
      return $task;
   }
   /**
   * @name data
   * @describe 任务数据
   **/
   public function data($query,$task){
      $insert = 1;
      foreach ($query as $k => $v) {
         $IsData =
         ReptileData::where([
            'id'   =>  $task->id,
            'url'   =>  $task->domain.$v['page']
         ])
         ->find();
         if (empty($IsData)) {
            $insert++;
            ReptileData::insert([
               'time'   =>   time(),
               'id'   =>   $task->id,
               'type'   =>   $task->parameter['function'],
               'title'   =>   $v['title'],
               'url'   =>   $task->domain.$v['page']
            ]);
         }
      }

      $task = $task->toArray();
      if ($task['parameter']['page'] > 1) {
         $task['parameter']['page']  = $task['parameter']['page'] - 1;
      }

      ReptileList::where([
         'id'   =>   $task['id']
      ])
      ->update([
         'utime'   =>   time(),
         'parameter'   =>   json_encode($task['parameter'])
      ]);

      $insert = $insert === 1?0:$insert;
      return '['.date('H:i:s').'] $this->'.$task['parameter']['function'].'(success)->page('.$task['parameter']['page'].')->insert('.$insert.');';
   }

   /**
   * @name employ
   * @describe 使用该数据
   **/
   public function employ($type = 'article'){
      $employ =
      ReptileData::where([
         'type'   =>   $type
      ])
      ->order('time')
      ->find()
      ->toArray();

      if (empty($employ)) {
         return [
            'title'   =>   '',
            'url'   =>   ''
         ];
      }

      return $employ;
   }
}
