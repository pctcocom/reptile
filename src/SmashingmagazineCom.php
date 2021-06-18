<?php
namespace Pctco\Sms;
use QL\QueryList;
/**
 * https://www.smashingmagazine.com/
 */
class ClassName extends AnotherClass{
   function __construct(){
      $this->name = 'smashingmagazine.com';
      $this->domain = 'https://www.smashingmagazine.com';
      $this->type = 'website';
      $this->page = [
         'article'   =>   385
      ];
   }
   public function article(){
      dump(__FUNCTION__);

      exit;
      $cache = Cache::store('reptile')->get($website);
      $page = $cache['page'];
      $rules = [
         'arr' => ['ul.bookmark-list >li.bookmark-item >span.bookmark-item-img','html','',function($html){
            $alist = str_replace(array("\r\n", "\r", "\n"), "", $html);
            preg_match_all('/<a .*?href="(.*?)".*?>/is',$alist,$url);
            preg_match_all('/<a .*?>(.*?)<\/a>/is',$alist,$text);

            $arr = [];
            foreach ($url[1] as $k => $v) {
               $arr[] = [
                  'title'   =>  empty(trim($text[1][$k]))?'':trim($text[1][$k]),
                  'url'   =>  $v
               ];
            }

            return $arr;
         }]
      ];

      $data = QueryList::get($config[$website]['list'],[
         'page' => $page,
         'ajax' => 1
      ],[
         'timeout' => 30,
         'headers' => [
             'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727',
             'Accept'     => 'application/json'
          ]
      ])->rules($rules)->query()->getData()->all();
      foreach ($data['arr'] as $v) {
         if (strstr($v['url'], 'http') != false) {
            $v['url'] = str_replace(array('%20','%09'), "",$v['url']);
            $parse = parse_url($v['url']);
         }

         if ($this->where('host',$parse['host'])->count() === 0) {
            $this->insert([
               'title_original'   =>   $v['title'],
               'scheme'   =>   $parse['scheme'],
               'host'   =>   $parse['host'],
               'path'   =>   empty($parse['path'])?'/':$parse['path'],
               'website'   =>   $website,
               'atime'   =>   time()
            ]);
         }
      }
      if ($page > 1) {
         $page = $page - 1;
         Cache::store('reptile')->set($website,[
            'page'   =>   $page
         ]);
      }
      return $website.' function GetList page = '.$page.' '.date('Y-m-d h:m:s',time());
   }
}
