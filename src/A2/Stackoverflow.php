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
            if ($e->getCode() === 0) {
                $this->GQuestions
                ->where([
                    'id'    =>  $timers->id
                ])
                ->update([
                    'status'    =>  11,
                    'utime' =>  time()
                ]);
            }
            return __CLASS__.'\\'.__FUNCTION__.' 异常捕获（'.$e->getCode().'）：' .$e->getMessage();
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

        $author_id = null;
        if (!empty($author_my)) {
            preg_match_all('/\/users\/(\d+)\/.*?/',$author_my[0],$matches);
            $author_id = $matches[1][0];
        }

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
    public function s3($timers){
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
            if ($e->getCode() === 0) {
                $this->GQuestions
                ->where([
                    'id'    =>  $timers->id
                ])
                ->update([
                    'status'    =>  11,
                    'utime' =>  time()
                ]);
            }
            return __CLASS__.'\\'.__FUNCTION__.' 异常捕获：' .$e->getMessage();
        }

        /** 
         ** 获取帖子id
         *? @date 22/10/08 00:40
         */
        preg_match_all('/https:\/\/stackoverflow.com\/questions\/(\d+)\/.*?/',$request_url,$matches);
        $this->s3_post_id = $matches[1][0];

        /** 
         ** 正常评论
         *? @date 22/09/29 16:39
         */
        $comment = 
        QueryList::html($html)
        ->rules([
            'user'  =>  ['#answers .answer .post-layout .answercell .user-info [itemprop=author] >a','href','',function($user){
                preg_match_all('/\/users\/(\d+)\/.*?/',$user,$matches);
                if (empty($matches[1][0])) {
                    return [
                        'my'    =>  $user,
                        'id'    =>  0
                    ];
                }
                return [
                    'my'    =>  $user,
                    'id'    =>  $matches[1][0]
                ];
            }],
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
            // 是否翻译 1 = 是 | 0 = 没翻译
            'translate'   =>  ['#answers .answer','data-answerid','',function($translate){
                return 0;
            }],
            // 是否收集了二级评论 1 = 是 | 0 = 没有
            'secondary-comments'   =>  ['#answers .answer','data-answerid','',function($translate){
                return 0;
            }],
            // 评论内容
            'content' =>  ['#answers .answer .post-layout .answercell .s-prose','html','',function($content){
                return $this->markdown->html($content,[
                    'tags' => [
                        // 是否去除 HTML 标签
                        'strip_tags' => true
                    ],
                    'table' =>  [
                        // div table 转 Markdown tables
                        'converter' =>  true
                    ]
                ]);
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
            'user'  =>  ['#comments-'.$this->s3_post_id.' .comments-list .comment .comment-user','href','',function($user){
                preg_match_all('/\/users\/(\d+)\/.*?/',$user,$matches);
                return [
                    'my'    =>  $user,
                    'id'    =>  $matches[1][0]
                ];
            }],
            // 评论id
            'id' =>  ['#comments-'.$this->s3_post_id.' .comments-list .comment','data-comment-id','',function($id){
                return (int)$id;
            }],
            'pid'   =>  ['#comments-'.$this->s3_post_id.' .comments-list .comment','data-comment-id','',function($pid){
                return 0;
            }],
            // 判断是否采纳
            'votecell' =>  ['#comments-'.$this->s3_post_id.' .comments-list .comment','data-comment-id','',function($votecell){
                return false;
            }],
            // 是否翻译 1 = 是 | 0 = 没翻译
            'translate'   =>  ['#comments-'.$this->s3_post_id.' .comments-list .comment','data-comment-id','',function($translate){
                return 0;
            }],
            // 是否收集了二级评论 1 = 是 | 0 = 没有
            'secondary-comments'   =>  ['#comments-'.$this->s3_post_id.' .comments-list .comment','data-comment-id','',function($translate){
                return 1;
            }],
            // 评论内容
            'content' =>  ['#comments-'.$this->s3_post_id.' .comments-list .comment .comment-text .comment-body .comment-copy','html','',function($content){
                return $this->markdown->html($content,[
                    'tags' => [
                        // 是否去除 HTML 标签
                        'strip_tags' => true
                    ],
                    'table' =>  [
                        // div table 转 Markdown tables
                        'converter' =>  true
                    ]
                ]);
            }]
        ])
        ->query()
        ->getData()
        ->all();
        
        $page_html = false;
        $status = 7;
        
        foreach ($comment as $k => $c) {
            if ($c['votecell'] === true) {
                $page_html = $html;
                $status = 6;
                break;
            }
        }

        $all_comment = array_merge($short_comment,$comment);

        $this->GQuestions
        ->where([
            'id'    =>  $timers->id
        ])
        ->update([
            // 如果已经采纳则状态 = 3
            'status'    =>  $status,
            'utime' =>  time(),
            // 获取评论 如果没有采纳答案则 86400*3 3天后在继续
            'timers'    =>  time() + 86400*3
        ]);

        $cache = $this->GQuestions->cache([
            'sid' => Skip::en('groups_questions',$timers->id),
            'gid' => $timers->gid,
            'handle'    =>  [
                'event' =>  'set'
            ],
            'data'  =>  [
                'comment_data'  =>  $all_comment,
                'page_html'  =>  $page_html
            ]
        ]);

        return __CLASS__.'\\'.__FUNCTION__.' model(stackoverflow) id = '.$timers->id.' groups('.$cache['gid_data']['name'].') comment('.$cache['comment'].') '.date('H:i:s');
    }
    /** 
     ** 收集二级评论
     * 必须是已采纳的帖子才会进入s4处理二级评论
     */
    public function s4($timers){
        $cache = $this->GQuestions->cache([
            'sid' => Skip::en('groups_questions',$timers->id),
            'gid' => $timers->gid,
            'handle'    =>  [
                'event' =>  'pull',
                'field' =>  ['comment_data','page_html','gid_data']
            ]
        ]);

        // 获取想要处理二级评论的数组
        $commentsKey = array_search(0,array_column($cache['comment_data'], 'secondary-comments'));

        if ($commentsKey === false) {
            $this->GQuestions
            ->where([
                'id'    =>  $timers->id
            ])
            ->update([
                'status'    =>  5,
                'utime' =>  time()
            ]);

            $this->GQuestions->cache([
                'sid' => Skip::en('groups_questions',$timers->id),
                'gid' => $timers->gid,
                'handle'    =>  [
                    'event' =>  'set',
                ],
                'data'  =>  [
                    'page_html' =>  's4 清空本字段中的内容...'
                ]
            ]);

            return __CLASS__.'\\'.__FUNCTION__.' model(stackoverflow) id = '.$timers->id.' groups('.$cache['gid_data']['name'].') 二级评论处理完毕 '.date('H:i:s');
        }

        $comments = $cache['comment_data'][$commentsKey];

        // 一级评论id
        $this->s4_comments_id = $comments['id'];

        $comment = 
        QueryList::html($cache['page_html'])
        ->rules([
            'user'  =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment .d-inline-flex a','href','',function($user){
                preg_match_all('/\/users\/(\d+)\/.*?/',$user,$matches);
                return [
                    'my'    =>  $user,
                    'id'    =>  $matches[1][0]
                ];
            }],
            'id'    =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment','data-comment-id','',function($id){
                return (int)$id;
            }],
            'pid'   =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment','data-comment-id','',function($pid){
                return $this->s4_comments_id;
            }],
            // 判断是否采纳
            'votecell' =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment','data-comment-id','',function($votecell){
                return false;
            }],
            // 是否翻译 1 = 是 | 0 = 没翻译
            'translate'   =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment','data-comment-id','',function($translate){
                return 0;
            }],
            // 是否收集了二级评论 1 = 是 | 0 = 没有
            'secondary-comments'   =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment','data-comment-id','',function($translate){
                return 1;
            }],
            'content'   =>  ['#answers .answer .post-layout .post-layout--right #comments-'.$this->s4_comments_id.' .comments-list .comment .comment-copy','html','',function($content){
                return $this->markdown->html($content,[
                    'tags' => [
                        // 是否去除 HTML 标签
                        'strip_tags' => true
                    ],
                    'table' =>  [
                        // div table 转 Markdown tables
                        'converter' =>  true
                    ]
                ]);
            }]
        ])
        ->query()
        ->getData()
        ->all();

        /** 
         ** 没有子评论
         *? @date 22/10/08 16:04
         */
        $cache['comment_data'][$commentsKey]['secondary-comments'] = 1;
        if (!empty($comment)) {
            $cache['comment_data'] = array_merge($cache['comment_data'],$comment);
        }

        $cache = $this->GQuestions->cache([
            'sid' => Skip::en('groups_questions',$timers->id),
            'gid' => $timers->gid,
            'handle'    =>  [
                'event' =>  'set'
            ],
            'data'  =>  [
                'comment_data'  =>  $cache['comment_data']
            ]
        ]);

        return __CLASS__.'\\'.__FUNCTION__.' model(stackoverflow) id = '.$timers->id.' groups('.$cache['gid_data']['name'].') 正在处理二级评论 '.date('H:i:s');
    }
}
