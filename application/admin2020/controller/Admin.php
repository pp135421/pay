<?php
namespace app\admin2020\controller;
use app\admin2020\model\Admin as AdminModel;
use app\admin2020\model\Role;
use app\admin2020\model\Channel;

class Admin extends Common
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        //每页显示30条数据
        $perCount = 30;
        $list = AdminModel::order('id desc')->paginate($perCount, false, ['query'=>input()]);
        //取出对应订单
        $list2 = $list->toArray()['data'];
        $roleData = db('role')->select();
        $channelData = db('channel')->select();
        foreach ($list2 as $k => $v) {
            if(!isset($list2[$k]['rolename'])) $list2[$k]['rolename'] = '-';
            if(!isset($list2[$k]['noDanger'])) $list2[$k]['noDanger'] = true;
            if($v['password'] == AdminModel::getMd5Password(['username' =>$v['username'], 'password' => '123456'])){
                $list2[$k]['noDanger'] = false;
            }
            foreach ($roleData as $k2 => $v2) {
                if($v['role_id'] == $v2['id']){
                    $list2[$k]['rolename'] = $v2['rolename'];
                }
            }
            if(!isset($list2[$k]['channel_name_en'])) $list2[$k]['channel_name_en'] = '';
            foreach ($channelData as $k2 => $v2) {
                if($v['channel_id'] == $v2['id']){
                    $list2[$k]['channel_name_en'] = $v2['name_en'];
                }
            }
        }
        //获得当前表的全部条数
        $count = AdminModel::count();
        return view('index', compact('list', 'list2', 'count'));
    }

    public function del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        if($id == 1) showmessage('超级管理员无法删除');
        $ret = AdminModel::del($id);
        if(!$ret) showmessage('删除失败');
        log_operation('删除管理员-'.$id);
        showmessage('删除成功', 1);
    }

    public function set()
    {
        $id = input('id', 0);
        if(request()->isGet()){
            $info = AdminModel::get($id);
            if(ROLE_ID == 1){
                $roleData = Role::all();
            }else{
                $roleData = Role::all(['id' => ['gt', 1]]);
            }
            $channelData = Channel::all();
            return view('set', compact('info', 'roleData', 'channelData'));
        }
        $result = AdminModel::setAdmin();
        $desc = $id ? '修改' : '添加';
        if(!$result) showmessage($desc. '管理员失败');
        log_operation('修改管理员-'. $id);
        showmessage($desc. '管理员成功', 1);

    }

    public function status()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $ret = AdminModel::changeStatus($id);
        if(!$ret) showmessage('切换失败');
        $msg = $ret == 1 ? '启用成功' : '禁用成功';
        log_operation('切换管理员状态：'. $msg.'-'.$id);
        showmessage($msg , 1);
    }


    public function pwd()
    {
        if(request()->isGet()){
            return view('pwd');
        }
        if(!ADMIN_ID)  showmessage('管理账户异常！');
        $result = AdminModel::modifyPwd();
        if(!$result) showmessage('修改失败！');
        log_operation('修改密码成功');
        showmessage('修改成功！' , 1);
    }
}
