<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/1
 * Time: 22:27
 */

namespace app\index2020\model;

use think\Db;

class Deposit extends Common
{
    /*
        日志列表
    */
    public static function deposit_log($params){
        $condition = [];
        $condition['member_id'] = MEMBER_ID;
        if($params){
            $params['apply']?$apply=explode('|',$params['apply']):'';
            $params['remit']?$remit=explode('|',$params['remit']):'';

            isset($apply)?$condition['create_date'] = array('between', array(strtotime($apply[0]), strtotime($apply[1]))):'';
            isset($remit)?$condition['success_date'] = array('between', array(strtotime($remit[0]), strtotime($remit[1]))):'';
            $params['status']?$condition['status']=$params['status']:'';
            $params['account']?$condition['bank_account']=$params['account']:'';
            $list = self::order('create_date','desc')->where($condition)->paginate(10);
            return $list;
        }else{
            $list = self::order('create_date','desc')->where($condition)->paginate(10);
            return $list;
        }
    }

}