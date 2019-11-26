<?php

namespace app\api\controller;

class Wechat_shaohua extends Common
{
    protected static $platform = 'wechat';
    //当前控制器名称
    protected static $controllerName = 'Wechat_shaohua';
    //上游通道对接网关地址
    protected static $gateway = 'http://ltpay.okcbill.com/api/ltpay';
    //上游通道代付地址
    protected static $gatewayDf = 'http://ltpay.okcbill.com/api/ltPayment';
    protected static $gatewayDfQueryBalance = 'http://ltpay.okcbill.com/api/BalanceSelect';
    protected static $gatewayDfQueryPayment = 'http://ltpay.okcbill.com/api/PaymentQuery';
    //上游通道商户号
    protected static $Merch_Id = '20190810034501';
    //上游通道商户秘钥
    protected static $apikey = '38660317ce4586bbe5fa17ae340233fd';
    //平台公钥
    protected static $platform_public = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQChD7mJJtpB7GHpOUGuaKD0nSGr1xawaRcIXRubUNLfy+G1v3/zsJ3Gg65GCDhkJvAuYNiRwthhWU3uQocX9E02BmWdTXtnR4J89YZ4Gb8Uud0sgQT3rYqCCxHTk1byTb8gBhpufgMA0ftET4SBhfuULXq7TBlI9CrzShCOoFXAtQIDAQAB';
    //商户私钥
    protected static $merchant_private = 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCfB/FnSj+Kmv8/4QXtdWG67hsL5MM1hxQQncJhiAgHGrtbFnWGhf0y5aG/yZ6sbMl6BtWZ0ewYoqDZisBZnIOJuQoCikWFFemu1h8tcFpng6IUWeH+NpH103ASc20+ElFLJnsPdll2VIg3Mw64/xglnBbpChlGjc0p+jo589WOkJ4v7XEZ4vwoz96+GrktYz/hqj/94u901joY83/B0e1DkQbYemukPvQ2LSda0aHKT7K6HoCeZLR0DYC4tL1ARORoiSrX5zIGScTt0O30VrA+amY90lACgibEz7+kuXnZwdc7JUzRDNkXonilDbKshi0wN/afuS2/YrKZvY76ikfVAgMBAAECggEALnJ+ciGAX3YXmMubFJiU+6ixY47j6M1T8epxrFSzq4eGMvtjRe/6XJQu4rn7SvbW/XnjhvKF10ggXIkm1sVcsavGnalO1qjowHSvVHEdJmCOxQzfcYT0qmyfrfPicx+ceKt7g6+YP3Q++IhEA4oVvl5RXqzhZvbhdiIsQWLS+7kQ4fpNDEPzUkdWDvEtp/8pXqDkoF+tzNprO+VCls5kjIqbBeMGGiD3lsFrtPY06cLMdxpgK4HjhVk0sm4C4yMKSqQRjEMdRXYl9dfsBa7Hp1h+yTSVtcUKRGEmDekE6NK0qb38R5HW8Md6J8RUguGc9xYSCPTPU7bmwyGolAGWAQKBgQDeJfaMwUH4AnZz3G0k6UlVN1EkqTBqPDS/m1Rmx+UcrnXiimT3C6FXPvnwhiQBsCnLjLrmEKTeDZ6L5RMFIT7M+XKaTccI4F8U2bClTOxhMCjSW8hyxUa5dICprCSh6p4sF7E4bI93jFdRTEB5SH+fDwqVNWbcnNIEWaVpEIGAVQKBgQC3Q8Qpk/wWlw9pilb/m1vEeInF3Vprshgs5sPAGi82zdoHDG2X1fMsVHJLkcRggdScNwVS3koWsBcPGVRslJYFfV4CH8o/51h1oej4DTKFAafL89yXx/ET+vKfYQ++Wdh/aVbsULiEFP264IFqB9xY+GKAjPahpF/Us5BEi88pgQKBgEWDBYn3swfC5YPNlo11PhAfNhHNqyui2TKXjSp4JDX8VUDk40D2b67YMudTYhLxJ7Lcv2LcFGqzQkguDuyNAZSr/XNRIRWi972TfJXM4y5qHmvscmWPW1kOnm/5QKE1w/ayFy87sQzMakozHP2WdPC1iS81PZGMtJ7N2lds5cjdAoGAEOD47CtpCFuZW8sWACy64vmHFuYwMcMRXvFSDhtbRdznu8Z1QYq+/tI4RKWERK8wecLHhr5abISDWyymDeoRdyf4xJFQ+1m/V/Y1ksMEaCOi5LHtGz2bApAWUH+MB6gWvIVjMBivJdsZE2EiCjX3IWqfB9/zxydwfBKdsvJ9fgECgYEAvI0ti16nfwVvEjj0zeBPsSuBAj5ncqUmaHzG+FNfXbXlx67xXh2EABzJ+52dGXOpyLrFCATSIATfyByhP754Evb6WEzw1giZMSc8UAzxMYDDJ626t1TidDVqVP6DYR/PvMPJwV9A3kv2AtUu/SDeDu3ZqN2Cpjwh+WIEp1uzSW0=';

    public function __construct()
    {
        parent::__construct();
    }

    public static function channel_post_data($data, $gateway, $signName = 'sign')
    {
        $data[$signName] = static::makeSign($data, static::$apikey);
        $data = json_encode($data);
        $platform_public = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap(static::$platform_public, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        //公钥加密
        $key = openssl_pkey_get_public($platform_public);
        if (!$key) showmessage('公钥不可用');
        $str = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $key);
            $str .= $encryptData;
        }
        $param = array(
            "Merch_Id" => static::$Merch_Id,
            'Version' => 'V1.0.0',
            'Char_Type' => 'UTF-8',
            'Sign_Type' => 'MD5',
            'Cryp_Type' => 'RSA',
        );
        $param['Data'] = base64_encode($str);
        $content = curl_post($param, $gateway);
        // dump($content);
        $content_arr = json_decode($content, true);
        // dump($content_arr);
        return $content_arr;
    }


    public static function pay($orderInfo)
    {
        if(!$orderInfo) showmessage('非法访问');
        $data = array(
            "Merch_Order" => $orderInfo['platform_order_id'],
            "Total_Amount" => bcadd($orderInfo['amount'] * 100, 0, 0),
            "Subject" => 'test_'. uniqid(),
            'Notify_Url' => 'http://'. config('admin_domin').  '/notify/'. $orderInfo['platform_order_id'],
            'IP_Adr' => '127.0.0.1',
            'Pay_Type' => '200002',
            'Acc_Type' => 'D0',
        );
        $content_arr = static::channel_post_data($data, static::$gateway, 'Sign');
        // dump($content_arr);
        //空数据返回失败
        if(!$content_arr)  record_error($orderInfo['platform_order_id'], '获得链接失败');
        //上游通道返回的错误信息
        if($content_arr['Err_Code'] != '0') {
            $error = $content_arr['Err_Mes'];
            static::returnError($error, $orderInfo);
        }
        //正常获得上游通道数据
        if($content_arr['Err_Code'] == '0'){
            $qrcode = $content_arr['H5_Url'];
            static::returnSuccess($qrcode, $orderInfo);
        }
    }

    public static function df_request($depositInfo, $channel_id=0)
    {
        if(!$depositInfo) showmessage('非法访问');
        $data = array(
            "pay_order_id" => $depositInfo['deposit_order_id'],
            'pay_secret' => '794444',
            'pay_card' => $depositInfo['bank_card_no'],
            "pay_money" => $depositInfo['amount'] * 100,
            "pay_name" => $depositInfo['bank_account'],
            "pay_bankname" => $depositInfo['bank_name'],
            "pay_bankcode" => $depositInfo['bank_code'],
            'return_url' => 'http://'. config('admin_domin').  '/df_notify/'. $depositInfo['deposit_order_id'],
        );
        $content_arr = static::channel_post_data($data, static::$gatewayDf, 'sign');
        //空数据返回失败
        if(!$content_arr)  showmessage('代付余额申请失败！');
        $result = db('deposit')->where(['deposit_order_id' => $depositInfo['deposit_order_id']])->setField([
            'return_json' => json_encode($content_arr, 320),
            'df_return_date' => time(),
        ]);
        if(!$result)  showmessage('保存代付返回结果异常！');
        //上游通道返回的错误信息
        if($content_arr['resCode'] != '00') {
            $msg = $content_arr['msg'];
            showmessage($msg);
        }
        //正常获得上游通道数据
        if($content_arr['resCode'] == '00'){
            $msg = $content_arr['msg'];
            $result = db('deposit')->where(['deposit_order_id' => $depositInfo['deposit_order_id']])->setField([
                'channel_id' => $channel_id,
            ]);
            if(!$result)  showmessage('指定代付订单号通道失败！');
            showmessage($msg, 1);
        }
    }

    public static function df_query()
    {
        $data = array(
            "Sel_Order" => uniqid(),
        );
        $content_arr = static::channel_post_data($data, static::$gatewayDfQueryBalance, 'Sign');
        //空数据返回失败
        if(!$content_arr)  showmessage('代付查询失败！');
        echo json_encode($content_arr, 320);die;
    }

    public static function df_query_status($depositInfo)
    {
        $data = array(
            "Pay_Order_Id" => $depositInfo['deposit_order_id'],
        );
        $content_arr = static::channel_post_data($data, static::$gatewayDfQueryPayment, 'Sign');
        //空数据返回失败
        if(!$content_arr)  showmessage('代付状态查询失败！');
        $result = static::updateDfStatus($content_arr, $depositInfo['deposit_order_id']);
        if(!$result) exit('更新代付订单号返回值失败！');
        //正常获得上游通道数据
        if($content_arr['Err_Code'] == '0' && $content_arr['Result'] == 'SUCCESS'){
            $msg = '代付订单号：'. $depositInfo['deposit_order_id']. ' 已下发！';
            showmessage($msg, 1);
        }
        //上游通道返回的错误信息
        if($content_arr['Err_Code'] == '0' && $content_arr['Result'] == 'WAIT_PAY') {
            $msg = '代付订单号：'. $depositInfo['deposit_order_id']. ' 处理中！';
            showmessage($msg);
        }
        //正常获得上游通道数据
        if($content_arr['Err_Code'] == '0' && $content_arr['Result'] == 'ERROR'){
            $msg = '代付订单号：'. $depositInfo['deposit_order_id']. ' 代付失败！';
            showmessage($msg);
        }
        //上游通道返回的错误信息
        if($content_arr['Err_Code'] == '0' && isset($content_arr['Err_Mes'])) {
            $msg = $content_arr['Err_Mes'];
            showmessage($msg);
        }
    }

    public static function notify_url()
    {
        $param_arr = static::logReturnArr(static::$controllerName);
        // $str = '{"Char_Type":"UTF-8","Cryp_Type":"RSA","Data":"HSDeR4hRNNQ+MsGjESDo+MsQn/vm3lIDXp6NE2wp60Ka9HpaW6hJkQHimTrD0b/3Of1gLCdszTm/ePr48ccFJbZQC0xgO89b12KkVpBUgrqxZZHTec2xixu3w/IqxOr2krjNDBR+50nAnlVGc+Gq3+cVygeqMcwr8JVEYmh5C7QHdEoJP2Y2fx7mlfhv6aDR4OWRsUtHPOcEbUm9eCrZVORx9Fw3d8ULRoAqRfGHClVR/zlglOGwrp/EBZybRYKWF9ATyTdsNnyu3/wu1g4eG2mbAKqBEArUaC/AEr5JLRpcg7cPmp1gFIuOeRZxZiC7Hl28gl4xI/+Olbujr/JjeA==","Merch_Id":"20190810034501","Sign_Type":"MD5","Version":"V1.0.0"}';
        // $param_arr = json_decode($str, true);
        // dump($param_arr);

        if(!$param_arr) exit('接受数据不能为空！');
        $merchant_private = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap(static::$merchant_private, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        $key = openssl_pkey_get_private($merchant_private);
        if (!$key) exit('私钥不可用');
       // $encrypted = $this->urlsafe_b64decode($encrypted);
        $data = base64_decode($param_arr['Data']);
        openssl_private_decrypt($data, $decrypt, $key);
        $param_arr = json_decode($decrypt, true);
        if(!$param_arr) exit('RSA转成数组失败');
        //判断respCode
        if($param_arr['Err_Code'] != '0') exit('Err_Code error');
        //判断merId
        if($param_arr['Result'] != 'SUCCESS') exit('Result error');
        $sign_old = $param_arr['Sign'];
        unset($param_arr['Sign']);
        $sign = static::makeSign($param_arr, static::$apikey);
        if($sign_old != $sign) die('sign 有误！');
        //返回值的平台订单号
        $order_id = $param_arr['Merch_Order'];
        //链接里的平台订单号
        $platform_order_id = input('orderid', '');
        if($platform_order_id != $order_id) exit('订单号关联异常！');
        //回调返回
        echo "success";
        return $platform_order_id;
    }

    public static function df_notify()
    {
        $param_arr = static::logReturnArr(static::$controllerName, '../extend/log/df_notify/');
        // die;
        // $str = '{"Char_Type":"UTF-8","Cryp_Type":"RSA","Data":"aqhnkpvMIzGFYYTLtpMug+4bsm5qdCUqavpuGiRA0dX0R5hzgYt4fFSfsw+3NNF/yZKcVAmSL461XdQ4MikMoveQ5SYYox10fQdvItNWcna+bll+ZDohhDmI3ZigD8lqYhh6/a/u9cnVFGXKUXjGN2rtwBk8qY9BORFpNRey4NlJWTtSBjEh/z49oHWtQaDtO0eUomnl/SLQpY8xFPZKEq0MkTctSuI5QMIlLvVp+Hk1iyXxZzLz54mSmvRsyJhVu9Wxa2+CCRtjtxtrdUrFXUgHke/sCi0GD7ZJmkAVn6NZu8fWYn8LOa9mfCWNX9fk59g8MOVIhZt8khyqaXmtqw==","Merch_Id":"20190810034501","Sign_Type":"MD5","Version":"V1.0.0"}';
        // $param_arr = json_decode($str, true);
        // dump($param_arr);

        if(!$param_arr) exit('接受数据不能为空！');
        $merchant_private = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap(static::$merchant_private, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        $key = openssl_pkey_get_private($merchant_private);
        if (!$key) exit('私钥不可用');
       // $encrypted = $this->urlsafe_b64decode($encrypted);
        $data = base64_decode($param_arr['Data']);
        openssl_private_decrypt($data, $decrypt, $key);
        $param_arr = json_decode($decrypt, true);
        if(!$param_arr) exit('RSA转成数组失败');
        dump($param_arr);
        //判断respCode
        if($param_arr['result_code'] != 'PAY_SUCCESS') exit('result_code error');
        $sign_old = $param_arr['sign'];
        unset($param_arr['sign']);
        $sign = static::makeSign($param_arr, static::$apikey);
        if($sign_old != $sign) die('sign 有误！');
        //返回值的平台订单号
        $order_id = $param_arr['pay_order_id'];
        //链接里的平台订单号
        $deposit_order_id = input('deposit_order_id', '');
        if($order_id != $deposit_order_id) exit('订单号关联异常！');
        $result = static::updateDfStatus($param_arr, $deposit_order_id);
        if(!$result) exit('更新代付订单号已下发失败！');
        //回调返回
        echo "success";
        return '';
    }

    public static function updateDfStatus($param_arr, $deposit_order_id)
    {
        $result = db('deposit')->where(['deposit_order_id' => $deposit_order_id])->setField([
            'return_json' => json_encode($param_arr, 320),
            'df_return_date' => time(),
        ]);
        return $result;
    }
}
