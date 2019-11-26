<?php
namespace app\admin2020\controller;

use think\Controller;
use app\admin2020\model\Admin;

class Login extends Controller
{
    public function __construct()
    {
        //禁止nginx代理服务器过来的数据
        if($_SERVER['REMOTE_ADDR'] == config('nginx_ip')) die('非法访问！');
    }

    public function index()
    {
        if(request()->isGet()){
            return view();
        }
        //检验账户密码的合法性
        $adminInfo = Admin::checkAdmin();
        if(!$adminInfo) showmessage('账号或密码错误');
        if($adminInfo['status'] != 1) showmessage('账号已被禁用');
        //取得当前账户的权限
        $roleInfo = db('role')->find($adminInfo['role_id']);
        $adminInfo['rolename'] = $roleInfo['rolename'];
        //对超级管路员特殊处理
        if($adminInfo['role_id'] == 1){
            $adminInfo['rule_ids'] = db('rule')->select();
        }else{
            $adminInfo['rule_ids'] = db('rule')->where("id in ({$roleInfo['rule_id']})")->select();
        }
        session('_admin', $adminInfo);
        //session('_username', $adminInfo['username']);
        showmessage('登录成功', 1);
    }

    public function logout()
    {
        session('_admin', null);
        $this->redirect('admin2020/login/index');
    }
}
