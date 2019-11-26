<?php 
namespace app\admin2020\controller;

class Log extends Common
{
	public function operation()
    {
		$where= [];
		//获得时间
        $create_date = input('create_date', '');
        $arr_time = explode('|', $create_date);
        if($arr_time && count($arr_time) == 2){
            if(count($arr_time) == 2){
                $where['create_date'] = array('between', array(strtotime($arr_time[0]), strtotime($arr_time[1])));
            }
        }
        //获得账户
        $username = input('username', '');
        if($username){
            $where['username'] = $username;
        }
        //搜索操作内容
        $msg = input('msg', '');
        if($msg){
            $where['msg'] = ['like', '%'. $msg .'%'];
        }
		$count = db('log_operation')->count();
        //每页显示30条数据
        $perCount = 30;
		$list = db('log_operation')->where($where)->order('id desc')->paginate($perCount, false, ['query'=>input()]);
        $showData = [
            'create_date' => $create_date,
            'username' => $username,
            'msg' => $msg,
        ];
		return view('operation', compact('count', 'list', 'showData'));
	}

	public function login()
    {
        $where= [];
        //获得时间
        $login_time = input('login_time', '');
        $arr_time = explode('|', $login_time);
        if($arr_time && count($arr_time) == 2){
            if(count($arr_time) == 2){
                $where['login_time'] = array('between', array(strtotime($arr_time[0]), strtotime($arr_time[1])));
            }
        }
        //获得账户
        $username = input('username','');
        if($username){
            $where['username'] = $username;
        }
        //判断类型是商户或管理
        $module = input('module', '');
        if($module){
            $where['module'] = $module;
        }
        //判断登录结果
        $result = input('result', '');
        if($result){
            $where['result'] = $result;
        }
        //判断ip
        $ip = input('ip', '');
        if($ip){
            $where['ip'] = $ip;
        }
        $count = db('log_login')->count();
        //每页显示30条数据
        $perCount = 30;
        $list = db('log_login')->where($where)->order('id desc')->paginate($perCount, false, ['query'=>input()]);
        $showData = [
            'login_time' => $login_time,
            'username' => $username,
            'module' => $module,
            'result' => $result,
            'ip' => $ip,
        ];
        return view('login', compact('count', 'list', 'showData'));
    }
}
















