<?php

namespace app\admin2020\controller;

use think\Db;
class Index extends Common
{
    public function __construct()
    {
        parent ::__construct();
    }

    //首页展示
    public function index()
    {
        $_rule_ids = $this->_rule_ids;
        return view('index', compact('_rule_ids'));
    }

    //首页信息
    public function welcome()
    {

        $orderData = db('order')
        ->field('channel_id, member_id, amount, platform_poundage, agent_poundage, passage_poundage, income_amount, pay_status, create_date, success_date')
        ->select();
        $channelData = db('channel')->select();
        foreach ($orderData as $k => $v) {
            if(!isset($orderData[$k]['is_inner'])) $orderData[$k]['is_inner'] = '2';
            foreach ($channelData as $k2 => $v2) {
                if($v['channel_id'] == $v2['id']){
                    $orderData[$k]['is_inner'] = $v2['is_inner'];
                }
            }
        }
        $list['allMoney'] = 0;
        $list['allMoneyInner'] = 0;
        $list['allMoneyNotInner'] = 0;
        $list['allAgent'] = 0;
        $list['allPlatform'] = 0;
        $list['today_allMoney'] = 0;
        $list['today_allAgent'] = 0;
        $list['today_allPlatform'] = 0;
        foreach ($orderData as $k => $v) {
            if(in_array($v['pay_status'], [2, 3, 4])){
                if($v['is_inner'] == 1) $list['allMoneyInner'] += (float)$v['amount'];
                if($v['is_inner'] == 2) $list['allMoneyNotInner'] += (float)$v['amount'];
                $list['allMoney'] += (float)$v['amount'];
                $list['allAgent'] += (float)$v['agent_poundage'];
                $list['allPlatform'] += (float)$v['platform_poundage'];
                if($v['success_date'] >= strtotime(date('Y-m-d')) && $v['success_date'] <= strtotime(date('Y-m-d 23:59:59'))){
                    $list['today_allMoney'] += (float)$v['amount'];
                    $list['today_allAgent'] += (float)$v['agent_poundage'];
                    $list['today_allPlatform'] += (float)$v['platform_poundage'];
                }
            }
        }
        return view('welcome',compact('list'));
    }

    public function qrcode()
    {
        return view();
    }

}
