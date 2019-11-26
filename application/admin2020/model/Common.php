<?php
namespace app\admin2020\model;
use think\Model;
use think\Db;
use app\admin2020\model\Operation;

class Common extends Model
{
    //修改状态
    public static function changeStatus($value, $key = 'id'){
        $info = self::get($value);
        $status = $info['status'] == 1 ? 2 : 1;
        $result = self::where($key, $value)->setField('status', $status);
        return $result ? $status : 0;
    }

//    //伪删除
//    public static function forge($value, $key = 'id'){
//        return self::where($key, 'in', $value)->setField('status', 2);
//    }

    //彻底删除
    public static function del($value, $key = 'id'){
        return self::where($key, 'in', $value)->delete();
    }

    /*
        2019-03-02 god Add
        列表状态修改
        status 状态字段 1为开启 2禁用
        condition 条件字段
        table 表名字段
    */
    public static  function editStatus($status='',$condition='',$table=''){
       if(!$table){
            $result = self::where($condition)->setField('status',$status);
       }elseif($table){;
            $result  = db("$table")->where($condition)->setField('status',$status);
       }

       if($result){
           $status==1?$msg='开启成功':$msg='禁用成功';
           $code = 1;
       }else{
           $status==1?$msg='开启失败':$msg='禁用失败';
           $code = 2;
       }
       ajaxReturn($code,$msg);
    }
}