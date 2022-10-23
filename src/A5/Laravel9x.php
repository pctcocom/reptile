<?php
namespace Pctco\Reptile\A5;
use Pctco\Reptile\Tools;
use QL\QueryList;
use Pctco\Types\Arrays;
use Pctco\File\Markdown;
class Laravel9x{
    function __construct($config = []){
        $this->config = $config;
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
    /** 
     ** 生成目录
     *? @date 22/10/13 21:20
     */
    public function handle(){
        $menu = 
        $this->tools->books([
            'event' =>  'menu.md-to-array',
            'config'    =>  $this->config
        ]);

        if ($menu['handle'] === 'generate-menu-json') {
            return __CLASS__.'\\'.__FUNCTION__.' books menu.json 生成成功共'.count($menu['data']).'个目录 '.date('H:i:s');
        }

        if ($menu['handle'] === 'collect-page-data') {
            $menuKey = array_search(false,array_column($menu['data'],'page'));

            if ($menuKey === false) {
                // 采集完毕
            }else{
                if ($menu['data'][$menuKey]['url'] == 'false') {
                    // 没有 url 的数据
                    $menu['data'][$menuKey]['page'] = true;
                }else{
                    // 正常采集中
                    try {
                        $request_url = $menu['data'][$menuKey]['url'];

                        $client = new \GuzzleHttp\Client();
            
                        $proxy = 
                        $client->request('GET',$request_url,[
                            'timeout' => 20
                        ]);
            
                        if ($proxy->getStatusCode() == 200) {
                            $html = $proxy->getBody()->getContents();
                        }else{
                            $proxy = false;
                        }
                    } catch (\Throwable $th) {
                        $proxy = false;
                    }
            
                    if ($proxy === false) {
                        return __CLASS__.'\\'.__FUNCTION__.' GuzzleHttp request html data error '.date('H:i:s');
                    }

                    $result = 
                    QueryList::html($html)
                    ->rules([
                        'content' =>  ['.extra-padding >.markdown-body','html','->.toc-wraper -.tocify-extend-page -blockquote:last(1) -blockquote:last(2)',function($content){
                            return $content;
                        }]
                    ])
                    ->query()
                    ->getData()
                    ->all();

                    $markdown_content = $this->markdown->html($result[0]['content'],[
                        'tags' => [
                            // 是否去除 HTML 标签
                            'strip_tags' => true
                        ],
                        'table' =>  [
                            // div table 转 Markdown tables
                            'converter' =>  true
                        ]
                    ]);
                    return $markdown_content;

                }
                // file_put_contents($menu['path']['menuJson'],json_encode($menu['data']));
            }

            return __CLASS__.'\\'.__FUNCTION__.' books menu.json page '.$menuKey.'/'.count($menu['data']).' 保存成功 '.date('H:i:s');
        }
    }
}