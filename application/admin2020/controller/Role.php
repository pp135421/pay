<?php
namespace app\admin2020\controller;
use app\admin2020\model\Role as RoleModel;
use app\admin2020\model\Rule;

class Role extends Common
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        //每页显示30条数据
        $perCount = 30;
        $list = RoleModel::order('id desc')->paginate($perCount, false, ['query'=>input()]);
        //获得当前表的全部条数
        $count = RoleModel::count();
        return view('index', compact('list', 'count'));
    }

    public function add_role()
    {
        $id = input('id', 0);
        if(request()->isGet()){
            $info = RoleModel::get($id);
            return view('add', compact('info'));
        }
        $result = RoleModel::setRole();
        $desc = $id ? '修改' : '添加';
        if(!$result) showmessage($desc. '角色失败');
        showmessage($desc. '角色成功', 1);
    }

    public function del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        if($id == 1) showmessage('超级管理员无法删除');
        $ret = RoleModel::del($id);
        if(!$ret) showmessage('删除失败');
        showmessage('删除成功', 1);
    }

    public function set_rule()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        if($id == 1) showmessage('超级管理员无须设置权限！');
        if(request()->isGet()){
            $info = RoleModel::get($id);
            $data = Rule::all();
            $listTree = Rule::getRuleData($data);
            $ruleData = explode(',', $info['rule_id']);
            return view('set_rule', compact('info', 'listTree', 'ruleData'));
        }
        $result = RoleModel::setRule();
        if(!$result) showmessage('设置角色权限失败');
        showmessage('设置角色权限成功', 1);
    }


}
