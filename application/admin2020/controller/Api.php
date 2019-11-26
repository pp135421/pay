<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/27
 * Time: 17:12
 */

namespace app\admin2020\controller;


class Api extends Common
{
    public function paid()
    {
        $platform_order_id = input('orderid', '');
        if(ROLE_ID != 15){
            showmessage('error!');
        }
        self::setOrderPaid($platform_order_id, 1); //1 代表内部强制回调
    }

    public function receive_notify()
    {
        //接收内部回调
        exit("OK");
    }

    public function df_request()
    {
        $deposit_order_id = input('deposit_order_id', '');
        if(!$deposit_order_id) showmessage('deposit_order_id：不能为空！');
        $depositInfo = db('deposit')->where(['deposit_order_id' => $deposit_order_id])->find();
        if(!$depositInfo) showmessage('平台代付订单号：'. $deposit_order_id. '不存在！');
        $bankNameCodeArr = config('bankNameCodeArr');
        $flag = false;
        foreach ($bankNameCodeArr as $k => $v) {
            if(strpos($depositInfo['bank_name'], $v) !== false){
                $depositInfo['bank_name'] = $v;
                $depositInfo['bank_code'] = $k;
                $flag = true;
                break;
            }
        }
        if(!$flag){
            showmessage('上游暂不支持银行：【'.$depositInfo['bank_name']. '】，代付处理！');
        }
        $channel_id = input('channel_id', '');
        $channelInfo = db('channel')->where(['id' => $channel_id])->find();
        if(!$channelInfo) showmessage('通道-'. $depositInfo['channel_id']. '不存在！');
        //找到商户分配的对应通道文件类
        $channelInfo['name_en'] = 'Wechat_shaohua';
        $file = __DIR__.'/../../api/controller/'. $channelInfo['name_en']. '.php';
        // $file = __DIR__.'/../../api/controller/'. $channelInfo['name_en']. '.php';
        if(!is_file($file)) showmessage($channelInfo['name_en'] .'：文件不存在！');
        include_once $file ;
        $className = '\\app\api\controller\\'. $channelInfo['name_en'];
        $className::df_request($depositInfo, $channelInfo['id']);
    }

    public function df_query()
    {
        //找到商户分配的对应通道文件类
        $channelInfo['name_en'] = 'Wechat_shaohua';
        $file = __DIR__.'/../../api/controller/'. $channelInfo['name_en']. '.php';
        // $file = __DIR__.'/../../api/controller/'. $channelInfo['name_en']. '.php';
        if(!is_file($file)) showmessage($channelInfo['name_en'] .'：文件不存在！');
        include_once $file ;
        $className = '\\app\api\controller\\'. $channelInfo['name_en'];
        $className::df_query();
    }

    public function df_query_status()
    {
        $deposit_order_id = input('deposit_order_id', '');
        if(!$deposit_order_id) showmessage('deposit_order_id：不能为空！');
        $depositInfo = db('deposit')->where(['deposit_order_id' => $deposit_order_id])->find();
        if(!$depositInfo) showmessage('平台代付订单号：'. $deposit_order_id. '不存在！');
        $channel_id = input('channel_id', '');
        $channelInfo = db('channel')->where(['id' => $channel_id])->find();
        if(!$channelInfo) showmessage('通道-'. $depositInfo['channel_id']. '不存在！');
        $file = __DIR__.'/../../api/controller/'. $channelInfo['name_en']. '.php';
        // $file = __DIR__.'/../../api/controller/'. $channelInfo['name_en']. '.php';
        if(!is_file($file)) showmessage($channelInfo['name_en'] .'：文件不存在！');
        include_once $file ;
        $className = '\\app\api\controller\\'. $channelInfo['name_en'];
        $className::df_query_status($depositInfo);
    }

    public function df_notify()
    {
        $deposit_order_id = input('deposit_order_id', '');
        if(!$deposit_order_id) showmessage('deposit_order_id：不能为空！');
        $depositInfo = db('deposit')->where(['deposit_order_id' => $deposit_order_id])->find();
        if(!$depositInfo) showmessage('平台代付订单号：'. $deposit_order_id. '不存在！');
        $channelInfo = db('channel')->where(['id' => $depositInfo['channel_id']])->find();
        if(!$channelInfo) showmessage('通道-'. $depositInfo['channel_id']. '不存在！');
        //找到商户分配的对应通道文件类
        $file = __DIR__.'/../../api/controller/'. $channelInfo['name_en']. '.php';
        if(!is_file($file)) showmessage($channelInfo['name_en'] .'：文件不存在！');
        include_once $file ;
        $className = '\\app\api\controller\\'. $channelInfo['name_en'];
        $className::df_notify();
    }

    public function notify()
    {
        $platform_order_id = input('orderid', '');
        if(!$platform_order_id) showmessage('orderid：不能为空！');
        $orderInfo = db('order')->where(['platform_order_id' => $platform_order_id])->find();
        if(!$orderInfo) showmessage('平台订单号：'. $platform_order_id. '不存在！');
        $channelInfo = db('channel')->where(['id' => $orderInfo['channel_id']])->find();
        if(!$channelInfo) showmessage('通道-'. $orderInfo['channel_id']. '不存在！');
        //找到商户分配的对应通道文件类
        $file = __DIR__.'/../../api/controller/'. $channelInfo['name_en']. '.php';
        if(!is_file($file)) showmessage($channelInfo['name_en'] .'：文件不存在！');
        include_once $file ;
        $className = '\\app\api\controller\\'. $channelInfo['name_en'];
        $platform_order_id = $className::notify_url();
        //必须先进行签名验证
        self::setOrderPaid($platform_order_id, 0); //0 代表接收外部回调
    }

    protected function self_mode($mode, $platform = 'Alipay')
    {
        //找到商户分配的对应通道文件类
        $file = __DIR__.'/../../api/controller/'. $platform.'_'. $mode. '.php';
        if(!is_file($file)) showmessage($platform.'_'. $mode. '：文件不存在！');
        include_once $file ;
        $className = '\\app\api\controller\\'. $platform.'_'. $mode;
        $platform_order_id = $className::notify_url();
        //订单自动回调
        if($platform_order_id) self::setOrderPaid($platform_order_id, 0); //0 代表接收外部回调
    }

    public function paofen()
    {
        $this->self_mode('paofen');
    }

    public function fenghuang()
    {
        $this->self_mode('fenghuang');
    }

    public function jubao()
    {
        $this->self_mode('jubao', 'Wechat');
    }

    public function zhongju()
    {
        $this->self_mode('zhongju');
    }

    public function dianyuantong()
    {
        $this->self_mode('dianyuantong', 'Wechat');
    }

    //支付宝转银行卡
    public function bank()
    {
        $this->self_mode('bank');
    }

    //普通红包
    public function hongbao()
    {
        $this->self_mode('hongbao');
        // self::setOrderPaid('ksk155343615978889', 0);
    }


    //钉钉红包
    public function dingding()
    {
        $this->self_mode('dingding');
    }

    //主动收款
    public function shoukuan()
    {
        $this->self_mode('shoukuan');
    }

    //农信银
    public function nongxin()
    {
        $this->self_mode('nongxin');
    }

    //农信银
    public function nongxin2()
    {
        $this->self_mode('nongxin', 'Wechat');
    }
}