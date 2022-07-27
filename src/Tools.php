<?php
namespace Pctco\Reptile;
use Pctco\Info\HttpProxy;
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
    *  @param myParam1 Explain the meaning of the parameter...
    *  @param myParam2 Explain the meaning of the parameter...
    *! @return 
    */
   public function proxy(){
      // 是否开启代理
      $status = false;
      if ($status) {
         $HttpProxy = new HttpProxy;
         $zmhttp = $HttpProxy->get();
         
         if (empty($zmhttp)) {
            return [
               'code'   => 410,
               'msg' => __CLASS__.'\\'.__FUNCTION__.' No HTTP Proxy resource '.date('H:i:s')
            ];
         }
         
         return [
            'code'   => 0,
            'ip'  => $zmhttp['n1'].':'.$zmhttp['n2'],
            'msg' => __CLASS__.'\\'.__FUNCTION__.' HTTP Proxy returned successfully '.date('H:i:s')
         ];
      }else{
         return [
            'code'   => 0,
            'ip'  => '127.0.0.1',
            'msg' => __CLASS__.'\\'.__FUNCTION__.' proxy mode is not enabled '.date('H:i:s')
         ];
      }


      
   }
}