<?php
namespace Pctco\Reptile\A1;
use app\model\Movie as ModelMovie;
use Pctco\Reptile\Tools;
use Pctco\Coding\Skip32\Skip;
use QL\QueryList;
use Pctco\Storage\App\Cover;
class Movie{
    function __construct($config){
        $this->config = $config;
        $this->tools = new Tools();
        $this->ModelMovie = new ModelMovie;
    }
    /** 
     ** 收集基础信息（列表页数据）
     * 主要收集信息：
     * 1. aid douban id
     * 2. tilte 影片名称
     * 3. score 评分
     */
    public function s1(){

        $proxy = $this->tools->proxy();

        if ($proxy['code'] !== 0) return $proxy['msg'];

        /** 
         ** 如果 handle data 中有数据先处理
         */
        if (!empty($this->config['movie']['handle']['data'])) {
            $handle = $this->config['movie']['handle'];
            $itme = $handle['data'][0];
            $classify = $handle['classify'];

            

            $find = 
            $this->ModelMovie
            ->partition('p'.$classify)
            ->field('id')
            ->where(['aid'=>$itme['id'],'source'=>1])
            ->find();

            if (empty($find)) {
                $id = 
                $this->ModelMovie
                ->partition('p'.$classify)
                ->insertGetId([
                    'aid'   =>  $itme['id'],
                    'title' =>  $itme['title'],
                    'score'  =>  $itme['rate'],
                    'source'    =>  1,
                    'classify'  =>  $classify,
                    'atime' =>  time()
                ]);

                $find = 
                $this->ModelMovie
                ->partition('p'.$classify)
                ->field('id')
                ->where('id',$id)
                ->find();
                
                $cover = new Cover($id,$itme['cover'],'movie','movie','cover');
                $cover->save();
            }

            unset($this->config['movie']['handle']['data'][0]);
            $this->config['movie']['handle']['data'] = array_values($this->config['movie']['handle']['data']);
            file_put_contents($this->config['json']['path'],json_encode($this->config));

            return __CLASS__.'\\'.__FUNCTION__.' model('.$handle['model'].') function('.$handle['function'].') data('.(count($handle['data']) - 1).') id = '.$find->id.' data successful '.date('H:i:s');

        }

        /** 
         ** 如果 handle data 中没有数据则在json中加入需要处理的数据
         */
        $model = $this->tools->MinTimers($this->config['movie']['model']);
        $modelKey = array_search($model['title'],array_column($this->config['movie']['model'],'title'));

        $modelTag = $this->tools->MinTimers($model['tag']);
        $modelTagKey = array_search($modelTag['name'],array_column($model['tag'],'name'));

        $model['query']['tag'] = $modelTag['name'];
        $model['query']['page_start'] = $modelTag['page_start'];

        try {
            $client = new \GuzzleHttp\Client();

            $guzzle['query'] = $model['query'];
            if ($proxy['ip'] !== '127.0.0.1') {
                $guzzle['proxy']['https'] = $proxy['ip'];
            }
            
            $result = 
            $client->request('get',$this->config['movie']['request']['s1'],$guzzle);

            if ($result->getStatusCode() == 200) {
                $result->getHeaderLine('application/json; charset=utf8');
                $result = json_decode($result->getBody(),true);
            }
        } catch (ValidateException $e) {
            // 这是进行验证异常捕获
            return __CLASS__.'\\'.__FUNCTION__.' 验证异常捕获: '.$e->getError().' '.date('H:i:s');
        } catch (\Exception $e) {
            // 这是进行异常捕获
            return __CLASS__.'\\'.__FUNCTION__.' 进行异常捕获: '.$e->getMessage().' '.date('H:i:s');
        }


        $this->config['movie']['model'][$modelKey]['timers'] = time();
        $this->config['movie']['model'][$modelKey]['tag'][$modelTagKey]['timers'] = time();
        
        // handle 处理器进行数据存储
        $this->config['movie']['handle'] = [
            'classify'  =>  $model['classify'],
            'model' =>  $model['query']['type'],
            'function'  =>  's1',
            'data'  =>  $result['subjects']
        ];

        // 更新相应的page_start页数
        $config['movie']['timers']['s1']['max'] = 0;
        foreach ($this->config['movie']['model'] as $k => $v) {
            if ($v['query']['type'] === $model['query']['type']) {
                $page_limit = $guzzle['query']['page_limit'];
                $page_start = $this->config['movie']['model'][$k]['tag'][$modelTagKey]['page_start'];
                if ($page_start > $page_limit) {
                    $this->config['movie']['model'][$k]['tag'][$modelTagKey]['page_start'] = $page_start - $page_limit;
                }else{
                    $config['movie']['timers']['s1']['active']++;
                    $this->config['movie']['model'][$k]['tag'][$modelTagKey]['page_start'] = 0;
                }
            }
            $config['movie']['timers']['s1']['max'] = $config['movie']['timers']['s1']['max'] + count($this->config['movie']['model'][$k]['tag']);
        }
        
        file_put_contents($this->config['json']['path'],json_encode($this->config));

        return __CLASS__.'\\'.__FUNCTION__.' model('.$model['query']['type'].') tag('.$model['query']['tag'].') page('.$model['query']['page_start'].') data('.count($result['subjects']).') api interface data storage success '.date('H:i:s');
    }
    /** 
     ** 收集详细信息
     */
    public function s2(){
        $timers = 
        $this->ModelMovie
        ->where('timers', 0)
        ->field('id,aid,title,classify')
        ->order('timers')
        ->find();

        $request_url = str_replace('{$doubanid}',$timers->aid,$this->config['movie']['request']['s2']);

        $result = 
        QueryList::get($request_url)
        ->rules([
            'title' =>  ['#content >h1 span:eq(0)','text'],
            // 年份
            'years' =>  ['#content >h1 span:eq(1)','text','',function($years){
                $years = rtrim(ltrim($years,'('),')');
                if (strlen($years) === 4) {
                    return [
                        // 年份
                        'years' =>  (int)$years,
                        // 年代
                        'era'   =>  substr($years,2,1).'0年代'
                    ];
                }
                return [
                    'years'  =>  0,
                    'era'   =>  '未知'
                ];
            }],
            // 评分
            'score' =>  ['#content >.grid-16-8 .article .indent .subjectwrap #interest_sectl .rating_wrap .ratings-on-weight .item .rating_per','text','',function($score){
                return array_filter(explode('%',$score));
            }],
            // 信息
            'info'  =>  ['#content >.grid-16-8 .article .indent .subjectwrap .subject #info','html','',function($info){
                $info = preg_replace("/<([a-z\/]+)[^>]*>/i",' ',$info);
                $array = explode(PHP_EOL,$info);

                $data = [];
                foreach ($array as $str) {
                    $gstr = explode(':',$str);

                    if (count($gstr) >= 2) {
                        $n = trim($gstr[0]);
                        $v = trim($gstr[1]);

                        switch ($n) {
                            case '类型': 
                                $data['types'] = [];
                                foreach (explode('/',$v) as $types) $data['types'][] = trim($types);
                                break;
                            case '制片国家/地区': 
                                $data['country'] = [];
                                foreach (explode('/',$v) as $country) $data['country'][] = trim($country);
                                break;
                            case '语言': 
                                $data['lang'] = [];
                                foreach (explode('/',$v) as $lang) $data['lang'][] = trim($lang);
                                break;
                            case '上映日期': 
                                $data['sdate'] = [];
                                foreach (explode('/',$v) as $sdate) {
                                    
                                    $sdate = trim($sdate);
                                    preg_match_all('/(?<=[(])[^()]+/',$sdate,$matches);

                                    $data['sdate'][] = [
                                        'date'  =>  preg_replace('/\(.*?\)/','',$sdate),
                                        'country' =>  $matches[0][0]
                                    ];
                                }
                                break;
                            case '首播': // 电视剧字段
                                $data['sdate'] = explode('/',$v);
                                break;
                            case '片长': 
                                $data['vl'] = [];
                                foreach (explode('/',$v) as $vl) $data['vl'][] = trim($vl);
                                break;
                            case '集数': // 电视剧字段
                                $data['vl']['episode'] = (int)$v;
                                break;
                            case '单集片长': // 电视剧字段
                                $data['vl']['minute'] = (int)$v;
                                break;
                            case '又名':
                                $data['aka'] = [];
                                $v = preg_replace('/\/(?=[^()]*\))/', '',$v);
                                foreach (explode('/',$v) as $aka) {
                                    $data['aka'][] = trim($aka);
                                }
                                $data['aka'] = implode('/',$data['aka']);
                                break;
                            case 'IMDb':
                                $data['imdb'] = $v;
                                break;
                        }
                    }
                }

                return $data;
            }],
            // 概括
            'content'   =>  ['#link-report >span','html','',function($summary){
                return $summary;
            }]
        ])
        ->range('#wrapper')
        ->query()
        ->getData()
        ->all();

        if (empty($result[0])) {
            $this->ModelMovie
            ->where('id',$timers->id)
            ->update([
                'timers' => time()
            ]);
            return false;
        }

        $result = $result[0];

        $result['original_title'] = trim(str_replace($timers->title,'',$result['title']));

        $find = 
        $this->ModelMovie
        ->partition('p'.$timers->classify)
        ->where(['id'=>$timers->id])
        ->update([
            'timers' => time()
        ]);

        $cache = $this->ModelMovie->cache([
            'sid' => Skip::en('movie',$timers->id),
            'handle' => [
                'event' => 'update'
            ],
            'data'  =>  [
                'original_title'    =>  $result['original_title'],
                'aka'   =>  empty($result['info']['aka'])?'':$result['info']['aka'],
                'imdb'  =>  empty($result['info']['imdb'])?'':$result['info']['imdb'],
                'types' =>  empty($result['info']['types'])?[]:$result['info']['types'],
                'country' =>  empty($result['info']['country'])?[]:$result['info']['country'],
                'years' =>  empty($result['years'])?[0,'未知']:[$result['years']['years'],$result['years']['era']],
                'lang'  =>  empty($result['info']['lang'])?[]:$result['info']['lang'],
                'sdate' =>  empty($result['info']['sdate'])?[]:$result['info']['sdate'],
                'vl'    =>  empty($result['info']['vl'])?[]:$result['info']['vl'],
                'content'   =>  empty($result['content'])?'':$result['content']
            ]
        ]);

        return __CLASS__.'\\'.__FUNCTION__.' model('.$cache['classify_data']['nlname'].') id('.$cache['id'].') update completed '.date('H:i:s');
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
