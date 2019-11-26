<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/1
 * Time: 23:22
 */

namespace app\admin2020\model;
use app\index2020\model\Statistics;
use think\Db;
class Deposit extends Common
{
    public static function depositList($params)
    {
        if($params){
            $params['apply']?$apply=explode('|',$params['apply']):'';
            $params['remit']?$remit=explode('|',$params['remit']):'';
            $condition = [];

            isset($apply)?$condition['create_date'] = array('between', array(strtotime($apply[0]), strtotime($apply[1]))):'';
            isset($remit)?$condition['success_date'] = array('between', array(strtotime($remit[0]), strtotime($remit[1]))):'';
            $params['status']?$condition['status']=$params['status']:'';

            $list = self::order('create_date','desc')->where($condition)->paginate(10);
            return $list;
        }else{
            $list = self::order('create_date','desc')->paginate(10);
            return $list;
        }
    }

    public static function deposit_edit($params){
        $channel_money = $params['channel_money'];//通道结算金额数组
        $money = $params['money'];//提现金额
        $channel = $params['channel'];//通道ID数组
        $balance = $params['balance'];//通道剩余金额数组
        $channel_name = $params['channel_name'];//通道名称数组
        $deposit_id = $params['id'];//提现记录表主键ID
        $member_id = $params['member_id'];//提现对应商户ID
        $status = $params['status'];//结算状态

        $channel_xiafa = '';
        // 启动事务
        Db::startTrans();
        try{
            //防止重复结算
            $depositInfo = db('deposit')->where('id', $deposit_id)->lock(true)->find();
            if(!$depositInfo || $depositInfo && $depositInfo['status'] == 3){
                ajaxReturn(2, '不允许多人同时结算下发');
            }
            switch ($status)
            {
                case 3:
                    //拼接原生 批量更新语句
                    $allmoney = 0;//总通道下发金额
                    $ids = '';
                    $sql = "UPDATE channel SET channel_money = CASE id";
                    foreach ($channel_money AS $key => $value){
                        if($value == '') $value = 0;
                        if(!is_numeric($value)){
                            ajaxReturn(2,'金额输入异常');
                        }

                        if(!$value) continue;
                        if($value > $balance[$key]){
                            ajaxReturn(2,$channel_name[$key]. '余额不足');
                        }

                        if($value){
                            if(!$channel_xiafa){
                                $channel_xiafa .= $channel_name[$key]. ': <font color="red">'. $value. '￥</font>';
                            }else{
                                $channel_xiafa .= ' | ' . $channel_name[$key]. ': <font color="red">'. $value. '￥</font>';
                            }
                        }

                        $allmoney = $allmoney + $channel_money[$key];
                        $sql .= sprintf(" WHEN %d THEN channel_money - %d ", $channel[$key], $channel_money[$key]);
                        $ids .= $channel[$key].',';
                    }
                    $sql .= "END WHERE id IN (".trim($ids,',').")";
                    if($allmoney != $money){
                        ajaxReturn(2,'下发金额不等于提现金额');
                    }

                    $res = db('channel')->execute($sql);
                    if(!$res){
                        Db::rollback();
                        ajaxReturn(2,'通道金额扣款失败');
                    }

                    // $memberData = db('member')->field('member_id, deposit_amount')->where('member_id', $member_id)->find();
//                    $money_change = [
//                        'member_id' => $memberData['member_id'],
//                        'change_money' => $memberData['deposit_amount'],//提现手续费
//                        'change_type' => 22,//结算下发手续费
//                        'create_date' => time(),
//                    ];
//                    $res = db('money_change')->insert($money_change);//增加资金变动记录
//                    if(!$res){
//                        Db::rollback();
//                        ajaxReturn(2,'添加下发手续费资金变动记录失败');
//                    }
                    //数据统计
                    // $params = [
                    //     'member_id' => $memberData['member_id'],
                    //     'type' => 3,
                    //     'issue_money' => $money,
                    //     'platform_deposit' => $memberData['deposit_amount'],
                    // ];
                    // Statistics::checkMember($params);
                break;
                case 4:
                    $memberData = db('member')->field('member_id, balance')->where('member_id', $member_id)->find();//获取用户余额
                    $money_change = [
                        'member_id' => $memberData['member_id'],
                        'before_money' => $memberData['balance'],//原金额
                        'change_money' => $money,//变动金额
                        'after_money' => $memberData['balance'] + $money,//变动后的金额
                        'change_type' => 13,//下发驳回
                        'create_date' => time(),
                    ];
                    $res = db('money_change')->insert($money_change);//增加资金变动记录
                    if(!$res){
                        Db::rollback();
                        ajaxReturn(2,'添加驳回资金变动记录失败');
                    }

                    $res = db('member')->where('member_id', $member_id)->setInc('balance',$money);
                    if(!$res){
                        Db::rollback();
                        ajaxReturn(2,'驳回时，商户退回金额失败');
                    }
                break;
            }
            $saveData = [
                'status' => $status,
            ];
            if($status == 3) $saveData['success_date'] = time();//当结算为已处理时 更新结算时间
            $res = db('deposit')->where('id',$deposit_id)->update($saveData);
            if(!$res){
                Db::rollback();
                ajaxReturn(2,'更新结算状态失败,不允重复更新');
            }
            if($status == 3){
                $msg = '已完成';
            }else if($status == 4){
                $msg = '已驳回';
            }else{
                $msg = '处理中';
            }
            // 提交事务
            Db::commit();
            log_operation('修改结算（提现单号 | '.$depositInfo['deposit_order_id'].'）： [ <font color="red">'.$msg. '</font> ] ，金额：'.$money. '，通道：'.$channel_xiafa);
            ajaxReturn(1,'结算处理成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            ajaxReturn(config('cf.fail'),$e->getMessage());
        }
    }
}