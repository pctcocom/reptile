<?php
namespace Pctco\Reptile\A1;
use app\Model\Movie as ModelMovie;
use Pctco\Reptile\Tools;
use QL\QueryList;
use Pctco\Storage\App\Cover;
class Movie{
    function __construct($config){
        $this->config = $config;
        $this->tools = new Tools();
        $this->ModelMovie = new ModelMovie;
    }
    /** 
     ** 周期
     *? @date 22/07/09 00:26
     */
    public function timers(){
        return $this->s1();
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
            $itme = $this->config['movie']['handle']['data'][0];

            $find = 
            $this->ModelMovie
            ->field('id')
            ->where(['aid'=>$itme['id'],'source'=>1])
            ->find();

            if (empty($find)) {
                $id = 
                $this->ModelMovie
                ->insertGetId([
                    'aid'   =>  $itme['id'],
                    'title' =>  $itme['title'],
                    'score'  =>  $itme['rate'],
                    'source'    =>  1,
                    'atime' =>  time()
                ]);

                $find = 
                $this->ModelMovie
                ->field('id')
                ->where('id',$id)
                ->find();

                
                $cover = new Cover($id,$itme['cover'],'movie','movie','cover');
                $cover->save();
            }

            unset($this->config['movie']['handle']['data'][0]);
            $this->config['movie']['handle']['data'] = array_values($this->config['movie']['handle']['data']);
            file_put_contents($this->config['json']['path'],json_encode($this->config));

            return __CLASS__.'\\'.__FUNCTION__.' id = '.$find->id.' data successful '.date('H:i:s');

        }

        /** 
         ** 如果 handle data 中没有数据则在json中加入需要处理的数据
         */
        $model = $this->tools->MinTimers($this->config['movie']['model']);

        try {
            $client = new \GuzzleHttp\Client();

            $guzzle['query'] = $model['query'];
            if ($proxy['ip'] !== '127.0.0.1') {
                $guzzle['proxy']['https'] = $proxy['ip'];
            }

            $result = 
            $client->request('get',$this->config['movie']['request'],$guzzle);

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
        
        // handle 处理器进行数据存储
        $this->config['movie']['handle'] = [
            'model' =>  $model['query']['type'],
            'function'  =>  's1',
            'data'  =>  $result['subjects']
        ];

        // 更新相应的page_start页数
        foreach ($this->config['movie']['model'] as $k => $v) {
            if ($v['query']['type'] === $model['query']['type']) {
                if ($this->config['movie']['model'][$k]['query']['page_start'] > 1) {
                    $this->config['movie']['model'][$k]['query']['page_start']--;
                }
            }
        }
        
        file_put_contents($this->config['json']['path'],json_encode($this->config));

        return __CLASS__.'\\'.__FUNCTION__.' api interface data storage success '.date('H:i:s');
    }
    /** 
     ** 收集详细信息
     */
    public function s2(){

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
