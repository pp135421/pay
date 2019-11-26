<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/1
 * Time: 15:28
 */

namespace app\index2020\controller;

use app\index2020\model\Member AS MemberModel;
use app\admin2020\model\Member AS MemberModelAdmin;
use app\index2020\model\Bank;
class Member extends Common
{
    public function pwd()
    {
        if(request()->isGet()){
            return view('pwd');
        }
        // if(!ADMIN_ID)  showmessage('商户账户异常！');
        $result = MemberModel::modifyPwd();
        if(!$result) showmessage('修改失败！');
        // log_operation('修改密码成功');
        showmessage('修改成功！' , 1);
    }

    public function paypwd()
    {
        if(request()->isGet()){
            return view('paypwd');
        }
        // if(!ADMIN_ID)  showmessage('商户账户异常！');
        $result = MemberModel::modifyPayPwd();
        if(!$result) showmessage('修改失败！');
        // log_operation('修改密码成功');
        showmessage('修改成功！' , 1);
    }

    //银行卡列表展示
    public function bank(){
        $card_number = input('card_number','');
        $account = input('account','');
        $condition['member_id'] = MEMBER_ID;
        $card_number?$condition['card_number'] = $card_number:'';
        $account?$condition['bank_account'] = $account:'';

        $list = db('bank_card')->where($condition)->paginate(10);
        return view('bank',compact('list','card_number','account'));
    }

    //银行卡号编辑
    public function bank_edit(){
        $bank_id = input('bank_id');

        if(request()->isPost()){
            $bank = input();
            if(!$bank['account'])ajaxReturn(2,'开户人不能为空');
            if(!$bank['bank_code'])ajaxReturn(2,'银行名称不能为空');
            if(!$bank['bankzhiname'])ajaxReturn(2,'银行支行名称不能为空');
            if(!$bank['card_number'])ajaxReturn(2,'银行卡号不能为空');
            if(!$bank['province'])ajaxReturn(2,'省份未填写');
            if(!$bank['city'])ajaxReturn(2,'城市未填写');
            Bank::bank_edit($bank);
        }
        $banklist = db('bank')->select();//银行编码名称
        $list = db('bank_card')->where('id', $bank_id)->find();//银行编码名称
        return view('bank_edit',compact('banklist','bank_id','list'));
    }

    /*
        银行卡状态修改
    */
    public function bank_status(){
        $status = input('status');
        $bank_id = input('bank_id');
        Bank::changeStatus($bank_id,$status);
    }

    /*
        银行卡删除
    */
    public function bank_del(){
        $id = input('id','');
        Bank::del($id);
    }


    public function log_login()
    {
        $where = [];
        if(USERNAME){
            $where['username'] = USERNAME;
            $where['module'] = 'index';
        }
        $count = db('log_login')->count();
        $list = db('log_login')->where($where)->order('id desc')->paginate(15);
        return view('log_login', compact('count', 'list'));
    }

    public function agent()
    {
        if(AGENT != 2) die('非代理账户');
        $result = MemberModel::memberList();
        $page = $result['page'];
        $list = $result['list'];

        return view('agent',compact('page', 'list', 'memberNumber'));
    }
    /*
        商户信息编辑
    */
    public function member_edit_under(){
        if(AGENT != 2) die('非代理账户');
        $memberId = input('mid','');//商户ID
        $list =  MemberModel::where("id",$memberId)->find();

        if(request()->isPost()){
            $params['username'] = input('username','')?input('username'):ajaxReturn(2,'用户名不能为空');
            $params['deposit_amount'] = input('deposit_amount','')?input('deposit_amount'):'';
            $params['pid'] = MID;//代理ID
            $params['password'] = input('password','');
            $params['paypwd'] = input('paypwd','');
            $params['nickname'] = input('nickname','');//昵称
            $params['memberId'] = $memberId;//商户ID

            //新增商户时 密码不能为空
            if(!$memberId){
                $params['password']?'':ajaxReturn(2,'登录密码不能为空');
                $params['paypwd']?'':ajaxReturn(2,'支付密码不能为空');
            }
            MemberModel::member_edit_under($params);
        }

        return view('member_edit_under',compact("list",'memberId'));
    }
}