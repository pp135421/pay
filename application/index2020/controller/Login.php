<?php
namespace app\index2020\controller;

use think\Controller;
use app\index2020\model\Member;

class Login extends Controller
{
    public function __construct()
    {
        //禁止nginx代理服务器过来的数据
        // if($_SERVER['REMOTE_ADDR'] == config('nginx_ip')) die('非法访问！');
    }

    public function index()
    {
        //假设访问来源不是合法的内部后台地址跳转过来
        $flag = false;
        $member_id = input('member_id', 0);
        if(isset($_SERVER['HTTP_REFERER']) && $member_id){
            $url = str_replace('www.', '', parse_url($_SERVER['HTTP_REFERER'])['host']);
            $urlArr = explode('.', $url);
            if(count($urlArr) == 3 && $urlArr[0] != 'api' && $urlArr[1]. '.'. $urlArr[2] == config('legal_domin') ||
                count($urlArr) == 2 && $urlArr[0]. '.'. $urlArr[1] == config('legal_domin')){
                $flag = true;
            }
        }
        if(request()->isGet() && !$flag){
            return view();
        }
        //$flag为true代表是合法的内部后台跳转过来的
        if($flag && $member_id){
            //内部免密登录流程
            $memberInfo = Member::get(['member_id' => $member_id])->toArray();
            $memberInfo['noPwd'] = 1;
        }else{
            //正常的登录流程
            $memberInfo = Member::checkMember();
            if($memberInfo){
                $memberInfo = $memberInfo->toArray();
                $memberInfo['noPwd'] = 0;
            }
        }
        if(!$memberInfo) showmessage('账号或密码错误！');
        if($memberInfo['status'] != 1) showmessage('账号已被禁用！');
        $memberInfo['login_time'] = time();
        //正常的登录流程
        session('_member', $memberInfo);
        if(!$flag) showmessage('登录成功', 1);
        $this->redirect('/index2020/index/index');
    }

    public function logout()
    {
        session('_member', null);
        $this->redirect('index2020/login/index');
    }
}
