<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/6/5
 * Time: 16:32
 */

namespace app\api\controller;
use think\Db;

class Df extends Common
{
    public function index()
    {
        //记录访问日志，并返回商户服务器IP
        $ip = static::logAccessData('代付提现创建');
        //收集商户提交的合法数据
        $requestData = static::getRequestDaifuData();
        //获取商户对应数据
        $memberInfo = db('member')->lock(true)->where([
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
        if(!$memberInfo['safe_ip']){
            $data = ['code' => 201, 'msg' => '商户未绑定安全IP，请联系客服绑定！'];
            $data['sign'] = self::makeSign($data, $memberInfo['apikey']);
            echo json_encode($data, 320);die;
        }
        if($memberInfo['safe_ip'] != $ip){
            $data = ['code' => 201, 'msg' => '安全IP错误，请联系客服重新绑定！'];
            $data['sign'] = self::makeSign($data, $memberInfo['apikey']);
            echo json_encode($data, 320);die;
        }
        //创建下发数据  createDeposit($memberInfo, $requestData, $deposit_type, $submit_type)
        Common::createDeposit($memberInfo, $requestData, $memberInfo['deposit_type'], 2); //2->代付
    }

    public function df_query()
    {
        //记录访问日志，并返回商户服务器IP
        $ip = static::logAccessData('代付状态查询');
        //收集商户提交的合法数据
        $requestData = static::getRequestDaifuQueryData();
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
        $depositInfo = db('deposit')->where(['submit_order_id' => $requestData['submit_order_id']])->find();
        if($depositInfo){
            // 1是未处理 2处理中 3已处理 4驳回
            $data = [
                'code' => 200,
                'msg' => 'success',
                'status' => $depositInfo['status'],
                'deposit_order_id' => $depositInfo['deposit_order_id'],
                'success_date' => $depositInfo['success_date'],
             ];
        }else{
            $data = [
                'code' => 201,
                'msg' => 'error',
             ];
        }
        $data['sign'] = self::makeSign($data, $memberInfo['apikey']);
        echo json_encode($data, 320);die;
    }

    public function member_query()
    {
        //记录访问日志，并返回商户服务器IP
        $ip = static::logAccessData('商户余额查询');
        //收集商户提交的合法数据
        $requestData = static::getRequestMemberBalanceQueryData();
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
        $data = [
            'code' => 200,
            'msg' => 'success',
            'balance' => $memberInfo['balance'], //1->成功  2->未支付
         ];
        $data['sign'] = self::makeSign($data, $memberInfo['apikey']);
        echo json_encode($data, 320);die;
    }
}
