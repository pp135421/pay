<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/6/5
 * Time: 16:32
 */

namespace app\api\controller;
use think\Db;

class Trade extends Common
{
    public function query()
    {
        //记录访问日志，并返回商户服务器IP
        //$ip = static::logReturnSafeIp('订单查询');
        //收集商户提交的合法数据
        $requestData = static::getRequestTradeQueryData();
        //获取商户对应数据
        $memberInfo = db('member')->where([
            'member_id' => $requestData['member_id'],
        ])->find();
        if(!$memberInfo) showmessage('商户号：'. $requestData['member_id']. '不存在');
        //签名验证
        $sign_new = static::makeSign($requestData, $memberInfo['apikey']);
        $sign = input('sign', '');
        if($sign_new != $sign) {
            ksort($requestData);
            $md5_str = '';
            foreach ($requestData as $k => $v) {
                $md5_str .= $k. '='. $v. "&";
            }
            $md5_str .= 'key='. $memberInfo['apikey'];
            showmessage('sign结果有误！服务器md5字符串结果：'. $md5_str. '，计算结果sign：'. $sign_new. '，您上传sign:'. $sign);
        }
        $orderInfo = db('order')->where(['submit_order_id' => $requestData['submit_order_id']])->find();
        if($orderInfo){
            $data = [
                'code' => 200,
                'msg' => 'success',
                'status' => in_array($orderInfo['pay_status'], [2, 3, 4]) ? 1 : 2, //1->成功  2->未支付
                'amount' => $orderInfo['amount'],
                'success_date' => $orderInfo['success_date'],
             ];
        }else{
            $data = [
                'code' => 201,
                'msg' => 'error',
                'status' => 0,
                'amount' => $orderInfo['amount'],
                'success_date' => 0,
             ];
        }
        $data['sign'] = self::makeSign($data, $memberInfo['apikey']);
        echo json_encode($data, 320);die;
    }
}
