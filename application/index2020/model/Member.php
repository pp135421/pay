<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/1
 * Time: 17:08
 */

namespace app\index2020\model;


class Member extends Common
{
    public static function modifyPwd()
    {
        $password_old = input('password_old', '');
        if(!$password_old) showmessage('旧密码不能为空！');
        $password = input('password', '');
        if(!$password) showmessage('新密码不能为空！');
        $password2 = input('password2', '');
        if(!$password2) showmessage('确认密码不能为空！');
        if($password != $password2) showmessage('两次密码不一致！');

        $data = [
            'username' => USERNAME,
            'password' => setPwdSalt($password_old),
        ];
        //更新的时候判断原始密码是否正确
        $adminInfo = self::get($data);
        if(!$adminInfo) showmessage('旧密码有误！');
        //新密码组合
        $data['password'] = setPwdSalt($password);
        //不允许修改账户名
        unset($data['username']);

        $primary['id'] = MID;
        $result = (new self)->save($data, $primary);
        if(!$result) return false;
        return true;
    }

    public static function modifyPayPwd()
    {
        $password_old = input('password_old', '');
        if(!$password_old) showmessage('旧密码不能为空！');
        $password = input('password', '');
        if(!$password) showmessage('新密码不能为空！');
        $password2 = input('password2', '');
        if(!$password2) showmessage('确认密码不能为空！');
        if($password != $password2) showmessage('两次密码不一致！');

        $data = [
            'username' => USERNAME,
            'paypwd' => setPwdSalt($password_old),
        ];
        //更新的时候判断原始密码是否正确
        $adminInfo = self::get($data);
        if(!$adminInfo) showmessage('旧密码有误！');
        //新密码组合
        $data['paypwd'] = setPwdSalt($password);
        //不允许修改账户名
        unset($data['username']);

        $primary['id'] = MID;
        $result = (new self)->save($data, $primary);
        if(!$result) return false;
        return true;
    }

    //商户编辑
    public static function member_edit($params)
    {
        $password = $params['password'];
        $paypwd = $params['paypwd'];

        $condition = [];
        $condition['id'] = MID;

        //验证密码是否重复
        if($password)$condition['password'] = setPwdSalt($password);
        if($paypwd)$condition['paypwd'] = setPwdSalt($paypwd);
        if($password || $paypwd){
            $res = self::where($condition)->find();
            if($res)ajaxReturn(2,'新密码不能与原密码相同');
        }

        unset($condition);
        $saveData = [];
        $params['nickname']?$saveData['nickname'] = $params['nickname']:'';
        $params['password']?$saveData['password'] = setPwdSalt($params['password']):'';
        $params['paypwd']?$saveData['paypwd'] = setPwdSalt($params['paypwd']):'';
        (new self)->save($saveData,['id' => MID]);//更新用户信息
        ajaxReturn(1,'更新成功');
    }

    public static function checkMember(){
        $username = input('username', '', 'trim');
        if(!$username)  showmessage('账户不能为空！');
        $password = input('password', '', 'trim');
        if(!$password) showmessage('密码不能为空！');
        //获得Md5密码
        $passwordMd5 = setPwdSalt($password);
        $ip = getClientIP();
        $ipInfo = db('ip')->where(['ip' => $ip])->find();
        if($ipInfo) showmessage('您的IP已进入系统黑名单，请联系客服解除！');
        $info = self::get(['username' => $username]);
        //登录错误次数过多，请联系客服解除
        if($info['login_error_count'] >= 5)  showmessage('登录错误次数过多，请联系客服解除！');
        //所有尝试登录的账户，不管失败或成功都对登录次数+1
        $log_login = db('log_login')->where(['username' => $username])->order('id desc')->find();
        $time = time();
        //记录登录日志（成功与失败都记录）
        $logLoginData = [
            'username' => $username,
            'ip' => $ip,
            'module' => 'index',
            'result' => ($info && $info['password'] == $passwordMd5) ? 1 : 2,  // 1：成功   2：失败
            'login_time' => $time,
            'login_count' => $log_login ? $log_login['login_count'] + 1 : 1,
        ];
        db('log_login')->insert($logLoginData);
        //合法账户密码错误
        if($info && $info['password'] != $passwordMd5) {
            //合法账户错误登录次数+1
            self::where(['username' => $username])->setInc('login_error_count', 1);
            showmessage('密码错误，您还可以尝试-'.(5-$info['login_error_count']). '次');
            if($info['login_error_count'] >= 5){
                db('ip')->insert(['ip' => $ip]);
            }
        }else if(!$info) {
            //非法账户
            $errorCount = db('log_login')->where([
                'login_time' => array('between', array($time - 300, $time)),
                'ip' => $ip,
                'result' => 2,
            ])->count();
            if($errorCount >= 10){
                db('ip')->insert(['ip' => $ip]);
                showmessage('您的IP已进入系统黑名单，请联系客服解除！');
            }
            showmessage('账户不存在！您的IP已被系统记录，请谨慎操作！');
        }
        //登录成功，对错误登录次数清零
        self::where(['username' => $username])->setField('login_error_count', 0);
        return $info;
    }

    //商户列表
    public static function memberList(){
        $res = self::where('pid', MID)->order('create_date','desc')->paginate(15);
        // DUMP($res);die;
        $list = $res->toArray()['data'];
        $memberPid = array_column($list,'pid');//商户PID数组
        $memberIds = array_column($list,'id');//商户ID数组
        $agent = db('member')->field('id,nickname')->where('id','in',$memberPid)->select();//商户ID上级代理信息
        $member_channel = db('member_channel')
            ->alias('mc')
            ->field('mc.member_id,c.name_cn,c.type_name')
            ->join('channel c','c.id = mc.channel_id')
            ->where('mc.member_id','in',$memberIds)
            ->select();

        foreach ($list AS $key=>$value){

            foreach ($agent AS $ke=>$vo){
                if($value['pid']==$vo['id']){
                    $list[$key]['agentname'] = $vo['nickname'];
                }
            }

            foreach ($member_channel AS $k=>$v){
                if($value['id']==$v['member_id']){
                    if($v['type_name'] == 'alipay'){
                        $list[$key]['alipayname'] = $v['name_cn'];
                    }elseif($v['type_name'] == 'wechat'){
                        $list[$key]['wechatname'] = $v['name_cn'];
                    }
                }
            }
            isset($list[$key]['agentname'])?'':$list[$key]['agentname']='';
            isset($list[$key]['alipayname'])?'':$list[$key]['alipayname']='';
            isset($list[$key]['wechatname'])?'':$list[$key]['wechatname']='';

        }

        $result = [
            'page' => $res,
            'list' => $list,
        ];
        return $result;
    }

    //商户信息修改
    public static function member_edit_under($params = []){

        $data = [
            'username' => $params['username'],
            'nickname' => $params['nickname'],
            'deposit_amount' => $params['deposit_amount'],
            'pid' => MID,
        ];

        $res = self::where('username',$params['username'])->find();
        if($res)ajaxReturn(2,'账户名称已存在，请重新输入');

        $data['password']   = setPwdSalt($params['password']);//登录密码
        $data['paypwd']     = setPwdSalt($params['paypwd']);//支付密码
        $data['apikey']     = md5(setPwdSalt($params['paypwd'].rand(10000,99999)));//支付密钥
        $data['create_date'] = date('Y-m-d H:i:s');
        $data['member_id']  = chr(rand(65,90)).date('m').time();//生成商户号

        $res = (new self)->save($data);
        $msg ='新增用户';


        if($res)ajaxReturn(1,$msg.'成功');
        ajaxReturn(2,$msg.'失败');
    }

}