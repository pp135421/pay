<?php
namespace app\admin2020\model;
use think\Model;
use think\Db;
use think\Validate;
class Rule extends Common
{

    public static function getRuleData($data){
        return self::getTree($data);
    }

    public static function getTree($array, $pid = 0, $level = 0)
    {

        static $list = [];

        foreach ($array as $key => $value){
            if(!is_array($value)) $value = $value->toArray();
            //第一次遍历,找到父节点为根节点的节点 也就是pid=0的节点
            if ($value['pid'] == $pid){
                //父节点为根节点的节点,级别为0，也就是第一级
                $value['level2'] = $level;
                //把数组放到list中
                $list[] = $value;
                //把这个节点从数组中移除,减少后续递归消耗
                unset($array[$key]);
                //开始递归,查找父ID为该节点ID的节点,级别则为原级别+1
                self::getTree($array, $value['id'], $level + 1);
            }
        }
        return $list;
    }

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
            'url' => strtolower($url),
            'pid' => $id,
            'level' => ($ruleInfo ? $ruleInfo['level'] + 1 : 1),
        ];
        $data['create_date'] = time();
        $result = (new self)->save($data);
        if(!$result) return false;
        return true;
    }



    public static function setRule()
    {
        $id = input('id', 0);
        $name = input('name', '');
        $module = input('module', '');
        $controller = input('controller', '');
        $action = input('action', '');
        $url = $module. ($controller ? '/'.$controller : ''). ($action ? '/'.$action : '');
        $data = [
            'name' => $name,
            'url' => strtolower($url),
        ];
        $primary = [];
        if($id){
            $primary['id'] = $id;
        }
        $result = (new self)->save($data, $primary);
        if(!$result) return false;
        return true;
    }


}