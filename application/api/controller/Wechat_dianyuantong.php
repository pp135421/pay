<?php

namespace app\api\controller;

class Wechat_dianyuantong extends Common
{
    protected static $platform = 'wechat';
    //当前控制器名称
    protected static $controllerName = 'Wechat_dianyuantong';

    public function __construct()
    {
        parent::__construct();
    }

    public static function pay($orderInfo)
    {
        if(!$orderInfo) showmessage('非法访问');
        $relate_key = input('relate_key', '');
        if($relate_key){
            $relate_key = urldecode($relate_key);
            $relate_key = urldecode($relate_key);
            // showmessage($relate_key);
            // $relate_key = base64_encode($relate_key);
            $wechatAccountInfo = db('wechat_account')->lock(true)->where(['name' => $relate_key])->find();
            if(!$wechatAccountInfo) record_error($orderInfo['platform_order_id'], '分配微信账户异常');
            if(!$wechatAccountInfo['url']) record_error($orderInfo['platform_order_id'], $wechatAccountInfo['url'].'：收款二维码解析异常！');
            $result = db('wechat_account')->where(['name' => $wechatAccountInfo['name']])->setField([
                'used_date' => time(),
            ]);
            if(!$result) record_error($orderInfo['platform_order_id'], "更新微信账户使用时间异常");
            $result = db('order')->where(['platform_order_id' => $orderInfo['platform_order_id']])->setField([
                'relate_key' => $wechatAccountInfo['name'],
                'special_str' => $wechatAccountInfo['url'],
            ]);
            if(!$result) record_error($orderInfo['platform_order_id'], '订单保存链接失败！');
        }else{
            $where = [
                'status' => 1,
                'pid' => ['gt', 0]
            ];
            $wechatAccountInfo = db('wechat_account')->lock(true)->where($where)->order('used_date asc')->find();
            if(!$wechatAccountInfo) record_error($orderInfo['platform_order_id'], "没有可用微信账户");
        }
        //用在轮询切换通道功能上
        if(isset($orderInfo['change_channel']) && $orderInfo['change_channel'] == 1){
            $param = [];
            static::changeChannel($param, $orderInfo);
        }
        //分配正确对应的url
        $qrcode = 'http://' .config('api_domin') .'/api/index/dianyuantong?orderid='. charEncode($orderInfo['platform_order_id']);
        static::returnSuccess($qrcode, $orderInfo);
    }

    public static function notify_url()
    {
        $param_arr = static::logReturnArr(static::$controllerName);
        // $str = '{"money":"0.01","sign":"0335de15e1c47f8cc880c397cd37e6a2","time":"1560435626000","timeStr":"2019-06-13 22:20:26","weixinAccount":"久👀伴💤"}';
        // $param_arr = json_decode($str, true);
        // dump($param_arr);

        if(!$param_arr) exit('接受数据不能为空！');
        header('content-type:text/html;charset=utf-8;');
        //签名验证
        // $flag = false;
        // $weixinAccount = '';
        // $wechatAccountData = db('wechat_account')->where(['pid' => 0])->select();
        // foreach ($wechatAccountData as $k => $v) {
        //     $sign = md5($param_arr['weixinAccount'] .$param_arr['money'] .$param_arr['time'] .$param_arr['timeStr'] . $v['account']);
        //     // dump($param_arr['weixinAccount'] .$param_arr['money'] .$param_arr['time'] .$param_arr['timeStr'] . $v['account']);
        //     // dump($sign);
        //     if($sign == $param_arr['sign']){
        //         $weixinAccount = $v['name'];
        //         $flag = true;
        //         break;
        //     }
        // }
        // if(!$flag) exit('sign error');

        //只处理付款时间5分钟内的订单
        if(time() - strtotime($param_arr['timeStr']) > 300){
            file_put_contents(ROOT_PATH. 'extend/log/record/'. date('Ymd').'.txt', PHP_EOL. date('H:i:s') . ' | '. static::$controllerName .  ' | '. '回调付款超时'.  ' | ' . json_encode($param_arr, 320), FILE_APPEND);
            exit('回调付款超时');
        }
        $money = bcadd($param_arr['money'], 0, 4);
        $weixinAccount = base64_encode($param_arr['weixinAccount']);
        $wechatNameInfo = db('wechat_name')->lock()->where([
            'name' => $weixinAccount,
        ])->find();
        if(!$wechatNameInfo){
            $data = ['name' => $weixinAccount];
            $wechatNameInfo = db('wechat_name')->insert($data);
        }
        //判断订单号是否唯一，否则当掉单处理
        $orderData = db('order')->where([
            'relate_key' => $weixinAccount,
            'actual_amount' => $money,
            'pay_status' => 1, //未支付
            'create_date' => ['gt', strtotime($param_arr['timeStr']) - 300],
        ])->select();
        // dump($orderData);
        //订单号处理
        if(count($orderData) == 0) {
            file_put_contents(ROOT_PATH. 'extend/log/record/'. date('Ymd').'.txt', PHP_EOL. date('H:i:s') . ' | '. static::$controllerName .  ' | '. '查询不到符合条件订单'.  ' | ' . json_encode($param_arr, 320), FILE_APPEND);
            exit('查询不到符合条件订单');
        }
        //可能存在重复订单
        if(count($orderData) > 1) {
            $orderIdArr = array_column($orderData, 'platform_order_id');
            $orderIdStr = implode(',', $orderIdArr);
            file_put_contents(ROOT_PATH. 'extend/log/record/'. date('Ymd').'.txt', PHP_EOL. date('H:i:s') . ' | '. static::$controllerName .  ' | '. '可能存在重复订单'.  ' | ' . $orderIdStr.  ' | ' . json_encode($param_arr, 320) , FILE_APPEND);
            exit('可能存在重复订单');
        }
        $platform_order_id = $orderData[0]['platform_order_id'];
        //对重复回调加强验证
        $orderInfo = db('order')->where([
            'special_str' => $param_arr['timeStr'],
        ])->find();
        if($orderInfo){
            file_put_contents(ROOT_PATH. 'extend/log/record/'. date('Ymd').'.txt', PHP_EOL. date('H:i:s') . ' | '. static::$controllerName .  ' | '. '可能是重复回调'.  ' | ' . json_encode($param_arr, 320), FILE_APPEND);
            exit('可能是重复回调');
        }
        $orderData = db('order')->where([
            'platform_order_id' => $platform_order_id,
        ])->setField([
            'special_str' => $param_arr['timeStr'],
        ]);
        //回调返回
        echo "success";
        return $platform_order_id;
    }
}
