<?php
namespace app\admin2020\model;
use think\Model;
use think\Db;
use think\Validate;
class Role extends Common
{
    //分类的删除
    public function dell($rule_id){
        //当分类id为0时 不容许删除
        if($rule_id<=0){
            $this->error = '参数错误';
            return FALSE;
        }
        //判断当前要删除的分类下是否存在子分类
        $rule_info = self::get(['p_id'=>$rule_id]);
        if($rule_info){
            //存在子分类
            $this->error = '存在子权限';
            return FALSE;
        }
        //使用query对象 调用delete方法删除
        self::where(['id'=>$rule_id])->delete();
    }

    public static function addRule($ruleInfo)
    {
        $id = $ruleInfo ? $ruleInfo['id'] : 0;
        $name = input('name', '');
        $module = input('module', '');
        $controller = input('controller', '');
        $action = input('action', '');
        $url = $module. '/'. $controller. ($action ? '/'.$action : '');
        $data = [
            'name' => $name,
            'url' => $url,
            'pid' => $id,
            'level' => ($ruleInfo ? $ruleInfo['level'] + 1 : 1),
        ];
        $data['create_date'] = time();
        $result = (new self)->save($data);
        if(!$result) return false;
        return true;
    }

    public static function setRole()
    {
        $id = input('id', 0);
        $rolename = input('rolename', '');
        $data = [
            'rolename' => $rolename,
        ];
        $primary = [];
        if($id){
            $primary['id'] = $id;
        }
        $result = (new self)->save($data, $primary);
        if(!$result) return false;
        return true;
    }

    public static function setRule()
    {
        $id = input('id', 0);
        $rule_id = input('rule_id/a', '');
        if(!$rule_id) showmessage('必须设置权限');
        $data = ['rule_id' => implode(',', $rule_id)];
        $primary = [];
        if($id){
            $primary['id'] = $id;
        }
        $result = (new self)->save($data, $primary);
        if(!$result) return false;
        return true;
    }


}