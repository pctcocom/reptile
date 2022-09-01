<?php
namespace Pctco\Reptile\A4;
use app\model\DatabaseManage;
use app\model\Article;
use Pctco\Reptile\Tools;
use Pctco\Coding\Skip32\Skip;
use QL\QueryList;
use Pctco\File\Markdown;
class Stackabuse{
    function __construct($config = []){
        $this->config = $config;
        $this->DM = new DatabaseManage;
        $this->tools = new Tools();
        $this->model = new Article;
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
     ** 收集列表数据
     *? @date 22/07/21 16:20
     */
    public function s1(){
        
        // 当前需要采集数据的类目
        $config = $this->config['model']['stackabuse'];
        $category = $this->tools->MinTimers($config['category']);
        try {
            $request_url = str_replace(['{$category}','{$page}'],[$category['name'],$category['page']],$config['request']);

            $client = new \GuzzleHttp\Client();

            $guzzle_config = [
                'timeout' => 20
            ];

            if ($config['proxy']['status'] === 1) {
                $guzzle_config = $this->tools->guzzle($guzzle_config);
            }

            $proxy = 
            $client->request('GET',$request_url,$guzzle_config);

            if ($proxy->getStatusCode() == 200) {
                $html = $proxy->getBody()->getContents();
            }else{
                $proxy = false;
            }
        } catch (\Throwable $th) {
            $proxy = false;
        }

        if ($proxy === false) {
            return __CLASS__.'\\'.__FUNCTION__.' category('.$category['name'].') GuzzleHttp request html data error '.date('H:i:s');
        }

        $result = 
        QueryList::html($html)
        ->rules([
            'title' =>  ['.grid >div >.flex >.flex-1 >.flex-1 >a.block >h3.mt-2','text','',function($title){
                return $title;
            }],
            'url' =>  ['.grid >div >.flex >.flex-1 >.flex-1 >a.block','href','',function($url){
                return $url;
            }]
        ])
        ->query()
        ->getData()
        ->all();

        try {
            foreach ($result as $v) {
                $is = 
                $this->model
                ->where([
                    'domain'  =>  $config['domain'],
                    'path'  =>  $v['url']
                ])
                ->count();
    
                if ($is === 0) {
                    $id = 
                    $this->model
                    ->insertGetId([
                        'menu'   =>  $category['menu_id'],
                        'source'    =>  $config['source'],
                        'domain'    =>  $config['domain'],
                        'path'   =>  $v['url'],
                        'lang'  =>  $config['lang'],
                        'status'    =>  2,
                        'atime' =>  time()
                    ]);

                    $this->model->cache([
                        'sid' => Skip::en('article',$id),
                        'handle'    =>  [
                            'event' =>  'set'
                        ]
                    ]);
                }
            }
        } catch (ValidateException $e) {
            return '验证异常捕获：' .$e->getError();
        } catch (\Exception $e) {
            return '异常捕获：' .$e->getMessage();
        }

        if ($this->config['model']['stackabuse']['category'][$category['menu_id']]['page'] > 1) {
            $this->config['model']['stackabuse']['category'][$category['menu_id']]['page']--;
        }
        
        $this->config['model']['stackabuse']['category'][$category['menu_id']]['timers'] = time();

        $this->config['model']['stackabuse']['timers'] = time();

        file_put_contents($this->config['json']['path'],json_encode($this->config));

        return __CLASS__.'\\'.__FUNCTION__.' category('.$category['name'].') data('.count($result).') The API list data was stored successfully '.date('H:i:s');
    }
    /** 
     ** 收集文章内容信息
     */
    public function s2($id){
        $cache = 
        $this->model->cache([
            'sid' => Skip::en('article',$id),
            'handle'    =>  [
                'event' =>  'pull'
            ]
        ]);

        $config = $this->config['model']['stackabuse'];
        
        $menuKey = array_search($cache['menu'],array_column($this->config['menu'], 'id'));
        $menu = $this->config['menu'][$menuKey];

        $this->config['model']['stackabuse']['timers'] = time();
        file_put_contents($this->config['json']['path'],json_encode($this->config));
        try {
            $request_url = $cache['reprint'];

            $client = new \GuzzleHttp\Client();

            $guzzle_config = [
                'timeout' => 20
            ];

            if ($config['proxy']['status'] === 1) {
                $guzzle_config = $this->tools->guzzle($guzzle_config);
            }

            $proxy = 
            $client->request('GET',$request_url,$guzzle_config);

            if ($proxy->getStatusCode() == 200) {
                $html = $proxy->getBody()->getContents();
            }else{
                $proxy = false;
            }
        } catch (ValidateException $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 验证异常捕获：' .$e->getError().' '.date('H:i:s');
        } catch (\Exception $e) {
            $this->model
            ->where('id',$id)
            ->update([
                'status'    =>  4
            ]);
            return __CLASS__.'\\'.__FUNCTION__.' 异常捕获：' .$e->getMessage().' '.date('H:i:s');
        }

        if ($proxy === false) {
            return __CLASS__.'\\'.__FUNCTION__.' model(stackabuse) GuzzleHttp request html data error '.date('H:i:s');
        }
        
        $data = 
        QueryList::html($html)
        ->rules([
            'title' =>  ['main >.items-stretch >.w-full >.grid h1.text-3xl','text','',function($title){
                return $title;
            }],
            'des'   =>  ['meta[name=description]','content','',function($des){
                return $des;
            }],
            'content' =>  ['main >.items-stretch >.w-full >.grid >.col-span-14 >div >.content','html','-noscript -#ad-snigel-1 -#ad-snigel-2 -#ad-snigel-3 -#ad-snigel-4 -#ad-snigel-5 -#ad-snigel-6 -#ad-snigel-7 -#ad-snigel-8 -#ad-snigel-9 -#ad-lead-magnet',function($content){
                return $content;
            }]
        ])
        ->query()
        ->getData()
        ->all();

        $data[0]['kw'] = $menu['name'];

        $markdown_content = $this->markdown->html($data[0]['content']);

        $this->model->where('id',$id)->update([
            'title' =>  $data[0]['title'],
            'kw'    =>  $data[0]['kw'],
            'status'    =>  3
        ]);

        $this->model->cache([
            'sid' => Skip::en('article',$id),
            'handle'    =>  [
                'event' =>  'set'
            ],
            'data'   => [
                'des' => $data[0]['des'],
                'content_original'   => $markdown_content
            ]
        ]);

        return __CLASS__.'\\'.__FUNCTION__.' id('.$id.') menu('.$menu['name'].') Updating data successfully '.date('H:i:s');
    }
}
