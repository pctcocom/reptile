<?php
namespace Pctco\Reptile\A2;
use app\model\GroupsQuestions;
use app\model\DatabaseManage;
use Pctco\Reptile\Tools;
use Pctco\Coding\Skip32\Skip;
use QL\QueryList;
use Pctco\File\Markdown;
class Stackoverflow{
    function __construct($config = []){
        $this->config = $config;
        $this->GQuestions = new GroupsQuestions;
        $this->DM = new DatabaseManage;
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
     ** 收集列表数据
     *? @date 22/07/21 16:20
     */
    public function s1(){
        $model =  $this->config['model']['stackoverflow'];
        // 当前需要采集数据的小组
        $groups = $this->tools->MinTimers($model['groups']);

        // 检测表分区是否存在 不存在则创建
        $DBPartition = $this->DM->DBPartition('groups_questions',$groups['gid']);
        
        

        try {
            $request_url = str_replace('{$tagged}',$groups['name'],$model['request']);
            $client = new \GuzzleHttp\Client();
            $proxy = 
            $client->request('GET',$request_url,[
                'query'   =>  [
                    'tab'  =>  'Newest'
                ],
                'proxy' =>  [
                    'https'  => '154.223.167.57:8888',
                ],
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
            return __CLASS__.'\\'.__FUNCTION__.' model(stackoverflow) groups('.$groups['name'].') GuzzleHttp request html data error '.date('H:i:s');
        }
        
        $result = 
        QueryList::html($html)
        ->rules([
            'aid' =>  ['#questions .s-post-summary','data-post-id','',function($aid){
                return $aid;
            }],
            'url' =>  ['#questions .s-post-summary .s-post-summary--content .s-post-summary--content-title a','href','',function($url){
                return $url;
            }]
        ])
        ->query()
        ->getData()
        ->all();
        return $result;

        try {
            foreach ($result as $v) {
                $is = 
                $this->GQuestions
                ->partition('p'.$groups['gid'])
                ->where('aid',$v['aid'])
                ->count();
    
                if ($is === 0) {
                    $gqid = 
                    $this->GQuestions
                    ->partition('p'.$groups['gid'])
                    ->insertGetId([
                        'aid'   =>  $v['aid'],
                        'gid'   =>  $groups['gid'],
                        'source'    =>  $model['source'],
                        'status'    =>  2
                    ]);

                    $this->GQuestions->cache([
                        'sid' => Skip::en('groups_questions',$gqid),
                        'gid' => $groups['gid'],
                        'handle'    =>  [
                            'event' =>  'set'
                        ],
                        'data'  =>  [
                            'reprint'   =>  $v['url']
                        ]
                    ]);
                }
            }
        } catch (ValidateException $e) {
            return '验证异常捕获：' .$e->getError();
        } catch (\Exception $e) {
            return '异常捕获：' .$e->getMessage();
        }
        
        $this->config['model']['stackoverflow']['groups'][$groups['gid']]['timers'] = time();
        file_put_contents($this->config['json']['path'],json_encode($this->config));

        return __CLASS__.'\\'.__FUNCTION__.' model(stackoverflow) groups('.$groups['name'].') data('.count($result).') api interface data storage success '.date('H:i:s');
    }
    /** 
     ** 收集问答信息
     */
    public function s2(){
        $request_url = 'https://stackoverflow.com/questions/67993442/hhh90001006-missing-cachedefault-update-timestamps-region-was-created-on-the';

        $html = QueryList::get($request_url)->getHtml();

        $content = QueryList::html($html)->find('.postcell .s-prose')->html();
        $keywords = QueryList::html($html)->find('.mt24 .post-taglist .d-flex a')->texts()->all();

        $content = $this->markdown->html($content);
        
        $comment = 
        QueryList::html($html)
        ->rules([
            // 评论id
            'id' =>  ['#answers .answer','data-answerid','',function($id){
                return $id;
            }],
            // 判断是否采纳
            'votecell' =>  ['#answers .answer .post-layout .post-layout--left .js-voting-container .js-accepted-answer-indicator','class','',function($votecell){
                // return 结果为 true 说明是采纳答案
                return strpos($votecell,'d-none') === false;
            }],
            // 评论内容
            'content' =>  ['#answers .answer .post-layout .answercell .s-prose','html','',function($content){
                return $this->markdown->html($content);
            }]
        ])
        ->query()
        ->getData()
        ->all();

        return $comment;
    }
    /** 
     ** 收集演职员数据
     */
    public function s3(){
        
    }
    /** 
     ** 下载剧照
     */
    public function s4(){

    }
    /** 
     ** 收集短评数据
     */
    public function s5(){

    }
    
}
