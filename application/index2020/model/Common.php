<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/28
 * Time: 20:35
 */

namespace app\index2020\model;


use think\Model;
use think\Db;
class Common extends Model
{
    public static function checkRule(){
        $member = session('_member');


        // if((time()-$member['login_time'])/86400>600)return false;//10分钟未操作则重新登录
        session('member.login_time',time());
        //环境常量
        define('AGENT', $member['agent']);
        define('USERNAME', $member['username']);//商户名称
        define('MID', $member['id']); //商户ID
        define('MEMBER_ID', $member['member_id']);//商户号
    }

    //删除
    public static function del($value, $key = 'id'){
        $res = self::where($key, 'in', $value)->delete();
        $res?ajaxReturn(1,'删除成功'):ajaxReturn(2,'删除失败');

    }

    /*
        修改状态
    */
    public static function changeStatus($id,$status){
        // 启动事务
        Db::startTrans();
        try{
            $status==2 ?$msg='禁用成功':$msg='开启成功';
            $ret = self::where('id', $id)->setField('status', $status);
            // 提交事务
            Db::commit();
            ajaxReturn(1,$msg);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            ajaxReturn(config('cf.fail'),$e->getMessage());
        }
    }
}