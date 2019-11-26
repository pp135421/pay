<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/28
 * Time: 20:27
 */

namespace app\index2020\controller;

use think\Controller;
use app\index2020\model\Common AS CommonModel;
class Common extends Controller
{
    public function __construct()
    {
        parent::__construct();
        //禁止nginx代理服务器过来的数据
        // if($_SERVER['REMOTE_ADDR'] == config('nginx_ip')) die('非法访问！');
        $ip = getClientIP();
        $blacklistInfo = db('blacklist')->where(['ip' => $ip])->find();
        if($blacklistInfo) showmessage('ip：'. $ip. '，已加入黑名单！！！');
        $url2 = strtolower(request()->module(). '/'. request()->controller());
        $url = strtolower($url2. '/'. request()->action());
        $this->_member = session('_member');
        if(!$this->_member) $this->redirect('index2020/login/index');
        if(!defined('MEMBER_ID')) define('MEMBER_ID', $this->_member['member_id']);
        if(!defined('MID')) define('MID', $this->_member['id']);
        if(!defined('USERNAME')) define('USERNAME', $this->_member['username']);
        if(!defined('AGENT')) define('AGENT', $this->_member['agent']);
        $memberInfo = db('member')->find(MID);
        if(!$this->_member) $this->redirect("/index2020/login/logout");
        $memberInfo['noPwd'] = $this->_member['noPwd'];
        $this->_member = $memberInfo;
        if($url != 'index2020/index/index' && $this->_member['noPwd'] == 0){
            if($url != 'index2020/member/pwd' && $this->_member['password'] == setPwdSalt('123456')){
                $this->redirect("/index2020/member/pwd");
                die;
            }
            if($url != 'index2020/member/pwd' && $url != 'index2020/member/paypwd' && $this->_member['paypwd'] == setPwdSalt('123456')){
                $this->redirect("/index2020/member/paypwd");
                die;
            }
        }
    }
}