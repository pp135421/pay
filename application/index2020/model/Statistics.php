<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/3
 * Time: 15:52
 */

namespace app\index2020\model;


use think\Model;

class Statistics extends Model
{
    public function member()
    {
        return $this->belongsTo('member', 'member_id', 'member_id');
    }

    /*
        member_id   商户号
        type        1为订单创建，累计订单总数量
                    2为成功支付订单，累计商户入金，代理收入，平台入金，通道入金
                    3为结算下发，累计平台入金,累计下发总金额
        total_money 商户入金累计字段
             income 代理收入累计字段
   platform_deposit 平台入金累计字段
        issue_money 下发总入金累计字段
    */
    public static function checkMember($params){
        $member_id = isset($params['member_id'])?$params['member_id']:'';
        $type = isset($params['type'])?$params['type']:'';
        $total_money = isset($params['total_money'])?$params['total_money']:0;
        $platform_deposit = isset($params['platform_deposit'])?$params['platform_deposit']:'';
        $passage_deposit = isset($params['passage_deposit'])?$params['passage_deposit']:'';
        $agent_income = isset($params['agent_income'])?$params['agent_income']:0;
        $issue_money = isset($params['issue_money'])?$params['issue_money']:0;

        if(!$member_id){
            throw new \Exception("商户号不存在");
        }

        $statistics = self::where('member_id',$member_id)->order('created_time','desc')->find();
        $today = strtotime(date('Y-m-d'));//今日日期

        $member = db('member')->lock(true)->field('pid')->where('member_id',$member_id)->find();//查询商户信息
        //没有今日记录 则创建
        if($statistics['created_time']!= $today){
            $insertData = [
                'member_id' => $member_id,
                'created_time' => $today,
            ];
            if($member['pid']){
                $insertData['agent_id'] = $member['pid'];
            }
            $res = (new self)->save($insertData);
            if(!$res) throw new \Exception("添加统计记录失败");
        }

        //拼接更新语句
        $sql = "UPDATE  `statistics` SET ";
        switch ($type){
            case 1:
                $sql .= "`blanket_order`= `blanket_order`+1 ";
                $msg = "统计记录订单累计失败";
                break;
            case 2:
                $sql .= "`total_money` = `total_money` + $total_money ,`success_order` = `success_order` + 1, `platform_deposit` = `platform_deposit` + $platform_deposit,`passage_deposit` = `passage_deposit` + $passage_deposit";
                if($member['pid']){
                    $sql.=",`agent_income` = `agent_income` + $agent_income,`agent_id`= '".$member['pid']."' ";
                }
                $msg = "统计记录商户入金累计失败";
                break;
            case 3:
                $sql .= "`platform_deposit`= `platform_deposit` + $platform_deposit,`issue_money` = `issue_money` + $issue_money ";
                $msg = "统计记录平台入金累计失败";
                break;
                break;
        }

        //更新条件
        $sql .= "WHERE  `member_id` = '$member_id' AND `created_time` = '$today' ";

        $res  = db('statistics')->execute($sql);
        if(!$res)throw new \Exception($msg);
        return true;
    }
}