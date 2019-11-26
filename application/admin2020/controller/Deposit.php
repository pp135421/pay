<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/1
 * Time: 23:21
 */

namespace app\admin2020\controller;
use app\admin2020\model\Deposit AS DepositModel;

class Deposit extends Common
{
    public function index()
    {

        $where = [];
        $member_id = input('member_id', '');
        if($member_id){
            $where['member_id'] = $member_id;
        }
        $status = input('status', '');
        if($status){
            $where['status'] = $status;
        }
        $submit_type = input('submit_type', '');
        if($submit_type){
            $where['submit_type'] = $submit_type;
        }
        $deposit_type = input('deposit_type', '');
        if($deposit_type){
            $where['deposit_type'] = $deposit_type;
        }
        $create_time = input('create_time',  date('Y-m-d 00:00:00'). ' | ' .date('Y-m-d 23:59:59'));
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
        //每页显示30条数据
        $perCount = 999999;
        $list = DepositModel::where($where)->order('id desc')->paginate($perCount, false, ['query'=>input()]);
        $list2 = $list->toArray()['data'];
        $depositAllMoney = 0;
        $count = 0;
        $nodoCount = 0;
        $dealingCount = 0;
        $successCount = 0;
        $refuseCount = 0;
        $memberData = db('member')->select();
        foreach ($list2 as $k => $v) {
            if($v['return_json']){
                $list2[$k]['return_json'] = json_decode($v['return_json'], true);
                if($list2[$k]['return_json'] && isset($list2[$k]['return_json']['Err_Code']) && $list2[$k]['return_json']['Result'] == 'ERROR'){
                    $list2[$k]['return_json']['msg'] = '代付失败！';
                }else if($list2[$k]['return_json'] && isset($list2[$k]['return_json']['Err_Code']) && $list2[$k]['return_json']['Result'] == 'WAIT_PAY'){
                    $list2[$k]['return_json']['msg'] = '处理中！';
                }
            }
            if(!isset($list2[$k]['nickname'])) $list2[$k]['nickname'] = '';
            if($v['status'] == 1){
                $nodoCount++;
            }
            if($v['status'] == 2){
                $dealingCount++;
            }
            if($v['status'] == 3){
                $successCount++;
                $depositAllMoney += $v['amount'];
            }
            if($v['status'] == 4){
                $refuseCount++;
            }
            $count++;
            foreach ($memberData as $k2 => $v2) {
                if($v['member_id'] == $v2['member_id']){
                    $list2[$k]['nickname'] = $v2['nickname'];
                }
            }
        }
        //回显HTML数据
        $showData = [
            'create_time' => $create_time,
            'success_time' => $success_time,
            'member_id' => $member_id,
            'status' => $status,
            'depositAllMoney' => $depositAllMoney,
            'count' => $count,
            'nodoCount' => $nodoCount,
            'dealingCount' => $dealingCount,
            'successCount' => $successCount,
            'refuseCount' => $refuseCount,
            'submit_type' => $submit_type,
            'deposit_type' => $deposit_type,
        ];
        return view('index', compact('list', 'list2',  'showData'));
    }

    /*
        结算编辑
    */
    public function deposit_edit(){
        $money = round(input('money'),2);
        $deposit_id = input('id');
        $deposit_order_id = input('deposit_order_id');
        $depositInfo = db('deposit')->where(['deposit_order_id' => $deposit_order_id])->find();
        if($depositInfo['return_json']){
            $depositInfo['return_json'] = json_decode($depositInfo['return_json'], true);
        }
        $member_id = input('member_id');
        $status = input('status');
        if(request()->isPost()){
            $depostData = $_POST;
            $depostData['status']?'':ajaxReturn(2,'结算方式未选择');
            depositModel::deposit_edit($depostData);
        }
        $channel = db('channel')->where([
            'channel_money' => array('gt', 0),
        ])->order('channel_money desc')->select();
        return view('deposit_edit',compact('money','deposit_id', 'depositInfo', 'channel', 'member_id', 'status'));
    }
}