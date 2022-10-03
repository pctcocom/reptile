<?php
namespace Pctco\Reptile;
use Pctco\Info\HttpProxy;
use Pctco\Types\Arrays;
class Tools{
   function __construct(){
      
   }
   /** 
    ** 根据timers字段排序 获取最小的 timers 数组
    *? @date 22/07/09 00:54
    *  @param Array $arrays 必须是二维数组
    *  @param Boolean $result 返回结果类型  1.返回数组 2.返回key
    *! @return Array
    */
   public function MinTimers(Array $arrays,$result = 1){
      $ac = array_column($arrays,'timers');
      array_multisort($ac,SORT_ASC,$arrays);
      if ($result === 1) return $arrays[0];
      if ($result === 2) return key($arrays);
      
   }
   /** 
    ** proxy
    *? @date 22/07/09 01:06
    *  @param $status 是否开启代理
    *! @return Array
    */
   public function proxy($options = []){
      $options = Arrays::merge([],[
         'status'  => false,
         'get' => [
            'where'  => [
               'type'  =>  'httpproxy',
               'n5'    =>  'CHN'
            ],
            'order' =>  'timers'
         ]
      ],$options);

      if ($options['status']) {
         $HttpProxy = new HttpProxy;
         $proxy = $HttpProxy->get($options['get']);
         if (empty($proxy)) {
            return [
               'code'   => 410,
               'msg' => __CLASS__.'\\'.__FUNCTION__.' No HTTP Proxy resource '.date('H:i:s')
            ];
         }
         
         return [
            'code'   => 0,
            'ip'  => $proxy['n1'].':'.$proxy['n2'],
            'msg' => __CLASS__.'\\'.__FUNCTION__.' HTTP Proxy returned successfully '.date('H:i:s')
         ];
      }else{
         return [
            'code'   => 413,
            'msg' => __CLASS__.'\\'.__FUNCTION__.' proxy mode is not enabled '.date('H:i:s')
         ];
      }
   }
   /** 
    ** 是否对 GuzzleHttp 进行代理配置
    *? @date 22/08/30 10:38
    *  @param $config guzzle 配置信息
    *! @return Array
    */
   public function guzzle($options = []){
      $options = Arrays::merge([],[
         'proxy'  => [
            'status' => true,
            'get' => [
               'where'  => [
                  'type'  =>  'httpproxy',
                  'n5'    =>  'CHN'
               ],
               'order' =>  'timers'
            ]
         ],
         'guzzle' => []
      ],$options);

      $proxy = 
      $this->proxy($options['proxy']);
      if ($proxy['code'] === 0) {
         return Arrays::merge([],$options['guzzle'],[
            'proxy' =>  [
               'https'  => $proxy['ip'],
            ]
         ]);
      }
      return $options['guzzle'];
   }
}