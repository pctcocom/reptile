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
        // timers 执行周期 time() + (3600*2)
        $this->interval_timers = time();
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

        try {
            $request_url = str_replace('{$tagged}',$groups['name'],$model['request']);
            $client = new \GuzzleHttp\Client();

            $guzzle_config = $this->tools->guzzle([
                'proxy'  => [
                    'get' => [
                        'where'  => [
                            'n5'    =>  'USA'
                        ],
                        'order' =>  'timers0'
                    ]
                ],
                'guzzle'    =>  [
                    'query'   =>  [
                        'tab'  =>  'Newest'
                    ],
                    'timeout' => 45
                ]
            ]);

            $proxy = 
            $client->request('GET',$request_url,$guzzle_config);

            if ($proxy->getStatusCode() == 200) {
                $html = $proxy->getBody()->getContents();
            }else{
                return __CLASS__.'\\'.__FUNCTION__.' GuzzleHttp：' .$proxy->getStatusCode();
            }
        }  catch (ValidateException $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 验证异常捕获：' .$e->getError();
        } catch (\Exception $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 异常捕获：' .$e->getMessage();
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
        
        try {
            foreach ($result as $v) {
                $is = 
                $this->GQuestions
                ->partition('p'.$groups['gid'])
                ->where([
                    'source'    =>  $model['source'],
                    'reprint'   =>  $v['url']
                ])
                ->count();
    
                if ($is === 0) {
                    $gqid = 
                    $this->GQuestions
                    ->partition('p'.$groups['gid'])
                    ->insertGetId([
                        'gid'   =>  $groups['gid'],
                        'source'    =>  $model['source'],
                        'reprint'  =>  $v['url'],
                        'status'    =>  8,
                        'atime' =>  time()
                    ]);

                    $this->GQuestions->cache([
                        'sid' => Skip::en('groups_questions',$gqid),
                        'gid' => $groups['gid'],
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
        
        $this->config['model']['stackoverflow']['timers'] = $this->interval_timers;

        $interval_timers_hours = $this->config['model']['stackoverflow']['groups'][$groups['gid']]['interval_timers_hours'];
        if ($interval_timers_hours !== 0) {
            $interval_timers_hours = 3600*$interval_timers_hours;
        }
        $this->config['model']['stackoverflow']['groups'][$groups['gid']]['timers'] = time() + $interval_timers_hours;

        file_put_contents($this->config['json']['path'],json_encode($this->config));

        return __CLASS__.'\\'.__FUNCTION__.' model(stackoverflow) groups('.$groups['name'].') data('.count($result).') api interface data storage success '.date('H:i:s');
    }
    /** 
     ** 收集问答信息
     */
    public function s2($timers){
        
        try {
            $request_url = $this->GQuestions->getAttr['source'][$timers->source]['domain'].$timers->reprint;

            $client = new \GuzzleHttp\Client();

            $guzzle_config = $this->tools->guzzle([
                'proxy'  => [
                    'get' => [
                        'where'  => [
                            'n5'    =>  'USA'
                        ],
                       'order' =>  'timers0'
                    ]
                ],
                'guzzle'    =>  [
                    'timeout' => 45
                ]
            ]);

            $proxy = 
            $client->request('GET',$request_url,$guzzle_config);

            if ($proxy->getStatusCode() == 200) {
                $html = $proxy->getBody()->getContents();
            }else{
                return __CLASS__.'\\'.__FUNCTION__.' GuzzleHttp：' .$proxy->getStatusCode();
            }
        }  catch (ValidateException $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 验证异常捕获：' .$e->getError();
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $this->GQuestions
                ->where([
                    'id'    =>  $timers->id
                ])
                ->update([
                    'status'    =>  10,
                    'utime' =>  time()
                ]);
            }
            return __CLASS__.'\\'.__FUNCTION__.' 异常捕获：' .$e->getMessage();
        }

        $title = QueryList::html($html)->find('#question-header a.question-hyperlink')->html();

        $content = QueryList::html($html)->find('.postcell .s-prose')->html();
        $content = $this->markdown->html($content,[
            'tags' => [
                // 是否去除 HTML 标签
                'strip_tags' => true
            ],
            'table' =>  [
                // div table 转 Markdown tables
                'converter' =>  true
            ]
        ]);
        
        $keywords = QueryList::html($html)->find('.mt24 .post-taglist .d-flex a')->texts()->all();
        
        $author_atime = QueryList::html($html)->find('.mb0 .post-signature.owner .user-info .user-action-time .relativetime')->attrs('title')->all();

        $author_my = QueryList::html($html)->find('.mb0 .post-signature.owner .user-info .user-details a')->attrs('href')->all();

        preg_match_all('/\/users\/(\d+)\/.*?/',$author_my[0],$matches);
        $author_id = $matches[1][0];

        $this->GQuestions
        ->where([
            'id'    =>  $timers->id
        ])
        ->update([
            'title' =>  $title,
            'kw'    =>  ','.implode(',',$keywords).',',
            'status'    =>  7,
            'utime' =>  time()
        ]);

        $cache = $this->GQuestions->cache([
            'sid' => Skip::en('groups_questions',$timers->id),
            'gid' => $timers->gid,
            'handle'    =>  [
                'event' =>  'set'
            ],
            'data'  =>  [
                'author'    =>  [
                    'my'    =>  $author_my[0],
                    'id'    =>  $author_id,
                    'date'  =>  [
                        'time'  =>  $author_atime[0],
                        'date'  =>  strtotime($author_atime[0])
                    ]
                ],
                'original_title'  =>  $title,
                'content' =>  $content,
                'original_content'  =>  $content
            ]  
        ]);

        return __CLASS__.'\\'.__FUNCTION__.' model(stackoverflow) id = '.$timers->id.' groups('.$cache['gid_data']['name'].')  '.date('H:i:s');
    }
    /** 
     ** 收集一级评论
     */
    public function s3(){
        try {
            $request_url = 'https://stackoverflow.com/questions/12717112/how-to-display-woocommerce-category-image';
            $client = new \GuzzleHttp\Client();
            $proxy = 
            $client->request('GET',$request_url,[
                'query'   =>  [
                    'tab'  =>  'Newest'
                ],
                // 'proxy' =>  [
                //     'https'  => '154.223.167.57:8888',
                // ],
                'timeout' => 20
            ]);

            if ($proxy->getStatusCode() == 200) {
                $html = $proxy->getBody()->getContents();
            }else{
                return __CLASS__.'\\'.__FUNCTION__.' GuzzleHttp：' .$proxy->getStatusCode();
            }
        }  catch (ValidateException $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 验证异常捕获：' .$e->getError();
        } catch (\Exception $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 异常捕获：' .$e->getMessage();
        }

        /** 
         ** 正常评论
         *? @date 22/09/29 16:39
         */
        $comment = 
        QueryList::html($html)
        ->rules([
            // 评论id
            'id' =>  ['#answers .answer','data-answerid','',function($id){
                return (int)$id;
            }],
            'pid'   =>  ['#answers .answer','data-answerid','',function($pid){
                return 0;
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

        /** 
         ** 短评论（问答内容下方的评论）
         *? @date 22/09/29 16:39
         */

        $short_comment = 
        QueryList::html($html)
        ->rules([
            // 评论id
            'id' =>  ['#comments-12717112 .comments-list .comment','data-comment-id','',function($id){
                return (int)$id;
            }],
            'pid'   =>  ['#comments-12717112 .comments-list .comment','data-comment-id','',function($pid){
                return 0;
            }],
            // 判断是否采纳
            'votecell' =>  ['#comments-12717112 .comments-list .comment','data-comment-id','',function($votecell){
                return false;
            }],
            // 评论内容
            'content' =>  ['#comments-12717112 .comments-list .comment .comment-text .comment-body .comment-copy','html','',function($content){
                return $this->markdown->html($content);
            }]
        ])
        ->query()
        ->getData()
        ->all();

        dump($short_comment);
        dump($comment);

        return $comment;
    }
    /** 
     ** 收集二级评论
     */
    public function s4(){
        try {
            $request_url = 'https://stackoverflow.com/questions/67993442/hhh90001006-missing-cachedefault-update-timestamps-region-was-created-on-the';
            $client = new \GuzzleHttp\Client();
            $proxy = 
            $client->request('GET',$request_url,[
                'query'   =>  [
                    'tab'  =>  'Newest'
                ],
                // 'proxy' =>  [
                //     'https'  => '154.223.167.57:8888',
                // ],
                'timeout' => 20
            ]);

            if ($proxy->getStatusCode() == 200) {
                $html = $proxy->getBody()->getContents();
            }else{
                return __CLASS__.'\\'.__FUNCTION__.' GuzzleHttp：' .$proxy->getStatusCode();
            }
        }  catch (ValidateException $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 验证异常捕获：' .$e->getError();
        } catch (\Exception $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 异常捕获：' .$e->getMessage();
        }

        $this->s4_pid = 100;
        // 一级评论id
        $this->s4_comments_id = '68068523';

        $comment = 
        QueryList::html($html)
        ->rules([
            'user'  =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment .d-inline-flex a','href','',function($user){
                preg_match_all('/\/users\/(\d+)\/.*?/',$user,$matches);
                return [
                    'my'    =>  $user,
                    'id'    =>  $matches[1][0]
                ];
            }],
            'id'    =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment','data-comment-id','',function($id){
                return $id;
            }],
            'pid'   =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment','data-comment-id','',function($pid){
                return $this->s4_pid;
            }],
            'comment'   =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment .comment-copy','html','',function($comment){
                return $this->markdown->html($comment);
            }]
        ])
        ->query()
        ->getData()
        ->all();

        return $comment;
    }
    /** 
     ** 收集短评数据
     */
    public function s5(){

    }
    
}
