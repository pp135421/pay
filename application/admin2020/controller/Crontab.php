<?php
namespace app\admin2020\controller;
use think\Controller;

class Crontab extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        echo date('Y-m-d H:i:s');
        $configInfo = db('config')->find(1);
        $flag = false;
        if($configInfo['crontab_close_wechat_account_order_fail_acount'] == 1) {
            $flag = true;
            $wechatAccountData = db('wechat_account')->field('id, name')->where([
                'status' => 1,
            ])->select();
            // dump($wechatAccountData);
            $wechatNameArr = array_column($wechatAccountData, 'name');
            // dump($wechatNameArr);
            $time = time();
            // dump($wechatNameArr);
            $orderData = db('order')->field('amount, create_date, pay_status, relate_key')->where([
                'relate_key' => array('in', $wechatNameArr),
                'create_date' => array('between', array(strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59')))),
            ])->order('id desc')->select();
            // dump($orderData);
            foreach ($wechatAccountData as $k => $v) {
                if(!isset($wechatAccountData[$k]['noSuccessCount'])) $wechatAccountData[$k]['noSuccessCount'] = 0;
                foreach ($orderData as $k2 => $v2) {
                    if($v['name'] == $v2['relate_key'] && $time - $v2['create_date'] >= 120){//最后一单超过2分钟
                        if(!in_array($v2['pay_status'], [2, 3, 4])){
                            $wechatAccountData[$k]['noSuccessCount']++;
                        }else{
                            break;
                        }

                    }
                }
            }
            $wechatNameArr = [];
            foreach ($wechatAccountData as $k => $v) {
                if($v['noSuccessCount'] >= $configInfo['order_fail_acount']){
                    $wechatNameArr[] = $v['name'];
                }
            }
            $result = db('wechat_account')->where([
                'name' => array('in', $wechatNameArr),
            ])->setField([
                'status' => 2,
            ]);
            if($result){
                $tempArr = [];
                foreach ($wechatNameArr as $k => $v) {
                    if($v){
                        $tempArr[] = base64_decode($v);
                    }
                }
                log_operation('关闭异常收款微信状态成功：'. implode(' | ', $tempArr));
            }
        }

        if($configInfo['crontab_close_wechat_account_day_max_money'] == 1) {
            $flag = true;
            $wechatAccountData = db('wechat_account')->field('day_max_money, id, name')->where([
                'status' => 1,
            ])->select();
            $wechatNameArr = array_column($wechatAccountData, 'name');
            // dump($wechatNameArr);
            $time = time();
            // dump($wechatNameArr);
            $orderData = db('order')->field('amount, create_date, pay_status, relate_key')->where([
                'relate_key' => array('in', $wechatNameArr),
                'create_date' => array('between', array(strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59')))),
            ])->select();
            // dump($orderData);
            foreach ($wechatAccountData as $k => $v) {
                if(!isset($wechatAccountData[$k]['successMoney'])) $wechatAccountData[$k]['successMoney'] = 0;
                foreach ($orderData as $k2 => $v2) {
                    if($v['name'] == $v2['relate_key'] && in_array($v2['pay_status'], [2, 3, 4])){
                        $wechatAccountData[$k]['successMoney'] += (float)$v2['amount'];
                    }
                }
            }
            // dump($wechatAccountData);
            $wechatNameArr = [];
            foreach ($wechatAccountData as $k => $v) {
                if((float)$v['day_max_money'] > 0 && $v['successMoney'] >= $v['day_max_money']){
                    $wechatNameArr[] = $v['name'];
                }
            }
            $result = db('wechat_account')->where([
                'name' => array('in', $wechatNameArr),
            ])->setField([
                'status' => 2,
            ]);
            if($result){
                $tempArr = [];
                foreach ($wechatNameArr as $k => $v) {
                    if($v){
                        $tempArr[] = base64_decode($v);
                    }
                }
                log_operation('关闭超额微信状态成功：'. implode(' | ', $tempArr));
            }
        }
        if(!$flag) die('未启动定时关闭异常微信账户任务');
    }

    public function yy()
    {
        echo phpinfo();
    }

    // public function xx()
    // {
    //     $orderData = db('order')->field('access_ip, platform_order_id')->select();
    //     foreach ($orderData as $k => $v) {
    //         if($v['access_ip']){
    //             $province_city = ip_to_city($v['access_ip'], false);
    //             if($province_city == '中国'){
    //                 $province_city = baidu_map_ip($v['access_ip']);
    //             }
    //             $province_city = only_province_city($province_city);
    //             db('order')->where(['platform_order_id' => $v['platform_order_id']])->setField(['province_city' => $province_city]);
    //         }
    //     }
    // }

}
