<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/1
 * Time: 21:54
 */

namespace app\index2020\controller;

use app\index2020\model\Deposit AS depositModel;
use app\api\controller\Common as CommonModel;

class Deposit extends Common
{
    public function index()
    {
        $where = [];
        $where['member_id'] = MEMBER_ID;
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
        $perCount = 30;
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
        return view('index', compact('list', 'list2', 'showData'));
    }

    public function deposit()
    {
        if(request()->isGet()){
            $bankCardData = db('bank_card')->where([
                'member_id' => MEMBER_ID,
                'status' => 1,
            ])->select();
            $memberInfo = db('member')->where(['member_id' => MEMBER_ID])->find();
            $drawingsInfo = db('drawings')->find(1);
            return view('deposit', compact('bankCardData', 'memberInfo', 'drawingsInfo'));
        }
        $paypwd = input('paypwd', '');
        if(!$paypwd) showmessage('支付密码不能为空');
        $depoist_money = input('depoist_money/f', 0);
        if(!$depoist_money) showmessage('提现金额不能为空');
        $card_number = input('card_number', '');
        if(!$card_number) showmessage('银行卡号未选择');
        //提现规则
        $drawingsInfo = db('drawings')->find(1);
        if($depoist_money > $drawingsInfo['max_money']) showmessage('不能超过提现最高金额');
        if($depoist_money < $drawingsInfo['min_money']) showmessage('不能低于提现最少金额');
        //获取商户对应数据
        $memberInfo = db('member')->lock(true)->where([
            'member_id' => MEMBER_ID,
        ])->find();
        if(!$memberInfo) showmessage('商户号：'. MEMBER_ID. '不存在');
        if($memberInfo['paypwd'] != setPwdSalt($paypwd)) showmessage('支付密码错误');
        //商户绑定的银行卡数据
        $bankCardInfo = db('bank_card')->where([
            'member_id' => MEMBER_ID,
            'card_number' => $card_number,
        ])->find();
        if(!$bankCardInfo) showmessage('找不到商户对应的银行卡号');
        $requestData = [
            'member_id' => MEMBER_ID,
            'amount' => $depoist_money,
            'bank_name' => $bankCardInfo['bankname'],
            'bank_branch_name' => $bankCardInfo['bankzhiname'],
            'bank_account' => $bankCardInfo['bank_account'],
            'bank_card_no' => $bankCardInfo['card_number'],
            'province' => $bankCardInfo['province'],
            'city' => $bankCardInfo['city'],
        ];
        //创建下发数据  createDeposit($memberInfo, $requestData, $deposit_type, $submit_type)
        CommonModel::createDeposit($memberInfo, $requestData, $memberInfo['deposit_type'], 1); //1->手动
    }

}