<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/1
 * Time: 0:27
 */

namespace app\admin2020\model;


class Admin extends Common
{
    public function role()
    {
        return $this->belongsTo('role', 'role_id', 'id');
    }

    public static function checkAdmin(){
        $username = input('username', '', 'trim');
        if(!$username)  showmessage('账户不能为空！');
        $password = input('password', '', 'trim');
        if(!$password) showmessage('密码不能为空！');
        $data = ['username' => $username, 'password' => $password];
        //获得Md5密码
        $passwordMd5 = self::getMd5Password($data);
        $info = self::get(['username' => $username]);
        //记录登录日志（成功与失败都记录）
        $ip = getClientIP();
        $ipInfo = db('ip')->where(['ip' => $ip])->find();
        if($ipInfo) showmessage('您的IP已进入系统黑名单，请联系技术解除！！！');
        //登录错误次数过多，请联系客服解除
        if($info['login_error_count'] >= 5) {
            if($info['status'] == 1) self::where(['username' => $username])->setField('status', 0);
            showmessage('登录错误次数过多，请联系技术解除！');
        }
        //所有尝试登录的账户，不管失败或成功都对登录次数+1
        $log_login = db('log_login')->where(['username' => $username])->order('id desc')->find();
        $time = time();
        $logLoginData = [
            'username' => $username,
            'ip' => $ip,
            'module' => 'admin',
            'result' => ($info && $info['password'] == $passwordMd5) ? 1 : 2,  // 1：成功   2：失败
            'login_time' => $time,
            'login_count' => $log_login ? $log_login['login_count'] + 1 : 1,
        ];
        db('log_login')->insert($logLoginData);
        $errorCount = db('log_login')->where([
            'login_time' => array('between', array($time - 300, $time)),
            'ip' => $ip,
            'result' => 2,
        ])->count();
        if($errorCount >= 10){
            db('ip')->insert([
                'ip' => $ip,
                'create_date' => $time,
            ]);
            showmessage('您的IP已进入系统黑名单，请联系技术解除！');
        }
        //合法账户密码错误
        if($info && $info['password'] != $passwordMd5) {
            //合法账户错误登录次数+1
            self::where(['username' => $username])->setInc('login_error_count', 1);
            showmessage('密码错误，您还可以尝试-'.(5-$info['login_error_count']). '次');
        }else if(!$info) {
            //非法账户
            showmessage('账户不存在！您的IP已被系统记录，请谨慎操作！');
        }
        //登录成功，对错误登录次数清零
        self::where(['username' => $username])->setField(['login_error_count' => 0, 'ip' => $ip]);
        return $info;
    }

    public static function getMd5Password($data)
    {
        return md5( md5( $data['username'] .config('serect_key'). $data['password'] ) );
    }

    public static function setAdmin()
    {
        $id = input('id', 0);
        $role_id = input('role_id', '');
        $username = input('username', '');
        $password = input('password', '');
        $password2 = input('password2', '');
        $primary = [];
        if($id){
            //修改
            $password_old = input('password_old', '');
            if($password_old){
                $data = [
                    'username' => $username,
                    'password' => $password_old,
                ];
                //更新的时候判断原始密码是否正确
                $data['password'] = self::getMd5Password($data);
                $adminInfo = self::get($data);
                if(!$adminInfo) showmessage('旧密码有误！');
                if(!$username) showmessage('账户不能为空！');
                if(!$password) showmessage('新密码不能为空！');
                if($password != $password2) showmessage('两次密码不一致！');
                //新密码组合
                $data['password'] = $password;
                $data['password'] = self::getMd5Password($data);
                //不允许修改账户名
                unset($data['username']);
            }
            $primary['id'] = $id;
        }else{
            if(!$username) showmessage('账户不能为空！');
            $data = ['username' => $username];
            $adminInfo = self::get($data);
            if($adminInfo) showmessage('账户已存在！');
            if(!$password) $password = '123456'; //默认密码固定 123456
            // if(!$password) showmessage('新密码不能为空！');
            // if($password != $password2) showmessage('两次密码不一致！');
            //添加
            $data = [
                'username' => $username,
                'password' => $password,
            ];
            $data['create_date'] = time();
            $data['password'] = self::getMd5Password($data);
        }
        $role_id = input('role_id', '');
        if(!$role_id) showmessage('角色必选！');
        $data['role_id'] = $role_id;

        $is_inner = input('is_inner', '2');
        $data['is_inner'] = $is_inner;

        $rent_rate = input('rent_rate', '0.0000');
        $data['rent_rate'] = $rent_rate;

        $channel_id = input('channel_id', '');
        $data['channel_id'] = $channel_id;

        $channel_rate = input('channel_rate', '0.0000');
        $data['channel_rate'] = $channel_rate;

        //删除非法超级管理员创建
        if(ROLE_ID != 1 && $data['role_id'] == 1){
            unset($data['role_id']);
        }

        $result = (new self)->save($data, $primary);
        if(!$result) return false;
        return true;
    }


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
            'username' => USER_NAME,
            'password' => $password_old,
        ];
        //更新的时候判断原始密码是否正确
        $data['password'] = self::getMd5Password($data);
        $adminInfo = self::get($data);
        if(!$adminInfo) showmessage('旧密码有误！');
        //新密码组合
        $data['password'] = $password;
        $data['password'] = self::getMd5Password($data);
        //不允许修改账户名
        unset($data['username']);

        $primary['id'] = ADMIN_ID;
        $result = (new self)->save($data, $primary);
        if(!$result) return false;
        return true;
    }
}