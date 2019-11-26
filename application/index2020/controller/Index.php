<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/28
 * Time: 20:27
 */

namespace app\index2020\controller;

use think\Db;
use app\index2020\model\Statistics;
class Index extends Common
{
    public function index(){
        return view();
    }

    public function welcome(){
        $memberInfo = db('member')->where(['member_id' => MEMBER_ID])->find();
        if($memberInfo['agent'] == 2){
            $memberIdArr = db('member')->field('member_id')->where(['pid' => $memberInfo['id']])->select();
            if($memberIdArr){
                $memberIdArr = array_column($memberIdArr, 'member_id');
            }else{
                $memberIdArr = [MEMBER_ID];
            }
        }else{
            $memberIdArr = [MEMBER_ID];
        }
        $orderData = db('order')->field('pay_status, amount')->where([
            'create_date' => ['between', [strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59'))]],
            'member_id' => ['in', $memberIdArr],
        ])->select();
        $count = 0;
        $successCount = 0;
        $successMoney = 0;
        foreach ($orderData as $k => $v) {
            if(in_array($v['pay_status'], [2, 3, 4])){
                $successCount++;
                $successMoney += (float)$v['amount'];
            }
            $count++;
        }
        $memberInfo['count'] = $count;
        $memberInfo['successCount'] = $successCount;
        $memberInfo['successMoney'] = $successMoney;
        return view('welcome', compact('memberInfo'));
    }
}