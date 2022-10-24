<?php
namespace Pctco\Reptile;
use Pctco\Info\HttpProxy;
use Pctco\Types\Arrays;
use Pctco\File\Markdown;
use QL\QueryList;
use QL\Ext\Chrome;
use Spatie\Browsershot\Browsershot;
class Tools{
   function __construct(){
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

   /** 
    ** books 处理
    *? @date 22/10/13 21:50
    *  @param $options['event'] 将 books .md menu 转换成 php array
    *  @param $options['config'] books $this->config
    */
   public function books($options){
      $options = Arrays::merge([],[
         'event'  => 'menu.md-to-array',
         'path' => '/path/menu.md',
         'config' => []
      ],$options);

      if ($options['event'] === 'menu.md-to-array') {
         $menuJson = str_replace('.json','/'.$options['config']['active']['books'].'/menu.json',$options['config']['json']['path']);
         if ($options['config']['books'][$options['config']['active']['books']]['menu'] === 0) {
            $path = str_replace('.json','/'.$options['config']['active']['books'].'/menu.md',$options['config']['json']['path']);

            $mds = file_get_contents($path);
            // 将md menu数据转换成html
            $html = $this->markdown->text($mds);

            // 将html ul li 转换成数组 目前只支持三级
            $data = QueryList::html($html)->find('>ul >li')->htmls()->all();

            $array = [];
            $id = 1;
            foreach ($data as $v) {
               $a = QueryList::html($v)->rules([
                     'name' => ['>a','text'],
                     'url' => ['>a','href']
               ])
               ->query()
               ->getData()
               ->all();

               $html = QueryList::html($v)->find('>ul >li')->htmls()->all();

               $array[] = [
                     'name'  =>  $a[0]['name'],
                     'url'   =>  $a[0]['url'],
                     'id'    =>  $id++,
                     'pid'   =>  0,
                     // false = 还没采集页面内容，true 已采集页面内容
                     'page'  =>  false,
                     'html'  =>  $html
               ];
            }

            foreach ($array as $k2 => $v2) {
               foreach ($v2['html'] as $k3 => $v3) {
                     $a = QueryList::html($v3)->rules([
                        'name' => ['>a','text'],
                        'url' => ['>a','href']
                     ])
                     ->query()
                     ->getData()
                     ->all();

                     $html = QueryList::html($v3)->find('>ul >li')->htmls()->all();
                     if (empty($html)) {
                        unset($array[$k2]['html'][$k3]);
                     }
                     
                     $array[] = [
                        'name'  =>  $a[0]['name'],
                        'url'   =>  $a[0]['url'],
                        'id'    =>  $id++,
                        'pid'   =>  $v2['id'],
                        'page'  =>  false,
                        'html'  =>  $html
                     ];
               }

               unset($array[$k2]['html']);
               
            }

            foreach ($array as $k4=>$v4) {
               if (!empty($v4['html'])) {
                     foreach ($v4['html'] as $v5) {
                        $a = QueryList::html($v5)->rules([
                           'name' => ['>a','text'],
                           'url' => ['>a','href']
                        ])
                        ->query()
                        ->getData()
                        ->all();

                        $array[] = [
                           'name'  =>  $a[0]['name'],
                           'url'   =>  $a[0]['url'],
                           'id'    =>  $id++,
                           'pid'   =>  $v4['id'],
                           'page'  =>  false,
                        ];
                     }
               }
               unset($array[$k4]['html']);
            }

            file_put_contents($menuJson,json_encode($array));

            $options['config']['books'][$options['config']['active']['books']]['menu'] = count($array);
            $options['config']['books'][$options['config']['active']['books']]['timers'] = time();

            file_put_contents($options['config']['json']['path'],json_encode($options['config']));

            return [
               'handle'  => 'generate-menu-json',
               'data'   => $array
            ];
         }

         $data = json_decode(file_get_contents($menuJson),true);

         return [
            'handle'  => 'collect-page-data',
            'path'   => [
               'menuJson'   => $menuJson
            ],
            'data'   => $data
         ];
      }

      return false;
   }

   /** 
    ** markdown loader  主要为了处理code pre 中有搞了代码
    *? @date 22/10/23 18:21
    */
   /** 
    ** 
    *? @date 22/10/24 22:10
    *  @param $event 事件类型
    *  @param $request 请求
    *             $event = markdown-loader-url 时 则是url地址
    *             $event = html-content 时 html 内容
    *! @return String
    */
   public function MarkdownLoader($options){
      $options = Arrays::merge([],[
         'event'  => 'markdown-loader-url',
         'request'  => ''
      ],$options);

      $this->MarkdownLoaderOptions = $options;
      
      if ($options['event'] === 'html-content') {
         if (empty($options['request'])) return false;
         
         $content = $this->markdown->html($options['request'],[
            'tags' => [
                'strip_tags' => true
            ],
            'table' =>  [
                'converter' =>  true
            ]
         ]);
         return $content;
      }

      /** 
       ** markdown-loader-url
       *? @date 22/10/24 22:09
       */
      if ($options['event'] === 'markdown-loader-url') {
         // 等待完善
         $content = 'https://markdown-loader-url.netlify.app/?url='.$this->MarkdownLoaderOptions['request'];

         $content = $this->markdown->html($content,[
            'tags' => [
                'strip_tags' => true
            ],
            'table' =>  [
                'converter' =>  true
            ]
         ]);

         return $content;
      }
   }
}