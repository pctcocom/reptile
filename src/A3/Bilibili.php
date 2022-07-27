<?php
namespace Pctco\Reptile\A3;
use app\model\Users;
use Pctco\Reptile\Tools;
class Bilibili{
    function __construct($config = []){
        $this->config = $config;
        $this->users = new Users;
        $this->tools = new Tools();
    }
    /** 
     ** 收集搜索列表数据
     *? @date 22/07/21 16:20
     */
    public function s1(){


        $model = $this->config['model']['bilibili'];
        
        

        /** 
         ** 如果 handle data 中没有数据则在json中加入需要处理的数据
         */
        $tags = $this->tools->MinTimers($model['tags']);
        $tagsKey = array_search($tags['name'],array_column($model['tags'],'name'));
        try {
            $request_url = $model['request']['s1']['url'];

            $model['query']['s1']['keyword'] = $tags['name'];

            $client = new \GuzzleHttp\Client();
            $proxy = 
            $client->request('GET',$request_url,[
                'query'   =>  $model['query']['s1'],
                'timeout' => 20
            ]);

            if ($proxy->getStatusCode() == 200) {
                $proxy = json_decode($proxy->getBody()->getContents(),true);
                $result = $proxy['data']['result'];
            }else{
                $result = false;
            }
        } catch (\Throwable $th) {
            $result = false;
        }

        if ($result === false) {
            return __CLASS__.'\\'.__FUNCTION__.' model(bilibili) tags('.$tags['name'].') GuzzleHttp request html data error '.date('H:i:s');
        }

        $handleData = [];
        foreach ($result as $s1) {
            $is = 
            $this->users
            ->where('original_nickname',$s1['uname'])
            ->count();

            if ($is === 0) {
                $handleData[] = [
                    'mid'   =>  $s1['mid'],
                    'uname' =>  $s1['uname'],
                    'upic'  =>  $s1['upic'],
                    'gender'    =>  $s1['gender'],
                    'preference'  =>  $tags['preference']
                ];
            }
        }

        $this->config['handle'] = [
            'source'    =>  $model['source'],
            'model' =>  'bilibili',
            'data'  =>  $handleData
        ];

        if ($this->config['model']['bilibili']['query']['s1']['page'] > 1) {
            $this->config['model']['bilibili']['query']['s1']['page']--;
        }

        $this->config['model']['bilibili']['request']['s1']['timers'] = time();

        $this->config['model']['bilibili']['tags'][$tagsKey]['timers'] = time();

        $this->config['model']['bilibili']['timers'] = time();

        file_put_contents($this->config['json']['path'],json_encode($this->config));
        
        return __CLASS__.'\\'.__FUNCTION__.' model(bilibili) tags('.$tags['name'].') data('.count($handleData).') api interface data storage success '.date('H:i:s');
    }
    /** 
     ** 收集 TA的粉丝和TA的关注 page 页数 rules
     *  @param String $options['uid'] 字段 uid
     *  @param String $options['aid'] 字段 aid
     *  @param Array $options['rules'] 字段 rules
     *  @param Array $options['preference'] 字段 preference
     */
    public function s2($options){

        $model = $this->config['model']['bilibili'];

        $rules = $this->tools->MinTimers($options['rules']);

        $rulesKey = array_search($rules['request'],array_column($options['rules'],'request'));

        $rules = $options['rules'][$rulesKey];
        
        try {
            $request_url = $model['request'][$rules['function']][$rules['request']]['url'];

            $client = new \GuzzleHttp\Client();
            $proxy = 
            $client->request('GET',$request_url,[
                'query'   =>  [
                    'vmid'  =>  $options['aid'],
                    // 第n页 (bilibili 系统限制只能访问前五页)
                    'pn'    =>  $rules['page'],
                    // 每页显示n条 (bilibili 系统限制最大只能索取50条)
                    'ps'    =>  50,
                    'order' =>  'desc'
                ],
                'timeout' => 20
            ]);

            if ($proxy->getStatusCode() == 200) {
                $proxy = json_decode($proxy->getBody()->getContents(),true);
                $result = true;
                if ($proxy['code'] === 22115) {
                    $result = false;
                }

                if ($options['rules'][$rulesKey]['page'] > 1) $options['rules'][$rulesKey]['page']--;

                $options['rules'][$rulesKey]['timers'] = time();

                $this->users
                ->where('uid',$options['uid'])
                ->update([
                    'rules' =>  serialize($options['rules'])
                ]);

                if ($result) {
                    $handleData = [];
                    foreach ($proxy['data']['list'] as $s1) {
                        $is = 
                        $this->users
                        ->where('original_nickname',$s1['uname'])
                        ->count();

                        if ($is === 0) {
                            $handleData[] = [
                                'mid'   =>  $s1['mid'],
                                'uname' =>  $s1['uname'],
                                'upic'  =>  $s1['face'],
                                'gender'    =>  3,
                                'preference'  =>  $options['preference']
                            ];
                        }
                    }

                    $this->config['handle'] = [
                        'source'    =>  $model['source'],
                        'model' =>  'bilibili',
                        'data'  =>  $handleData
                    ];

                    file_put_contents($this->config['json']['path'],json_encode($this->config));
                }else{
                    // 用户已设置隐私，无法查看
                    return __CLASS__.'\\'.__FUNCTION__.' model(bilibili) uid('.$options['uid'].') GuzzleHttp request json data error('.$proxy['message'].') '.date('H:i:s');
                }
            }else{
                return __CLASS__.'\\'.__FUNCTION__.' model(bilibili) GuzzleHttp request json data error '.date('H:i:s');
            }
        } catch (ValidateException $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 验证异常捕获：' .$e->getError();
        } catch (\Exception $e) {
            return __CLASS__.'\\'.__FUNCTION__.' 异常捕获：' .$e->getMessage();
        }

        $rules_page_sum = array_sum(array_column($options['rules'],'page'));
        $rules_timers_sum = array_sum(array_column($options['rules'],'timers'));
        // 如果 TA的粉丝(page) + TA的关注(page) = 2 说明它们都是第1页
        // 如果 TA的粉丝(timers) + TA的关注(timers) > time() 说明它们它们都更新过
        if ($rules_page_sum === 2 && $rules_timers_sum > time()) {
            return false;
        }else{
            return __CLASS__.'\\'.__FUNCTION__.' model(bilibili) uid('.$options['uid'].') page number('.$options['rules'][$rulesKey]['page'].') data('.count($proxy['data']['list']).') rules('.$rules['request'].') api interface data storage success '.date('H:i:s');
        }
    }
}
