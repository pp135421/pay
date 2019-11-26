<?php
namespace app\admin2020\controller;

use app\admin2020\model\Member;

class Count extends Common
{
    public function __construct()
    {
        parent::__construct();
    }

    public function wechat()
    {
        //订单表
        $where = [];
        $relate_key = input('relate_key', '' , 'urldecode');
        if($relate_key){
            $where['relate_key'] = $relate_key;
        }
        $province_city = input('province_city', '');
        if($province_city){
            $where['province_city'] = $province_city;
        }
        $list = null;
        $list2 = null;
        $orderData = null;
        $moneySuccess = 0;
        $count = 0;
        $countSuccess = 0;
        $wechatAccountData = db('wechat_account')->field('name, used_date')->order('used_date desc')->select();
        if($relate_key || $province_city){
            $where['amount'] = ['>=', 100];
            // $where['create_date'] = ['between', [strtotime(date('Y-m-d', strtotime("-1 day")). ' 00:00:00'), time()]];
            $perCount = 30;
            $list = db('order')->field('platform_order_id, province_city, pay_status, relate_key, amount, access_ip, used_date')->where($where)->order('used_date desc')->paginate($perCount, false, ['query'=>input()]);
            $list2 = $list->toArray()['data'];
            $orderData = db('order')->field('platform_order_id, province_city, pay_status, relate_key, amount, access_ip, used_date')->where($where)->order('used_date desc')->select();
            foreach ($orderData as $k => $v) {
                if(in_array($v['pay_status'], [2, 3, 4])){
                    $countSuccess++;
                    $moneySuccess += (float)$v['amount'];
                }
                $count++;
            }
        }
        //回显HTML数据
        $showData = [
            'provinceCityData' => config('chinaProvinceCityArr'),
            'relate_key' => $relate_key,
            'province_city' => $province_city,
            'count' => $count,
            'countSuccess' => $countSuccess,
            'moneySuccess' => $moneySuccess,
        ];
        return view('wechat', compact('list', 'list2', 'orderData', 'wechatAccountData', 'showData'));
    }

    public function wechat2()
    {
        //订单表
        $whereOrder = [];
        // 判断每1天的商户统计数据
        $create_date = input('create_date', date('Y-m'));
        if($create_date){
            $whereOrder['create_date'] = array('between', array(strtotime($create_date. '-01 00:00:00'), strtotime($create_date. '-31 23:59:59')));
        }
        $whereOrder['actual_amount'] = ['egt', 1];
        $wechatAccountData = db('wechat_account')->field('name')->order('id desc')->select();
        $orderData = db('order')
        ->field('pay_status, relate_key, create_date')
        ->where($whereOrder)->select();
        $num = 0;
        foreach ($wechatAccountData as $k => $v) {
            for ($i=1; $i <= 31 ; $i++) {
                $num = $i;
                if(!isset($wechatAccountData[$k]['count_'.$num])) $wechatAccountData[$k]['count_'.$num] = 0;
                if(!isset($wechatAccountData[$k]['successCount_'.$num])) $wechatAccountData[$k]['successCount_'.$num] = 0;
                $num2 = $i < 10 ? '0'.$i : $i;
                if(date('Y-m-d') == $create_date. '-'.$num2){
                    break;
                }
            }
        }
        foreach ($wechatAccountData as $k => $v) {
            foreach ($orderData as $k2 => $v2) {
                if($v['name'] == $v2['relate_key']){
                    $arr = array_keys($v);
                    $day = ltrim(date('d', $v2['create_date']), '0');
                    if(in_array($v2['pay_status'], [2, 3, 4])){
                        if(in_array('successCount_'.$day, $arr)){
                            $wechatAccountData[$k]['successCount_'.$day]++;
                        }
                    }
                    if(in_array('count_'.$day, $arr)){
                        $wechatAccountData[$k]['count_'.$day]++;
                    }
                }
            }
        }
        // dump($wechatAccountData);
        //回显HTML数据
        $showData = [
            'create_date' => $create_date,
        ];
        return view('wechat2', compact('list', 'wechatAccountData', 'showData', 'num'));
    }

    public function channel(){
        //订单表
        $whereOrder = [];
        // 判断每1天的商户统计数据
        $create_date = input('create_date', date('Y-m-d'));
        if($create_date){
            $whereOrder['create_date'] = array('between', array(strtotime($create_date. ' 00:00:00'), strtotime($create_date. ' 23:59:59')));
        }
        // $whereOrder['pay_status'] = array('in', array(2, 3, 4));
        //判断每1天的商户统计数据
        $orderData = db('order')
        ->field('channel_id, actual_amount, pay_status, create_date, success_date')
        ->where($whereOrder)->select();

        $changeData = db('channel')->order('id desc')->select();
        foreach ($changeData as $k => $v) {
            if(!isset($changeData[$k]['successMoney'])) $changeData[$k]['successMoney'] = 0;
            if(!isset($changeData[$k]['successCount'])) $changeData[$k]['successCount'] = 0;
            if(!isset($changeData[$k]['count'])) $changeData[$k]['count'] = 0;
            foreach ($orderData as $k2 => $v2) {
                if($v['id'] == $v2['channel_id']){
                    for ($i=0; $i < 24 ; $i++) {
                        if(!isset($changeData[$k]['count_'.$i])) $changeData[$k]['count_'.$i] = 0;
                        if(!isset($changeData[$k]['successCount_'.$i])) $changeData[$k]['successCount_'.$i] = 0;
                        if(in_array($v2['pay_status'], [2, 3, 4])){
                            if(date('H', $v2['create_date']) == $i){
                                $changeData[$k]['successCount_'.$i]++;
                            }
                        }
                        if(date('H', $v2['create_date']) == $i){
                            $changeData[$k]['count_'.$i]++;
                        }
                    }
                    if(in_array($v2['pay_status'], [2, 3, 4])){
                        $changeData[$k]['successMoney'] += (float)$v2['actual_amount'];
                        $changeData[$k]['successCount']++;
                    }
                    $changeData[$k]['count']++;
                }
            }
        }

        $dayArr = array_column($changeData, 'successMoney');
        if($dayArr) array_multisort($dayArr, SORT_DESC, $changeData);
        // dump($changeData);die;
        //回显HTML数据
        $showData = [
            'create_date' => $create_date,
        ];
        return view('channel', compact('list', 'changeData', 'showData'));
    }

    public function province_city()
    {
        $where = [];
        $channel_id = input('channel_id', '');
        if($channel_id){
            $where['channel_id'] = $channel_id;
        }
        //处理订单创建时间范围
        $create_time = input('create_time', date('Y-m-d'). ' 00:00:00'. ' | '. date('Y-m-d'). ' 23:59:59');
        $arr_create_time = explode('|', $create_time);
        if($arr_create_time && count($arr_create_time) == 2){
            if(count($arr_create_time) == 2){
                $where['create_date'] = array('between', array(strtotime($arr_create_time[0]), strtotime($arr_create_time[1])));
            }
        }
        //处理订单状态
        $pay_status = input('pay_status', '');
        if($pay_status){
            $where['pay_status'] = $pay_status;
        }
        $where['access_ip'] = ['neq', ''];
        //统计订单
        $orderData = db('order')->field('access_ip')->where($where)->select();
        $province_city_count = [];
        if($orderData){
            $access_ip_arr = array_column($orderData, 'access_ip');
            $access_ip_arr = array_unique($access_ip_arr);
            // dump($province_city_arr);
            $province_city_arr = [];
            foreach ($access_ip_arr as $k => $v) {
                $province_city = ip_to_city($v, false);
                if(strpos($province_city, '省') !== false && strpos($province_city, '市') !== false){
                    $arr = explode('省', $province_city);
                    $province_city = $arr[0]. '省';
                }else if(strpos($province_city, '市') !== false){
                    $arr = explode('市', $province_city);
                    $province_city = $arr[0]. '市';
                }else if(strpos($province_city, '区') !== false){
                    $arr = explode('区', $province_city);
                    $province_city = $arr[0]. '区';
                }
                $province_city_arr[] = $province_city;
            }
            $allTotal = count($province_city_arr);
            foreach ($province_city_arr as $k => $v) {
                if(!isset($province_city_count[$k]['province_city'])) {
                    $province_city_count[$v]['province_city'] = $v;
                }
                if(!isset($province_city_count[$v]['num'])) {
                    $province_city_count[$v]['num'] = 1;
                }else{
                    $province_city_count[$v]['num']++;
                }
            }
            $province_city_count['其他']['province_city'] = '其他';
            $province_city_count['其他']['num'] = 0;
            foreach ($province_city_count as $k => $v) {
                if($v['num'] / $allTotal <= 0.01){
                    $province_city_count['其他']['num'] += $v['num'];
                }
            }
            foreach ($province_city_count as $k => $v) {
                $province_city_count[$k]['province_city'] = $v['province_city']. '（'. bcadd($v['num'] * 100/$allTotal, 0, 2) .'%）';
            }
            foreach ($province_city_count as $k => $v) {
                if($v['num'] / $allTotal <= 0.01){
                    unset($province_city_count[$k]);
                }
            }
        }
        //获取全部通道数据
        $channelData = db('channel')->order('id desc')->select();
        //回显HTML数据
        $showData = [
            'channel_id' => $channel_id,
            'create_time' => $create_time,
            'pay_status' => $pay_status,
        ];
        return view('province_city', compact('province_city_count', 'showData', 'channelData'));
    }
    public function member(){
        //订单表
        $whereOrder = [];
        //判断每1天的商户统计数据
        $create_date = input('create_date', date('Y-m-d'));
        if($create_date){
            $whereOrder['create_date'] = array('between', array(strtotime($create_date), strtotime($create_date. ' 23:59:59')));
        }
        $whereOrder['pay_status'] = array('in', array(2, 3, 4));
        //判断每1天的商户统计数据
        $orderData = db('order')
        ->field('member_id, amount, platform_poundage, agent_poundage, passage_poundage, income_amount')
        ->where($whereOrder)->select();
        //资金变动表
        $moneyChangeData = db('money_change')->field('member_id, change_type, change_money')->where([
            'create_date' => array('between', array(strtotime($create_date), strtotime($create_date. ' 23:59:59'))),
            'change_type' => array('in', array(13, 23)), //13：下发驳回~  23：下发申请
        ])->select();
        //商户表
        $whereMember = [];
        //判断商户号
        $member_id = input('member_id', '');
        if($member_id){
            $whereMember['member_id'] = $member_id;
        }
        //每页显示20条数据
        $perCount = 9999;
        $list = Member::field('member_id, nickname, balance')->where($whereMember)->order('balance desc')
        ->paginate($perCount, false, ['query'=>input()])
        ->each(function($item, $key) use($orderData, $moneyChangeData){

            $item->total_money = 0;
            $item->agent_deposit = 0;
            $item->passage_deposit = 0;
            $item->platform_deposit = 0;
            $item->income_deposit = 0;
            foreach ($orderData as $k2 => $v2) {
                if($item->member_id == $v2['member_id']){
                    $item->total_money += (float)$v2['amount'];
                    $item->agent_deposit += (float)$v2['agent_poundage'];
                    $item->passage_deposit += (float)$v2['passage_poundage'];
                    $item->platform_deposit += (float)$v2['platform_poundage'];
                    $item->income_deposit += (float)$v2['income_amount'];
                }
            }
            $item->issue_money = 0;
            foreach ($moneyChangeData as $k2 => $v2) {
                if($item->member_id == $v2['member_id']){
                    if($v2['change_type'] == 23){
                        $item->issue_money += (float)$v2['change_money'];
                    }
                    if($v2['change_type'] == 13){
                        $item->issue_money -= (float)$v2['change_money'];
                    }
                }
            }
            if($item->total_money <= 0){
                return 'delete';
            }

        });
        $memberData = $list->toArray()['data'];
        //回显HTML数据
        $showData = [
            'create_date' => $create_date,
            'member_id' => $member_id,
        ];
        return view('member', compact('list', 'memberData', 'showData'));
    }

    public function commission(){
        //订单表
        $whereOrder = [];
        // 判断每1天的商户统计数据
        $success_date = input('success_date', date('Y-m'));
        if($success_date){
            $whereOrder['success_date'] = array('between', array(strtotime($success_date), strtotime("+1 months", strtotime($success_date))));
        }
        $whereOrder['pay_status'] = array('in', array(2, 3, 4));
        //判断每1天的商户统计数据
        $orderData = db('order')
        ->field('channel_id, actual_amount, pay_status, success_date')
        ->where($whereOrder)->select();
        // dump($orderData);die;

        $changeData = db('channel')->select();
        $innerMoney = 0;
        $outMoney = 0;
        foreach ($orderData as $k => $v) {
            if(!isset($orderData[$k]['is_inner'])) $orderData[$k]['is_inner'] = 0;
            foreach ($changeData as $k2 => $v2) {
                if($v['channel_id'] == $v2['id']){
                    $orderData[$k]['is_inner'] = $v2['is_inner'];
                }
            }
        }
        $moneyData = [];
        $moneyData['0']['innerMoney'] = 0;
        $moneyData['0']['outMoney'] = 0;
        $moneyData['0']['day'] = date('Ym', strtotime($success_date));
        //非当月检测31天的数据，当月只检测当天之前的数据
        $todayNum = $success_date == date('Y-m') ? date('d') : 31;
        foreach ($orderData as $k => $v) {
            for ($i=1; $i <= $todayNum ; $i++) {
                $num = $i;
                $num2 = $num < 10 ? '0' .$num : $num;
                $day = $success_date. '-'. $num2;
                if(!isset($moneyData[$i]['innerMoney'])) $moneyData[$i]['innerMoney'] = 0;
                if(!isset($moneyData[$i]['outMoney'])) $moneyData[$i]['outMoney'] = 0;
                if(!isset($moneyData[$i]['day'])) $moneyData[$i]['day'] = $day;
                if(in_array($v['pay_status'], [2, 3, 4])){
                    if(date('Y-m-d', $v['success_date']) == $day){
                        if($v['is_inner'] == 1){
                            $moneyData['0']['innerMoney'] += (float)$v['actual_amount'];
                            $moneyData[$i]['innerMoney'] += (float)$v['actual_amount'];
                        }else{
                            $moneyData['0']['outMoney'] += (float)$v['actual_amount'];
                            $moneyData[$i]['outMoney'] += (float)$v['actual_amount'];
                        }
                    }
                }
            }
        }
        $dayArr = array_column($moneyData, 'day');
        if($dayArr) array_multisort($dayArr, SORT_DESC, $moneyData);
        //回显HTML数据
        $showData = [
            'success_date' => $success_date,
        ];
        return view('commission', compact('list', 'moneyData', 'showData'));
    }

    public function getExcel(){
        $data = input();
        $condition = [];

        isset($data['created_time'])?$condition['sta.created_time'] = strtotime($data['created_time']):'';
        isset($data['member_id'])&&$data['member_id']?$condition['sta.member_id'] = $data['member_id']:'';
        $data = db('statistics')
            ->alias('sta')
            ->field('member.nickname,sta.member_id,sta.total_money,sta.issue_money,member.balance,sta.created_time')
            ->join('member','member.member_id = sta.member_id')
            ->where($condition)
            ->select();

        foreach ($data AS $key => $value){
            $data[$key]['created_time'] = date('Y-m-d',$value['created_time']);
        }

        $title = ['商户昵称','商户号','入金金额','下发金额','剩余金额','日期'];
        exportExcel($title,$data,'ksk商户报表');
    }
}
