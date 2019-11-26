<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 13:08
 */

namespace app\index2020\controller;
use app\index2020\model\Order as OrderModel;
use app\index2020\model\Member;
use think\Controller;
use think\Db;

class Order extends Common
{
    public  function index()
    {
        $where = [];
        //处理订单创建时间范围
        $create_time = input('create_time', date('Y-m-d 00:00:00'). ' | '. date('Y-m-d 23:59:59'));
        $arr_create_time = explode('|', $create_time);
        if($arr_create_time && count($arr_create_time) == 2){
            if(count($arr_create_time) == 2){
                $where['create_date'] = array('between', array(strtotime($arr_create_time[0]), strtotime($arr_create_time[1])));
            }
        }

        //处理订单成功时间范围
        $success_time = input('success_time', '');
        $arr_success_time = explode('|', $success_time);
        if($arr_success_time && count($arr_success_time) == 2){
            if(count($arr_success_time) == 2){
                $where['success_date'] = array('between', array(strtotime($arr_success_time[0]), strtotime($arr_success_time[1])));
            }
        }

        //处理连接方式
        $access_type = input('access_type', '');
        if($access_type){
            if($access_type == 'H5'){
                $where['access_type'] = [['like' ,'%Android%'] ,['like' , '%iphone%'] ,'or'] ;
            }else if($access_type == 'Android' || $access_type == 'iphone'){
                $where['access_type'] = array('like', $access_type);
            }else if($access_type == 'wait'){
                $where['access_type'] = 'wait';
            }else if($access_type == 'PC'){
                $where['access_type'] = array('like', '%Win%');
            }
        }

        //处理订单状态
        $pay_status = input('pay_status', '');
        if($pay_status){
            $where['pay_status'] = $pay_status;
        }

        //处理通道
        $channel_id = input('channel_id', '');
        if($channel_id){
            $where['channel_id'] = $channel_id;
        }
        //处理接口
        $type_name = input('type_name', '');
        if($type_name){
            $where['type_name'] = $type_name;
        }

        //处理平台订单号或下游订单号或商户号
        $keyword = input('keyword', '', 'trim');
        if($keyword){
            if(strpos($keyword, config('platform_name_en')) !== false){
                    $where['platform_order_id'] = $keyword;
            }else{
                $where['submit_order_id'] = $keyword;
            }
        }

        $member_id = input('member_id', '');
        //对应商户号
        if(AGENT == 2 && MEMBER_ID && MID){
            $memberData = db('member')->field('member_id, nickname')->where(['pid' => MID])->select();
            $memberIdArr = array_column($memberData, 'member_id');
            if(in_array($member_id, $memberIdArr)){
                $where['member_id'] = $member_id;
            }else{
                $where['member_id'] = ['in', $memberIdArr];
            }
        }else if(MEMBER_ID){
            $memberData = db('member')->field('member_id, nickname')->select();
            $where['member_id'] = MEMBER_ID;
        }
        //每页显示30条数据
        $perCount = 30;
        $list = OrderModel::where($where)->order('id desc')->paginate($perCount, false, ['query'=>input()]);
        $list2 = $list->toArray()['data'];
        foreach ($list2 as $k => $v) {
            foreach ($memberData as $k2 => $v2) {
                if($v['member_id'] == $v2['member_id']){
                    $list2[$k]['nickname'] = $v2['nickname'];
                }
            }
        }
        $successMoney = 0;
        $orderData = OrderModel::field('amount, pay_status, member_id')->where($where)->order('id desc')->select();
        if(AGENT == 2 && MEMBER_ID && MID){
            foreach ($orderData as $k => $v) {
                if($member_id){
                    if($v['member_id'] == $member_id && in_array($v['pay_status'], [2, 3, 4])){
                        $successMoney += (float)$v['amount'];
                    }
                }else{
                    if(in_array($v['member_id'], $memberIdArr) && in_array($v['pay_status'], [2, 3, 4])){
                        $successMoney += (float)$v['amount'];
                    }
                }
            }
        }else{
            foreach ($memberData as $k => $v) {
                foreach ($orderData as $k2 => $v2) {
                    if($v['member_id'] == $v2['member_id'] && in_array($v2['pay_status'], [2, 3, 4])){
                        $successMoney += (float)$v2['amount'];
                    }
                }
            }
        }
        //获得当前表的全部条数
        $count = OrderModel::count();

        //获取全部通道数据
        $channelData = db('channel')->field('id,name_cn')->select();
        //回显HTML数据
        $showData = [
            'create_time' => $create_time,
            'success_time' => $success_time,
            'keyword' => $keyword,
            'pay_status' => $pay_status,
            'access_type' => $access_type,
            'channel_id' => $channel_id,
            'type_name' => $type_name,
            'member_id' => $member_id,
            'successMoney' => $successMoney,
        ];
        return view('index', compact('list', 'list2', 'showData', 'channelData', 'count', 'memberData'));
    }

    public function getExcel(){
        $where = [];
        //处理订单创建时间范围
        $create_time = input('create_time', '');
        $arr_create_time = explode('|', $create_time);
        if($arr_create_time && count($arr_create_time) == 2){
            if(count($arr_create_time) == 2){
                $where['create_date'] = array('between', array(strtotime($arr_create_time[0]), strtotime($arr_create_time[1])));
            }
        }

        //处理订单成功时间范围
        $success_time = input('success_time', '');
        $arr_success_time = explode('|', $success_time);
        if($arr_success_time && count($arr_success_time) == 2){
            if(count($arr_success_time) == 2){
                $where['success_date'] = array('between', array(strtotime($arr_success_time[0]), strtotime($arr_success_time[1])));
            }
        }

        //处理平台订单号或下游订单号或商户号
        $keyword = input('keyword', '');
        if($keyword){
            if(strpos($keyword, config('platform_name_en')) !== false){
                $where['platform_order_id'] = $keyword;
            }else if(preg_match('/^[A-Z][0-1][0-9]\d{10}$/', $keyword)){
                $where['member_id'] = $keyword;
            }else{
                $where['submit_order_id'] = $keyword;
            }
        }

        //处理连接方式
        $access_type = input('access_type', '');
        if($access_type){
            if($access_type == 'H5'){
                $where['access_type'] = [['like' ,'%Android%'] ,['like' , '%iphone%'] ,'or'] ;
            }else if($access_type == 'Android' || $access_type == 'iphone'){
                $where['access_type'] = array('like', $access_type);
            }else if($access_type == 'wait'){
                $where['access_type'] = 'wait';
            }else if($access_type == 'PC'){
                $where['access_type'] = array('like', '%Win%');
            }
        }

        //处理订单状态
        $pay_status = input('pay_status', '');
        if($pay_status){
            $where['pay_status'] = $pay_status;
        }

        //处理通道
        $channel_id = input('channel_id', '');
        if($channel_id){
            $where['channel_id'] = $channel_id;
        }
        //处理接口
        $type_name = input('type_name', '');
        if($type_name){
            $where['type_name'] = $type_name;
        }
        //对应商户号
        if(AGENT == 2 && MEMBER_ID && MID){
            $member_ids = db('member')->field('member_id')->where(['pid' => MID])->select();
            $member_ids = array_column($member_ids, 'member_id');
            $where['member_id'] = ['in', $member_ids];
        }else if(MEMBER_ID){
            $where['member_id'] = MEMBER_ID;
        }
        $data = db('order')->field('submit_order_id, member_id, amount, income_amount, platform_poundage, agent_poundage, passage_poundage, actual_amount, create_date, success_date, type_name, pay_status')
            ->where($where)->order('id desc')->select();
        foreach($data as $k => $v){
            //手续费计算
            $data[$k]['poundage'] = $v['platform_poundage'] + $v['agent_poundage'] + $v['passage_poundage'];
            unset($data[$k]['platform_poundage']);
            unset($data[$k]['agent_poundage']);
            unset($data[$k]['passage_poundage']);
            //时间转换
            $data[$k]['create_date'] = $v['create_date'] ? date('Y-m-d H:i:s', $v['create_date']) : '-';
            $data[$k]['success_date'] = $v['success_date'] ? date('Y-m-d H:i:s', $v['success_date']): '-';
            //支付状态
            $data[$k]['pay_status'] = pay_status2($v['pay_status']);
        }
        $title = ['订单号', '商户号', '订单金额', '实际入账金额', '实际付款金额', '创建时间', '成功时间', '接口名称', '支付状态', '手续费'];
        exportExcel($title, $data, 'ksk商户订单表_'. date('YmdHis'));
    }

}