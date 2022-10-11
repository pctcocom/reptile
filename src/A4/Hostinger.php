<?php
namespace Pctco\Reptile\A4;
use app\model\DatabaseManage;
use app\model\Article;
use Pctco\Reptile\Tools;
use Pctco\Coding\Skip32\Skip;
use QL\QueryList;
use Pctco\File\Markdown;
class Hostinger{
    function __construct($config = []){
        $this->config = $config;
        $this->DM = new DatabaseManage;
        $this->tools = new Tools();
        $this->model = new Article;
        // timers 执行周期
        $this->interval_timers = time() + (3600*2);
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
        $config = $this->config['model']['hostinger'];
        $category = $this->tools->MinTimers($config['category']);
        try {
            $request_url = str_replace(['{$category}','{$page}'],[$category['name'],$category['page']],$config['request']);

            $client = new \GuzzleHttp\Client();

            $guzzle_config = [
                'timeout' => 20
            ];

            if ($config['proxy']['status'] === 1) {
                $guzzle_config = $this->tools->guzzle([
                    'proxy'  => [
                        'get' => [
                            'where'  => [
                                'n5'    =>  'USA'
                            ]
                        ]
                    ],
                    'guzzle'    =>  $guzzle_config
                ]);
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
            'title' =>  ['.tutorials-list__cards-container >.tutorials-list__card >.d-flex >.d-flex >span >h4','text','',function($title){
                return $title;
            }],
            'url' =>  ['.tutorials-list__cards-container >.tutorials-list__card','href','',function($url){
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

        if ($this->config['model']['hostinger']['category'][$category['menu_id']]['page'] > 1) {
            $this->config['model']['hostinger']['category'][$category['menu_id']]['page']--;
        }
        
        $this->config['model']['hostinger']['category'][$category['menu_id']]['timers'] = time();

        $this->config['model']['hostinger']['timers'] = $this->interval_timers;

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

        $config = $this->config['model']['hostinger'];

        $this->config['model']['hostinger']['timers'] = $this->interval_timers;
        file_put_contents($this->config['json']['path'],json_encode($this->config));
        try {
            $request_url = $cache['reprint'];

            $client = new \GuzzleHttp\Client();

            $guzzle_config = [
                'timeout' => 20
            ];

            if ($config['proxy']['status'] === 1) {
                $guzzle_config = $this->tools->guzzle([
                    'proxy'  => [
                        'get' => [
                            'where'  => [
                                'n5'    =>  'USA'
                            ]
                        ]
                    ],
                    'guzzle'    =>  $guzzle_config
                ]);
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
            return __CLASS__.'\\'.__FUNCTION__.' 异常捕获：' .$e->getMessage().' '.date('H:i:s');
        }

        if ($proxy === false) {
            return __CLASS__.'\\'.__FUNCTION__.' model(hostinger) GuzzleHttp request html data error '.date('H:i:s');
        }
        
        $data = 
        QueryList::html($html)
        ->rules([
            'title' =>  ['#post >.post-content >h1','text','',function($title){
                return $title;
            }],
            'des'   =>  ['meta[name=description]','content','',function($des){
                return $des;
            }],
            'content' =>  ['#post >.post-content','html','->p.has-text-align-center ->#ez-toc-container ->.label ->h1 ->.social-container ->script[type=rocketlazyloadscript] -noscript -span.ez-toc-section -span.ez-toc-section-end ->.helpful ->.protip:last ->#the-author-section',function($content){
                
                /** 
                 ** img 简化
                 *? @date 22/08/30 18:24 
                 */
                if(preg_match_all("/<img.*?data-lazy-src=\"(.*?)\".*?>/ism", $content, $img)){
                    $imgs = [];
                    foreach ($img[1] as $img_url) {
                        if (substr($img_url,0,4) !== 'http') {
                            $img_url = rtrim($this->config['model']['hostinger']['domain'],'/').$img_url;
                        }
                        $imgs[] = '<img src="'.$img_url.'"><br>';
                    }
                    $content = str_replace($img[0],$imgs,$content);
                }
                
                /** 
                 ** 将 a = 图片链接的去除
                 *? @date 22/08/30 18:24
                 */
                if(preg_match_all("/<a\s+href=\"([^.]+.(?:gif|jpg|jpeg|png|webp))\"[^><]+>(.*?)<\/a>/ism", $content, $a_img_link)){
                    $content = str_replace($a_img_link[0],$a_img_link[2],$content);
                }
                
                /** 
                 ** 将 a = 链接补全
                 *? @date 22/09/01 23:26
                 */
                if (preg_match_all("/<a.*?href=\"(.*?)\".*?>.*?<\/a>/ism", $content, $a_link)) {
                    $relative_link = [];
                    $completion_link = [];
                    foreach ($a_link[1] as $a_link_v) {
                        if (substr($a_link_v,0,4) !== 'http') {
                            $relative_link[] = $a_link_v;
                            $completion_link[] = rtrim($this->config['model']['hostinger']['domain'],'/').$a_link_v;
                        }
                    }
                    $content = str_replace($relative_link,$completion_link,$content);
                }

                /** 
                 ** 去除或替换 div
                 *? @date 22/08/30 18:23
                 */
                if(preg_match_all("/<div.*?class=\"(wp-block-image|d-flex justify-content-center|protip)\".*?>(.*?)<\/div>/ism", $content, $div_label)){
                    foreach ($div_label[1] as $dlk => $dlv) {
                        if ($dlv === 'protip') {
                            $div_label[2][$dlk] = '<blockquote>'.$div_label[2][$dlk].'</blockquote>';
                        }
                    }
                    $content = str_replace($div_label[0],$div_label[2],$content);
                }

                if(preg_match_all("/<div>(.*?)<\/div>/ism", $content, $div2_label)){
                    $content = str_replace($div2_label[0],$div2_label[1],$content);
                }

                if(preg_match_all("/<span.*?>(.*?)<\/span>/ism", $content, $span_label)){
                    $content = str_replace($span_label[0],$span_label[1],$content);
                }
                
                /** 
                 ** 去除 figure
                 *? @date 22/08/30 18:23
                 */
                if(preg_match_all("/<figure.*?class=\"(wp-block-table|aligncenter|aligncenter size-large|aligncenter size-full)\".*?>(.*?)<\/figure>/ism", $content, $figure_label)){
                    $content = str_replace($figure_label[0],$figure_label[2],$content);
                }

                /** 
                 ** 去除 pre
                 *? @date 22/09/01 13:46
                 */
                if(preg_match_all("/<pre.*?class=\"(wp-block-preformatted)\".*?>(.*?)<\/pre>/ism", $content, $figure_label)){
                    $content = str_replace($figure_label[0],$figure_label[2],$content);
                }
                return $content;
            }]
        ])
        ->query()
        ->getData()
        ->all();

        $keywords = QueryList::html($html)->find('#post >.post-content >.label >.category a')->texts()->all();

        $data[0]['kw'] = implode(',',$keywords);

        $markdown_content = $this->markdown->html($data[0]['content'],[
            'tags' => [
                // 是否去除 HTML 标签
                'strip_tags' => true
            ],
            'table' =>  [
                // div table 转 Markdown tables
                'converter' =>  true
            ]
        ]);

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

        return __CLASS__.'\\'.__FUNCTION__.' id('.$id.') Updating data successfully '.date('H:i:s');
    }
}
