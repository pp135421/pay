<?php
namespace app\admin2020\controller;
use app\admin2020\model\Rule as RuleModel;

class Rule extends Common
{
    public function __construct(){
        parent::__construct();
    }

    public function index()
    {
        //每页显示1000条数据
        $perCount = 1000;
        $list = RuleModel::order('id desc')->paginate($perCount, false, ['query'=>input()]);
        $listTree = RuleModel::getRuleData($list);
        //获得当前表的全部条数
        $count = RuleModel::count();
        return view('index', compact('list', 'count', 'listTree'));
    }

    public function set()
    {
        $id = input('id', 0);
        if(!$id)  showmessage('添加权限失败');
        if(request()->isGet()){
            $info = RuleModel::get($id)->toArray();
            $info['url'] = explode('/', $info['url']);
            return view('set', compact('info'));
        }
        $result = RuleModel::setRule();
        if(!$result) showmessage('修改权限失败');
        showmessage('修改权限成功', 1);
    }

    public function add_rule()
    {
        $id = input('id', 0);
        $info = RuleModel::get($id);
        if($info && $info['level'] >=3) showmessage('菜单最多3级');
        if(request()->isGet()){
            return view('add_rule', compact('info'));
        }
        $result = RuleModel::addRule($info);
        if(!$result) showmessage('添加权限失败');
        showmessage('添加权限成功', 1);
    }

    public function del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $ret = RuleModel::del($id);
        if(!$ret) showmessage('删除失败');
        showmessage('删除成功', 1);
    }

}
