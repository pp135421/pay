<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/23
 * Time: 16:32
 */

namespace app\api\controller;

use think\Db;
use app\index2020\model\Statistics;
class Index extends Common
{
    public function index()
    {
        //记录访问日志，并返回商户服务器IP
        // $ip = static::logAccessData('订单创建');
        //收集商户提交的合法数据
        $requestData = static::getRequestData();
        //获取商户对应数据
        $memberInfo = db('member')->where([
            'member_id' => $requestData['member_id'],
        ])->find();
        if(!$memberInfo) showmessage('商户号：'. $requestData['member_id']. '不存在');
        //签名验证
        $sign_new = static::makeSign($requestData, $memberInfo['apikey']);
        $sign = input('sign', '');
        if($sign_new != $sign) {
            ksort($requestData);
            $md5_str = '';
            foreach ($requestData as $k => $v) {
                $md5_str .= $k. '='. $v. "&";
            }
            $md5_str .= 'key='. $memberInfo['apikey'];
            showmessage('sign结果有误！服务器md5字符串结果：'. $md5_str. '，计算结果sign：'. $sign_new. '，您上传sign:'. $sign);
        }
        if($memberInfo['agent'] == '2') showmessage('代理商户号不能交易订单！');
        //商户对应分配通道
        //单独通道
        if($memberInfo['regulation_'. $requestData['type_name']] == 1){
            $memberChannelInfo = db('member_channel')->where([
                'member_id' => $memberInfo['member_id'],
                'type_name' => $requestData['type_name'],
                'status' => 1,
                'regulation' => 1,
            ])->find();
            if(!$memberChannelInfo) showmessage($requestData['type_name']. '：商户管理，指定通道（单独）未开启！');
            //总通道
            $channelInfo = db('channel')->where([
                'id' => $memberChannelInfo['channel_id'],
                'status' => 1, //总通道开启
            ])->find();
            if(!$channelInfo) showmessage($requestData['type_name']. '：总通道未开启！请联系客服打开！');
        }else{
            //轮询通道
            $memberChannelData = db('member_channel')->where([
                'member_id' => $memberInfo['member_id'],
                'type_name' => $requestData['type_name'],
                'status' => 1,
                'regulation' => 2,
            ])->select();
            if(!$memberChannelData) showmessage($requestData['type_name']. '：商户管理，指定通道（轮询）未开启！');
            $channel_ids = array_column($memberChannelData, 'channel_id');
            $channelData = db('channel')->where(['status' => 1])->select();
            if(!$channelData) showmessage($requestData['type_name']. '：不存在开启的通道');
            $channelIds = [];
            $channelDataMember = [];
            foreach ($channelData as $k => $v) {
                foreach ($channel_ids as $k2 => $v2) {
                    if($v['id'] == $v2 && $v['status'] == 1 ){
                        $channelIds[] = $v2;
                        $channelDataMember[] = $v;
                    }
                }
            }
            if(!$channelIds) showmessage($requestData['type_name']. '：通道管理，指定通道（轮询）未开启！');
            $channelIdArr = [];
            foreach ($channelData as $k => $v) {
                if(in_array($v['id'], $channelIds)){
                    if($v['status'] == 1 && (float)$requestData['amount'] >= (float)$v['min_money_poll'] && (float)$requestData['amount'] <= (float)$v['max_money_poll']){
                        $channelIdArr[] = $v;
                    }
                }
            }
            if(!$channelIdArr) {
                $num = rand(0, count($channelDataMember) - 1);
                $channelInfo = $channelDataMember[$num];
            }else{
                //随机先取出一个通道备用
                $num = rand(0, count($channelIdArr) - 1);
                $channelInfo = $channelIdArr[$num];
                if(count($channelIdArr) > 1){
                    require ROOT_PATH. 'extend/redis/RedisLock.php';
                    $config = array(
                        'host' => 'localhost',
                        'port' => 6379,
                        'index' => 0,
                        'auth' => '',
                        'timeout' => 1,
                        'reserved' => NULL,
                        'retry_interval' => 100,
                    );
                    $oRedisLock = new \RedisLock($config);
                    $key = config('platform_name_en'). '_mylock';
                    $is_lock = $oRedisLock->lock($key, 3);
                    if($is_lock){
                        //按照轮询，可能会重新匹配一个替换
                        $arr = [];
                        $channel_choose = cache('channel_choose');
                        // dump($channel_choose);
                        if(!$channel_choose){
                            //通道缓存数据初始化
                            $channel_choose = [];
                            foreach ($channelIdArr as $k => $v) {
                                $arr['channel_id'] = $v['id'];
                                $arr['name_cn'] = $v['name_cn'];
                                $arr['weight'] = $v['weight'];
                                $arr['chooseCount'] = 0;
                                $channel_choose[$v['id']] = $arr;
                            }
                        }else{
                            //判断缓存里的通道数据是否还是启用状态
                            $arrTemp = [];
                            foreach ($channel_choose as $k => $v) {
                                foreach ($channelIdArr as $k2 => $v2) {
                                    if($v['channel_id'] == $v2['id'] && $v2['status'] == 1){
                                        $arrTemp[$v['channel_id']] = $v;
                                    }
                                }
                            }
                            $channel_choose = $arrTemp;
                        }
                        // dump($channel_choose);
                        //按照chooseCount升序（可以按顺序保证每个通道都可以正常轮询1次）
                        $chooseCount = array_column($channel_choose, 'chooseCount');
                        $weight = array_column($channel_choose, 'weight');
                        array_multisort($chooseCount, SORT_ASC, $weight, SORT_DESC,  $channel_choose);
                        //从权重轮询中选出
                        $num2 = 0;
                        $chooseNoCount = 0;
                        $weight = 0;
                        foreach ($channel_choose as $k => $v) {
                            $chooseNoCount++;
                            if($v['weight']){
                                $channel_choose[$k]['weight']--;
                                $weight = $channel_choose[$k]['weight'];
                                $channel_choose[$k]['chooseCount']++;
                                $num2 = $v['channel_id'];
                                // dump($v['name_cn']);
                                break;
                            }
                        }
                        //按照通道ID重置channelIdArr键值
                        $arrTemp = [];
                        foreach ($channelIdArr as $k => $v) {
                            $arrTemp[$v['id']] = $v;
                        }
                        $channelIdArr = $arrTemp;
                        // dump($channel_choose);
                        // dump($weight);
                        //按照新的比例规则匹配出新的通道分配给商户
                        if($num2){
                            if($chooseNoCount > count($channel_choose) - 1 && $weight <= 0){
                                $channel_choose = null;
                            }
                            $channelInfo = $channelIdArr[$num2];
                        }else{
                            $channel_choose = null;
                        }
                        cache('channel_choose', $channel_choose);
                        $oRedisLock->unlock($key);
                    }else{
                        showmessage('redis并发锁设置失败！');
                    }
                }
            }
        }
        //最小、最大金额判断
        if($channelInfo['min_money'] > 0 && $requestData['amount'] < $channelInfo['min_money'] || $channelInfo['max_money'] > 0 && $requestData['amount'] > $channelInfo['max_money'])
            showmessage($requestData['type_name']. '：通道金额范围：'. $channelInfo['min_money']. '~'. $channelInfo['max_money']);
        //加入数据并生成订单并找到对应通道控制器处理订单
        static::orderToController($requestData, $memberInfo, $channelInfo);
    }

    public function dianyuantong()
    {
        $info = $this->checkPlatform();
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info = $this->checkOrder($info);
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $chinaProvinceCityArr = config('chinaProvinceCityArr');
        // dump($chinaProvinceCityArr);
        if(!in_array($info['province_city'], $chinaProvinceCityArr)){

            $msg = '请使用中国境内网络或VPN<br/>仅在如下省市IP出码：<br/>'. implode(',', $chinaProvinceCityArr);
            static::logAccessData($info['platform_order_id']. ' | ' .$msg, 'fail');
            return view('error', compact('msg'));
        }
        $wechatMsg = '';
        if(!$info['relate_key'] && !$info['special_str']){
            $time = time();
            $wechatAccountInfo = null;
            $configInfo = db('config')->find(1);
            //取出当天的订单数据
            $orderData = db('order')->field('amount, relate_key, pay_status, create_date, province_city')->where([
                'channel_id' => 301,
                'relate_key' => ['neq', '0'],
                // 'create_date' => ['between', [strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59'))]],
            ])->order('id desc')->select();
            //优先选择没有被派单过的微信
            $wechatData = db('wechat_account')->field('name, url')->where([
                'status' => 1,
                'pid' => ['gt', 0],
                'is_unusual' => 2,
            ])->select();
            if($wechatData){
                $relateKeyArr = array_column($wechatData, 'name');
                $wechatData = array_combine($relateKeyArr, $wechatData);
                foreach ($wechatData as $k => $v) {
                    foreach ($orderData as $k2 => $v2) {
                        if($v['name'] == $v2['relate_key'] && $v2['province_city']){
                            $key = array_search($v2['relate_key'], $relateKeyArr);
                            if($key !== false){
                                array_splice($relateKeyArr, $key, 1);
                            }
                        }
                    }
                }
                if($relateKeyArr){
                    $info['intellect_dispatch'] = 4; //初始派单
                    $wechatAccountInfo = $wechatData[$relateKeyArr[0]];
                }
            }
            if(!$wechatAccountInfo){
                //$relateKeyArr 限制 $configInfo['delay_dispatch_second'] 分钟内已经派单的微信不再接收派单
                $noAssignArr = [];
                if($configInfo['delay_dispatch_second'] > 0){
                    foreach ($orderData as $k => $v) {
                        if($v['amount'] == (float)$info['amount'] && $v['create_date'] >= $time - 60 * $configInfo['delay_dispatch_second'] && $v['create_date'] <= $time){
                                $noAssignArr[] = $v['relate_key'];
                        }
                    }
                }
                // dump($relateKeyArr);
                //取出符合条件的微信
                $wechatData = db('wechat_order_success')
                ->alias('a')
                ->join('wechat_account b', 'a.name=b.name', 'left')
                ->field('a.name, count(a.province_city) count_province_city, b.account, b.day_max_money, b.used_date, b.url')
                ->where([
                    'a.province_city' => $info['province_city'],
                    'a.name' => ['not in', $noAssignArr],
                    'a.amount' => ['egt', 1],
                    'b.status' => 1,
                    'b.is_unusual' => 2,
                    // 'a.success_date' => ['between', [strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59'))]],
                ])
                ->group('a.name, a.province_city')
                ->order('count_province_city desc')
                ->select();
                // foreach ($wechatData as $k => $v) {
                //     dump(base64_decode($v['name']));
                // }
                // dump($wechatData);die;
                if($wechatData){
                    foreach ($wechatData as $k => $v) {
                        if(!isset($wechatData[$k]['noSuccessCount'])) $wechatData[$k]['noSuccessCount'] = 0;
                        foreach ($orderData as $k2 => $v2) {
                            if($v2['province_city'] == $info['province_city']){
                                if($v['name'] == $v2['relate_key']){
                                    if(in_array($v2['pay_status'], [2, 3, 4])){
                                        break;
                                    }
                                    $wechatData[$k]['noSuccessCount']++;
                                }
                            }
                        }
                    }
                    $relateKeyArr = array_column($wechatData, 'name');
                    $tempArr = [];
                    foreach ($wechatData as $k => $v) {
                        //只取出连续失败次数小于5的微信
                        if($v['noSuccessCount'] < 5){
                            $tempArr[] = $v;
                        }
                    }
                    $wechatData = $tempArr;
                    // dump($wechatData);die;
                    // dump($wechatData);
                    if($wechatData){
                        //按照 noSuccessCount 升序
                        $noSuccessCount = array_column($wechatData, 'noSuccessCount');
                        //按照 used_date 升序
                        $used_date = array_column($wechatData, 'used_date');
                        array_multisort($noSuccessCount, SORT_ASC, $used_date, SORT_ASC, $wechatData);
                        $info['intellect_dispatch'] = 1; //智能派单
                        $wechatAccountInfo = $wechatData[0];
                    }
                }
            }
            if(!$wechatAccountInfo){
                //优化随机匹配规则（尽量分配给收款成功省市较少的微信，降低微信账户异常的概率）
                $wechatData = db('wechat_account')
                ->alias('a')
                ->join('wechat_order_success b', 'a.name=b.name', 'left')
                ->field('a.name, b.province_city, a.url, a.used_date')
                ->where([
                    'a.pid' => ['gt', 0],
                    'a.status' => 1,
                    'a.is_unusual' => 2,
                    //$configInfo['delay_dispatch_second'] 分钟内不会被重复分配到
                    'a.used_date' => ['<', $time - 60 * $configInfo['delay_dispatch_second']],
                ])
                ->order('a.used_date asc')
                ->select();
                // dump($wechatData);
                if($wechatData){
                    // dump($wechatData);
                    $wechatData = array_unique($wechatData, SORT_REGULAR);
                    $nameArr = array_column($wechatData, 'name');
                    $wechatData = array_combine($nameArr, $wechatData);
                    $tempArr = array_count_values($nameArr);
                    asort($tempArr);
                    // dump($tempArr);
                    foreach ($wechatData as $k => $v) {
                        foreach ($tempArr as $k2 => $v2) {
                            if($k == $k2){
                                $wechatData[$k]['count_province_city'] = $v2;
                                break;
                            }
                        }
                    }
                    //按照 count_province_city 升序
                    $count_province_city = array_column($wechatData, 'count_province_city');
                    //按照 used_date 升序
                    $used_date = array_column($wechatData, 'used_date');
                    array_multisort($count_province_city, SORT_ASC, $used_date, SORT_ASC, $wechatData);
                    // dump($wechatData);
                    foreach ($wechatData as $k => $v) {
                        $info['intellect_dispatch'] = 2; //普通派单
                        $wechatAccountInfo = $v;
                        break;
                    }
                }

                if(!$wechatAccountInfo){
                    $wechatAccountInfo = db('wechat_account')
                    ->where([
                        'status' => 1,
                        'pid' => ['gt', 0],
                    ])
                    ->order('used_date asc')
                    ->find();
                    if(!$wechatAccountInfo) {
                        $msg = '没有可用微信账户';
                        static::logAccessData($info['platform_order_id']. ' | ' .$msg, 'fail');
                        return view('error', compact('msg'));
                    }
                    $info['intellect_dispatch'] = 3; //随机派单
                }
            }
            if(!$wechatAccountInfo['url']) {
                $msg = $wechatAccountInfo['url'].'：收款二维码解析异常！';
                static::logAccessData($info['platform_order_id']. ' | ' .$msg, 'fail');
                return view('error', compact('msg'));
            }

            $result = db('wechat_account')->where(['name' => $wechatAccountInfo['name']])->setField([
                'used_date' => time(),
            ]);
            if(!$result) {
                $msg = '更新微信账户使用时间异常';
                static::logAccessData($info['platform_order_id']. ' | ' .$msg, 'fail');
                return view('error', compact('msg'));
            }
            $result = db('order')->where(['platform_order_id' => $info['platform_order_id']])->setField([
                'relate_key' => $wechatAccountInfo['name'],
                'special_str' => $wechatAccountInfo['url'],
                'intellect_dispatch' => $info['intellect_dispatch'],
            ]);
            if(!$result) {
                $msg = '订单保存链接失败！';
                static::logAccessData($info['platform_order_id']. ' | ' .$msg, 'fail');
                return view('error', compact('msg'));
            }
        }else{
            $wechatAccountInfo = db('wechat_account')->where([
                'name' => $info['relate_key'],
            ])->order('used_date asc')->find();
            if(!$wechatAccountInfo) {
                $msg = '没有可用微信账户';
                static::logAccessData($info['platform_order_id']. ' | ' .$msg, 'fail');
                return view('error', compact('msg'));
            }
            $msg = $info['intellect_dispatch'] == 1 ? '智能派单' : '随机派单';
        }
        if($info['intellect_dispatch'] == 1){
            $wechatMsg = '（智能派单）';
        }else if($info['intellect_dispatch'] == 2){
            $wechatMsg = '（普通派单）';
        }else if($info['intellect_dispatch'] == 3){
            $wechatMsg = '（随机派单）';
        }else if($info['intellect_dispatch'] == 4){
            $wechatMsg = '（初始派单）';
        }else{
            $wechatMsg = '（-）';
        }
        $info['img_url'] = qrcode($wechatAccountInfo['url']);
        $info['relate_key'] = base64_decode($wechatAccountInfo['name']). $wechatMsg;
        return view('order_dianyuantong', compact('info'));
    }

    public function form_submit()
    {
        $info = $this->checkPlatform();
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info = $this->checkOrder($info);
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $channelInfo = db('channel')->find($info['channel_id']);
        //找到商户分配的对应通道文件类
        $file = __DIR__.'/'. $channelInfo['name_en']. '.php';
        if(!is_file($file)) showmessage($channelInfo['name_en']. '：文件不存在！');
        include_once $file ;
        $className = '\\app\api\controller\\'. $channelInfo['name_en'];
        $info['curl'] = 1;
        $className::pay($info);
    }

    protected function checkPlatform()
    {
        header('Content-type:text/html;charset=utf-8');
        //判断订单号各种情况
        $orderid = charDecode(input('orderid', ''));
        if(!preg_match('/^'. config('platform_name_en'). '[0-9]{15}$/', $orderid)) {
            $msg = '该订单号异常';
            static::logAccessData('null'. ' | ' .$msg, 'fail');
            return $msg;
        }
        //取出合法订单数据
        $info = db('order')->where(['platform_order_id' => $orderid])->find();
        if(!$info){
            $msg = '该订单号不存在';
            static::logAccessData('null'. ' | ' .$msg, 'fail');
            return $msg;
        }
        $info['actual_amount'] = bcadd($info['actual_amount'], 0, 2);
        return $info;
    }

    protected function checkOrder($info)
    {
        //随机取一个IP出来模拟
        // $access_ip = db('order')->field('access_ip')->where(['access_ip' => ['neq', '']])->select();
        // $num = rand(0, count($access_ip) - 1);
        // $ip = $access_ip[$num]['access_ip'];
        // $ip = '153.19.50.62';
        $ip = getClientIP();
        // dump($ip);
        //获得省市
        $province_city = ip_to_city($ip, false);
        if($province_city == '中国'){
            $province_city = baidu_map_ip($ip);
        }
        $province_city = only_province_city($province_city);
        // dump($province_city);
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if(isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == config('admin_domin') && isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '80' || isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] == config('admin_domin') && isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '80'){
            //订单数据里保存访问IP对应省市
            if(!$info['access_ip'] && !$info['province_city']){
                $data = [
                    'access_type' => $user_agent,
                    'access_ip' => $ip,
                    'province_city' => $province_city,
                ];
                $result = db('order')->where(['platform_order_id' => $info['platform_order_id']])->setField($data);
                if(!$result) {
                    $msg = '保存跳转到上游的玩家IP失败！';
                    static::logAccessData($info['platform_order_id']. ' | ' .$msg, 'fail');
                    return $msg;
                }
                // $msg = '自定义正常访问';
                // static::logAccessData($info['platform_order_id']. ' | ' .$msg. ' | ' .json_encode($_SERVER, 320), 'fail');
            }
        }else{
            $msg = '请点击该链接正常访问！';
            static::logAccessData($info['platform_order_id']. ' | ' .$msg. ' | ' .json_encode($_SERVER, 320), 'fail');
            return $msg;
        }
        $info['ip'] = $ip;
        $info['province_city'] = $province_city;
        if(strpos(strtolower($user_agent), 'Android') !== false || strpos(strtolower($user_agent), 'Adr')!== false){
            $info['user_agent'] = 'Android';
        }else if(strpos(strtolower($user_agent), 'Mac OS X') !== false || strpos(strtolower($user_agent), 'iphone') !== false || strpos(strtolower($user_agent), 'ipad') !== false || strpos(strtolower($user_agent), 'ipod') !== false){
            $info['user_agent'] = 'IOS';
        }else{
            $info['user_agent'] = 'PC';
        }
        //处理掉重复已支付订单
        if($info['pay_status'] == 2 || $info['pay_status'] == 3 || $info['pay_status'] == 4) {
            $msg = '该订单号已付款';
            static::logAccessData($info['platform_order_id']. ' | ' .$msg, 'fail');
            return $msg;
        }
        //5分钟超时
        $minuteCount = 5;
        if(time() - $info['create_date'] > 60 * $minuteCount){
            $msg = '订单超时，请重新下单';
            static::logAccessData($info['platform_order_id']. ' | ' .$msg, 'fail');
            return $msg;
        }
        //关联数据
        $channelInfo = db('channel')->find($info['channel_id']);
        $c_name = str_replace('Alipay'. '_', '', $channelInfo['name_en']);
        $c_name = str_replace('Wechat'. '_', '', $c_name);
        $info['trueUrl'] = 'alipays://platformapi/startapp?appId=20000067&url=' .urlencode('http://' .config('api_domin') . '/api/index/'.$c_name .'?orderid='. charEncode($info['platform_order_id']));
        $info['url'] = static::$alipayqr. urlencode(static::$alipays. 'http://' .config('api_domin') . '/api/index/'.$c_name .'?orderid='. charEncode($info['platform_order_id']));

        $info['img_url'] = qrcode($info['url']);
        $second = 60 * $minuteCount - (time() - $info['create_date']);
        $info['second'] = $second > 0 ? $second : 0;
        return $info;
    }

    public function order()
    {
        $info = $this->checkPlatform();
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info = $this->checkOrder($info);
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        return view('order2', compact('info'));
    }

    public function jump()
    {
        $info = $this->checkPlatform();
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info = $this->checkOrder($info);
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info['img_url'] = qrcode($info['special_str']);
        return view('order_jump', compact('info'));
    }

    public function jumpS()
    {
        $info = $this->checkPlatform();
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info = $this->checkOrder($info);
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        //$info['applNO'] = $info['special_str'];
        //dump($info);die;
        return view('order_jump_strong', compact('info'));
    }

    public function yunshanfu()
    {
        $info = $this->checkPlatform();
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info = $this->checkOrder($info);
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info['img_url'] = qrcode($info['special_str']);
        return view('order_yunshanfu', compact('info'));
    }

    public function jump_pass()
    {
        $info = $this->checkPlatform();
        $info = $this->checkOrder($info);
        // $info['img_url'] = qrcode($info['special_str']);
        header("location:". $info['special_str']);
        die;
    }

    public function jump2()
    {
        $info = $this->checkPlatform();
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info = $this->checkOrder($info);
        if(!is_array($info)){
            $msg = $info;
            return view('error', compact('msg'));
        }
        $info['img_url'] = qrcode($info['special_str']);
        $parse_url = parse_url($info['special_str']);
        $info['query'] = [];
        if(isset($parse_url['query'])){
            $info['query'] = explode('&', $parse_url['query']);
            $arr = [];
            foreach($info['query'] as $v){
                $arr[] = explode('=', $v);
            }
            $info['query'] = $arr;
        }
        return view('order_alipay2', compact('info'));

        if($info['user_agent'] == 'IOS' || $info['user_agent'] == 'Android'){
            header('location:'.$info['special_str']);die;
        }else{
            return view('order_alipay', compact('info'));
        }

    }

    public function alipay()
    {
        $info = $this->checkPlatform();
        $info = $this->checkOrder($info);
        return view('order_alipay', compact('info'));
    }

    public function wechat()
    {
        $info = $this->checkPlatform();
        $info = $this->checkOrder($info);
        return view('order_wechat', compact('info'));
    }

    public function bank()
    {
        $platform = 'bank';
        $info = $this->checkPlatform($platform);
        $info = $this->checkOrder($info, $platform);
        $alipayBankInfo = db('alipay_bank')->where(['card_no' => $info['relate_key']])->find();
        if(!$alipayBankInfo) die("<script>alert('找不到对应银行卡账户！')</script>");
        if($alipayBankInfo['card_id']){
            $hiddenCard = (string)$alipayBankInfo['card_no'];
            $str = '';
            for ($i=0; $i < strlen($hiddenCard)-1 ; $i++) {
                if($i > 5 && $i < strlen($hiddenCard) - 5){
                    $str .= '*';
                }else{
                    $str .= $hiddenCard[$i];
                }
            }
            $hiddenCard = $str;
            $url = 'alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=bill&cardNo='.$hiddenCard.'&bankAccount='.urlencode($alipayBankInfo['bank_account']).'&money='.$info['actual_amount'].'&amount='.$info['actual_amount'].'&bankMark='.$alipayBankInfo['bank_mark'].'&bankName='.urlencode($alipayBankInfo['bank_name']).'&cardIndex='.$alipayBankInfo['card_id'].'&cardNoHidden=true&cardChannel=HISTORY_CARD&orderSource=from';
        }else{
            $url = 'alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=bill&cardNo='.$alipayBankInfo['card_no'].'&bankAccount='.urlencode($alipayBankInfo['bank_account']).'&money='.$info['actual_amount'].'&amount='.$info['actual_amount'].'&bankMark='.$alipayBankInfo['bank_mark'].'&bankName='.urlencode($alipayBankInfo['bank_name']);
        }
        $info['bankUrl'] = $url;
        $info['wakeup'] = 'alipays://platformapi/startapp?appId=10000007&qrcode=' .urlencode('http://' .config('api_domin') . '/api/index/'.'dingding2' .'?orderid='. charEncode($info['platform_order_id']));
        $info['trueUrl'] = 'alipays://platformapi/startapp?appId=10000007&qrcode=' .urlencode('http://' .config('api_domin') . '/api/index/'.'bank2' .'?orderid='. charEncode($info['platform_order_id']).'&step=1');
        $info['url'] = 'https://render.alipay.com/p/s/i?scheme='. urlencode($url);
        $info['trueUrl'] = static::$taobao. urlencode($info['bankUrl']);
        $info['trueUrl2'] = 'alipays://platformapi/startapp?appId=20000691&url='. urlencode('http://' .config('api_domin') . '/api/index/'.'bank2' .'?orderid='. charEncode($info['platform_order_id']));
        $info['img_url'] = qrcode($info['url']);
        return view('order_taobao', compact('info'));
    }

    public function bank2()
    {
        $platform = 'bank';
        $info = $this->checkPlatform($platform);
        $info = $this->checkOrder($info, $platform);
        $alipayBankInfo = db('alipay_bank')->where(['card_no' => $info['relate_key']])->find();
        if(!$alipayBankInfo) die("<script>alert('找不到对应银行卡账户！')</script>");
        if($alipayBankInfo['card_id']){
            $hiddenCard = (string)$alipayBankInfo['card_no'];
            $str = '';
            for ($i=0; $i < strlen($hiddenCard)-1 ; $i++) {
                if($i > 5 && $i < strlen($hiddenCard) - 5){
                    $str .= '*';
                }else{
                    $str .= $hiddenCard[$i];
                }
            }
            $hiddenCard = $str;
            $url = 'alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=bill&cardNo='.$hiddenCard.'&bankAccount='.urlencode($alipayBankInfo['bank_account']).'&money='.$info['actual_amount'].'&amount='.$info['actual_amount'].'&bankMark='.$alipayBankInfo['bank_mark'].'&bankName='.urlencode($alipayBankInfo['bank_name']).'&cardIndex='.$alipayBankInfo['card_id'].'&cardNoHidden=true&cardChannel=HISTORY_CARD&orderSource=from';
        }else{
            $url = 'alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=bill&cardNo='.$alipayBankInfo['card_no'].'&bankAccount='.urlencode($alipayBankInfo['bank_account']).'&money='.$info['actual_amount'].'&amount='.$info['actual_amount'].'&bankMark='.$alipayBankInfo['bank_mark'].'&bankName='.urlencode($alipayBankInfo['bank_name']);
        }
        $info['bankUrl'] = $url;
        $info['wakeup'] = 'alipays://platformapi/startapp?appId=10000007&qrcode=' .urlencode('http://' .config('api_domin') . '/api/index/'.'dingding2' .'?orderid='. charEncode($info['platform_order_id']));
        $info['trueUrl'] = 'alipays://platformapi/startapp?appId=10000007&qrcode=' .urlencode('http://' .config('api_domin') . '/api/index/'.'bank2' .'?orderid='. charEncode($info['platform_order_id']).'&step=1');
        $info['url'] = 'https://render.alipay.com/p/s/i?scheme='. urlencode($url);
        $info['trueUrl'] = static::$taobao. urlencode($info['bankUrl']);
        return view('bank_delay', compact('info'));
        // return view('order_taobao', compact('info'));
    }


    public function dingding()
    {
        $info = $this->checkPlatform();
        $info = $this->checkOrder($info);
        return view('order_taobao', compact('info'));
    }

    public function dingding2()
    {
        $info = $this->checkPlatform();
        $info = $this->checkOrder($info);
        return view('dingding', compact('info'));
    }

    public function shoukuan3()
    {
        $info = $this->checkPlatform();
        $info = $this->checkOrder($info);
        $payurl = 'http://' .config('api_domin').'/api/index/'.'shoukuan2'.'?orderid='. charEncode($info['platform_order_id']);
        header("location: https://render.alipay.com/p/s/i?scheme=".urlencode("alipays://platformapi/startapp?saId=10000007&qrcode=".urlencode($payurl)));
        die;
    }


    public function shoukuan()
    {
        $info = $this->checkPlatform();
        $info = $this->checkOrder($info);
        // if($info['access_count'] > 0) die("<script>alert('该二维码只可访问1次，请重新下单！')</script>");
        $payurl = 'http://' .config('api_domin').'/api/index/'.'shoukuan'.'?orderid='. charEncode($info['platform_order_id']);
        if(!isset($_GET['auth_code'])){
            header("location: https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=". config('shoukuan_alipay_appid')."&scope=auth_base&redirect_uri=". urlencode($payurl)."&state=1");
            exit;
        }
        $api_domin = config('api_domin');
        $userid = file_get_contents('http://'. $api_domin .'/api/index/'.'getAlipayUserid'.'?code='. $_GET['auth_code']);
        if((float)$userid < 0) die("<script>alert('userid获得失败！')</script>");

        $alipayAccountInfo = [];
        if(!$info['relate_key']){
            //轮询出支付宝账户
            $where = [
                'status' => 1,
            ];
            $alipayAccountData = db('alipay_account')->where($where)->order('used_date asc')->select();
            if(!$alipayAccountData) die("<script>alert('找不到对应支付宝账户')</script>");
            //根据IP和设备区分开
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $where = [
                'special_str' => $userid,
                'relate_key' => ['gt', 0],
            ];
            $orderData = db('order')->where($where)->select();
            // dump($orderData);die;
            if($orderData){
                // dump($orderData);
                $relate_key_s = array_column($orderData, 'relate_key');
                $relate_key_s = array_unique($relate_key_s);
                foreach ($alipayAccountData as $k => $v) {
                    if(!in_array($v['account'], $relate_key_s)){
                        $alipayAccountInfo = $alipayAccountData[$k];
                        break;
                    }
                }
            }
            if(!$alipayAccountInfo){
                $where = [
                    'status' => 1,
                ];
                $alipayAccountInfo = db('alipay_account')->where($where)->order('used_date asc')->find();
                $result = db('alipay_account')->where(['account' => $alipayAccountInfo['account']])->setField([
                    'used_date' => time(),
                ]);
                if(!$result) die("<script>alert('分配关联支付宝账户异常')</script>");
            }
        }else{
            $alipayAccountInfo = db('alipay_account')->where(['account' => $info['relate_key']])->order('used_date asc')->find();
            if(!$alipayAccountInfo) die("<script>alert('指定关联支付宝账户不存在')</script>");
        }
        db('order')->where(['platform_order_id' => $info['platform_order_id']])->setField([
            'access_count' => $info['access_count'] + 1,
            'access_ip' => getClientIP(),
            'special_str' => $userid,
            'relate_key' => $info['relate_key'] ? $info['relate_key'] : $alipayAccountInfo['account'],
        ]);
        $info['userId'] = $alipayAccountInfo['appid'];
        $info['account'] = $alipayAccountInfo['account'];
        $info['alipay_account'] = $alipayAccountInfo['name'];
        $info['userid2'] = $userid;
        if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'iphone') || strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'ipod') || strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'ipad')){
            $info['access_type'] = 'iphone';
        }
        if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'android')){
            $info['access_type'] = 'Android';
        }
        return view('shoukuan', compact('info'));
    }

    public function shoukuan_info()
    {
        $act = input('act', '');
        $appid = input('appid', '');
        if($act == 'msg'){
            $platform_order_id = input('id', 0);
            if(!$platform_order_id) die('订单号非法');
            $userid = input('userid', 0);
            if(!$userid) die('userid非法');
            $info = db('order')->where(['platform_order_id' => $platform_order_id])->find();
            //保存用户userid
            db('order')->where(['platform_order_id' => $platform_order_id])->setField(['special_str' => $userid]);
            if(!$info) die('找不到对应订单');
            $info['actual_amount'] = bcadd($info['actual_amount'], 0, 2);
            include_once ROOT_PATH. 'extend/mqtt/aes.php';
            $keyStr = 'U1TXC5LQ11C436IM';
            $plainText = "pay_".$platform_order_id.'_'. $info['actual_amount'].'_alipay_'. $userid;
            $aes = new \CryptAES();
            $aes->set_key($keyStr);
            $aes->require_pkcs5();
            $encText = $aes->encrypt($plainText);
            include_once ROOT_PATH. 'extend/mqtt/Mqtt.php';
            $mqtt = new \Mqtt("47.244.145.196", 61613, $info['relate_key']."_server"); //实例化MQTT类
            if ($mqtt->connect(true, NULL, "admin", "UrSOoaKU50od5CuBa8rxjoL")) {
                echo '1';
                //如果创建链接成功
                $mqtt->publish($info['relate_key'], $encText, 0);
                $mqtt->close();    //发送后关闭链接
            }else{
                echo '0';
            }
        }else if($act == 'login'){
            echo json_encode(array(
               'msg' => '登录成功_'. config('api_domin'),
               'status' => '1',
               'return_url' => 'https://pay.baidu.com/',
               'notify_url' => 'http://'.config('api_domin'). '/hongbao',
               'notify_key' => '1234567',
               'app_type' => 1,
               // 'receive_url' => 'http://'.config('api_domin'). '/api/index/shoukuan_info',
               'receive_url' => 'http://'.config('api_domin'). '/api/index/shoukuan_info',
               'run_alipay' => 1,
               'run_weixin' => 1,
               'run_qq' => 1,
               'mqtt_need' => 1,
               "mqtt_host" => "tcp://47.244.145.196:61613",
               "mqtt_username" => "admin",
               "mqtt_password" => "UrSOoaKU50od5CuBa8rxjoL",
               "mqtt_topic" => $appid,
               'appid' => $appid,
           ));
        }elseif($act == 'logout'){
            echo json_encode(array('msg'=>'注销成功','status'=>'1'));
        }else if($act == 'rec_pay'){
            $data = $_POST;
            $array1 = array('code'=>1,'pay_url'=>urldecode(trim($_POST['payurl'])));
            file_put_contents(ROOT_PATH. 'extend/log/shoukuan/'.$_POST['mark'].'.ul1', json_encode($array1));
            echo 'success';
        }else if($act == 'get_url'){
            $file = ROOT_PATH. 'extend/log/shoukuan/'.$_POST['orderid'].'.ul1';
            $str = '';
            if(file_exists($file)){
                $str = file_get_contents(ROOT_PATH. 'extend/log/shoukuan/'.$_POST['orderid'].'.ul1');
            }
            // file_put_contents('./1.txt', $str. PHP_EOL, FILE_APPEND);
            echo $str;
        }else if($act == 'get_ordercheck'){
            $str = file_get_contents(ROOT_PATH. 'extend/log/shoukuan/'.$_GET['orderid'].'.ul2');
            echo $str;
        }
        exit;
    }

    public function reOrder()
    {
        die("<script>alert('请重新下单')</script>");
    }

    public function getAlipayUserid()
    {
        $cope = $_GET['code'];
        require_once ROOT_PATH. 'extend/aop/AopClient.php';
        //支付宝商户秘钥
        $merchant_private_key = config('shoukuan_merchant_private_key');
        if(strpos($merchant_private_key,'PRIVATE') === false){
            $merchant_private_key = "-----BEGIN PRIVATE KEY-----\n" .
            wordwrap($merchant_private_key, 64, "\n", true) .
            "\n-----END PRIVATE KEY-----";
        }
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = config('shoukuan_alipay_appid');
        $aop->rsaPrivateKey = $merchant_private_key;
        //支付宝公钥
        $aop->alipayrsaPublicKey= config('shoukuan_alipay_public_key');
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        require_once ROOT_PATH. 'extend/aop/request/AlipaySystemOauthTokenRequest.php';
        $request = new \AlipaySystemOauthTokenRequest ();
        $request->setCode($cope);
        $request->setGrantType('authorization_code');
        $result = $aop->execute ( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $user_id = $result->$responseNode->user_id;
        return $user_id;
    }

    public function nongxin()
    {
        $info = $this->checkPlatform();
        $info = $this->checkOrder($info);
            //内部跳转
        header("location:". $info['special_str']);die;
    }

    public function hongbao()
    {
        $info = $this->checkPlatform();
        $info = $this->checkOrder($info);
        $alipay_account_info = db('alipay_account')->where(['account' => $info['relate_key']])->find();
        if(!$alipay_account_info) die("<script>alert('指定关联支付宝账户不存在')</script>");
        $info['appid'] = $alipay_account_info['appid'];
        $info['account'] = $alipay_account_info['account'];
        // dump($_SERVER);die;
        return view('hongbao', compact('info'));
    }

}
