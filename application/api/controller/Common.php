<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/23
 * Time: 16:41
 */

namespace app\api\controller;

use think\Db;
use think\Controller;
use app\api\model\Order;
use app\index2020\model\Statistics;

class Common extends Controller
{
    protected static $alipayqr = 'https://ds.alipay.com/?from=mobilecodec&scheme=';
    protected static $taobao = 'taobao://render.alipay.com/p/s/i?scheme=';
    protected static $alipayH5 = 'https://render.alipay.com/p/s/i?scheme=';
    protected static $alipays = 'alipayqr://platformapi/startapp?saId=10000007&qrcode=';

    public function __construct()
    {
        parent::__construct();
        $ip = getClientIP();
        $blacklistInfo = db('blacklist')->where(['ip' => $ip])->find();
        if($blacklistInfo) showmessage('ip：'. $ip. '，已加入黑名单！！');
    }

    public static function createOrder($param)
    {
        $model = db('order');
        $info = $model->where(['submit_order_id' => $param['submit_order_id']])->find();
        if($info) showmessage('订单号：['. $param['submit_order_id']. '] 已存在！');
        //生成平台订单号
        $param['platform_order_id'] = config('platform_name_en'). time(). rand(10000,99999);
        $param['create_date'] = time();
        //插入订单表数据
        $result = $model->insert($param);
        if(!$result) showmessage('生成订单失败');
        $orderInfo = $model->where(['submit_order_id' => $param['submit_order_id']])->find();
        return $orderInfo;
    }

    public static function createDeposit($memberInfo, $requestData, $deposit_type, $submit_type)
    {//$submit_type=1->手动，$submit_type=2->代付    $submit_type=1->到账余额扣除，$submit_type=2->商户余额扣除
        // 启动事务
        Db::startTrans();
        try{
            if((float)$requestData['amount'] <= 0){
                showmessage('提现余额必须大于0！');
            }
            $drawingsInfo = db('drawings')->find();
            if(!$drawingsInfo){
                showmessage('提现配置异常！');
            }
            if($requestData['amount'] < $drawingsInfo['min_money'] || $requestData['amount'] > $drawingsInfo['max_money']){
                showmessage('提现金额限制：'.$drawingsInfo['min_money']. '~'. $drawingsInfo['max_money']);
            }
            if($requestData['amount'] <= $memberInfo['deposit_amount']){
                showmessage('提现金额必须大于商户下发手续费！手续费：'. $memberInfo['deposit_amount']);
            }
            if($memberInfo['deposit_type'] == 2 && (float)$memberInfo['deposit_amount'] > 0){
                $reduceMoney = $requestData['amount'] + $memberInfo['deposit_amount'];
                $depositMoney = $requestData['amount'];
                $changeMoney = $requestData['amount'];
            }else{
                $reduceMoney = $requestData['amount'];
                $depositMoney = $requestData['amount'] - $memberInfo['deposit_amount'];
                $changeMoney = $requestData['amount'] - $memberInfo['deposit_amount'];
            }
            if($reduceMoney > $memberInfo['balance']){
                $deposit_type_msg = $memberInfo['deposit_type'] == 1 ? '从到账余额扣除' : '从商户余额扣除';
                showmessage('商户余额不足！当前剩余：'. bcadd($memberInfo['balance'], 0, 2). '，手续费：'. bcadd($memberInfo['deposit_amount'], 0, 2). '/笔，扣除方式：'. $deposit_type_msg);
            }
            //扣除商户余额
            $result = db('member')->where('member_id', $memberInfo['member_id'])->setDec('balance', $reduceMoney);
            if(!$result){
                Db::rollback();
                showmessage('商户余额扣除失败');
            }
            $deposit_order_id = 'df'. time(). rand(10000,99999);
            //$submit_type=1->手动，$submit_type=2->代付
            if($submit_type == 1){
                $submit_order_id = '-';
            }else{
                $submit_order_id = $requestData['submit_order_id'];
            }
            $data = [
                'deposit_order_id' => $deposit_order_id,
                'submit_order_id' => $submit_order_id,
                'member_id' => $memberInfo['member_id'],
                'bank_name' => $requestData['bank_name'],
                'bank_branch_name' => $requestData['bank_branch_name'],
                'bank_card_no' => $requestData['bank_card_no'],
                'bank_account' => $requestData['bank_account'],
                'province' => $requestData['province'],
                'city' => $requestData['city'],
                'create_date' => time(),
                'amount' => $depositMoney,
                'deposit_amount' => $memberInfo['deposit_amount'],
                'deposit_type' => $deposit_type,
                'submit_type' => $submit_type,
            ];
            $result = db('deposit')->insert($data);
            if(!$result){
                Db::rollback();
                showmessage('结算申请失败');
            }
            //增加提现金额的资金变动记录
            $data = [
                'member_id' => $memberInfo['member_id'],
                'before_money' => $memberInfo['balance'],
                'change_money' => $changeMoney,
                'after_money' => $memberInfo['balance'] - $changeMoney,
                'change_type' => 23,//结算申请
                'create_date' => time(),
            ];
            $result = db('money_change')->insert($data);
            if(!$result){
                Db::rollback();
                showmessage('添加提现金额到资金变动记录失败');
            }
            //增加商户手续费的资金变动记录
            if((float)$memberInfo['deposit_amount'] > 0){
                $afterMoney = $memberInfo['balance'] - $changeMoney;
                $data = [
                    'member_id' => $memberInfo['member_id'],
                    'before_money' => $afterMoney,
                    'change_money' => $memberInfo['deposit_amount'],
                    'after_money' => $afterMoney - $memberInfo['deposit_amount'],
                    'change_type' => 22,//结算手续费
                    'create_date' => time(),
                ];
                $result = db('money_change')->insert($data);
                if(!$result){
                    Db::rollback();
                    showmessage('添加提现手续费到资金变动记录失败');
                }
            }
            // 提交事务
            Db::commit();
            $data = ['code' => 200, 'msg' => 'success', 'deposit_order_id' => $deposit_order_id];
            $data['sign'] = self::makeSign($data, $memberInfo['apikey']);
            //返回成功json
            show_success($data);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $data = ['code' => 201, 'msg' => $e->getMessage()];
            $data['sign'] = self::makeSign($data, $memberInfo['apikey']);
            echo json_encode($data, 320);die;
        }
    }

    public static function orderToController($requestData, $memberInfo, $channelInfo)
    {
        //加入通道数据
        $requestData['channel_id'] = $channelInfo['id'];
        $requestData['channel_name'] = $channelInfo['name_cn'];
        //获取不同type_name下的商户费率
        //先假设该商户上级代理费率为0
        $ratePid = 0;
        //假设默认 $requestData['type_name'] = 'alipay'
        $memberRate = $memberInfo[$requestData['type_name'].'_rate'];
        //查询当前商户是否有上级代理，并重新赋值上级代理费率
        if($memberInfo['pid'] > 0){
            $memberInfoPid = db('member')->find($memberInfo['pid']);
            if(!$memberInfoPid) showmessage('商户上级代理数据异常！');
            $ratePid = $memberInfoPid[$requestData['type_name'].'_rate'];
        }
        //计算后平台、代理手续费率差
        if($ratePid) {
            $platform_rate_diff = $ratePid - $channelInfo['rate'];
            $agent_rate_diff = $memberRate - $ratePid;
        }else{
            $platform_rate_diff = $memberRate - $channelInfo['rate'];
            $agent_rate_diff = 0;
        }
        if($platform_rate_diff < 0 || $agent_rate_diff < 0)  showmessage('商户关联费率设置异常！');
        //赋值$requestData生成订单数据
        $requestData['actual_amount'] = $requestData['amount'];
        $requestData['platform_poundage'] = $requestData['amount'] * $platform_rate_diff;
        $requestData['agent_poundage'] = $requestData['amount'] * $agent_rate_diff;
        $requestData['passage_poundage'] = $requestData['amount'] * $channelInfo['rate'];
        $requestData['income_amount'] = $requestData['amount'] * (1 - $platform_rate_diff - $agent_rate_diff - $channelInfo['rate']);
        $requestData['relate_key'] = '0';

        //生成订单
        $result = static::createOrder($requestData);
        if(!$result) showmessage('创建订单失败');
        $result['apikey'] = $memberInfo['apikey'];
        $result['curl'] = $channelInfo['form_submit'] == 2 ? 2 : 1;
        //找到商户分配的对应通道文件类
        $file = __DIR__.'/'. $channelInfo['name_en']. '.php';
        if(!is_file($file)) showmessage($channelInfo['name_en']. '：文件不存在！！！');
        include_once $file ;
        $className = '\\app\api\controller\\'. $channelInfo['name_en'];
        $className::pay($result);
    }


    protected static function getRequestData()
    {
        if(!input('post.')) showmessage('必须有post数据！');
        //保存参数数组
        $param = [];
        //商户编号
        $param['member_id'] = input('member_id', '');
        if(!$param['member_id']) showmessage('member_id：不能为空！');
        //下游提交订单号
        $param['submit_order_id'] = input('submit_order_id', '');
        if(!$param['submit_order_id']) showmessage('submit_order_id：不能为空！');
        //下游订单号不能带有平台内部英文名platform_name_en
        if(config('platform_name_en') && strpos($param['submit_order_id'], config('platform_name_en')) !== false) showmessage('submit_order_id：格式不合法！请更换！');
        if(strlen($param['submit_order_id']) < 15 || strlen($param['submit_order_id']) > 30) showmessage('submit_order_id：长度必须15~30位！');
        //订单发起金额
        $param['amount'] = input('amount', '');
        $amount = (float) $param['amount'];
        if(!$amount) showmessage('amount：不能为空！');
        if(floor($amount) > 1 && floor($amount) != $amount) showmessage('amount：不能为小数');
        if(floor($amount) <= 0) showmessage('amount：必须是正整数！');
        //订单支付类型（alipay、wechat等等）
        $param['type_name'] = input('type_name', '');
        if(!$param['type_name']) showmessage('type_name：不能为空！');
        //服务器异步回调地址
        $param['notify_url'] = input('notify_url', '');
        if(!$param['notify_url']) showmessage('notify_url：不能为空！');
        //页面同步回调地址
        $param['callback_url'] = input('callback_url', '');
        if(!$param['callback_url']) showmessage('callback_url：不能为空！');
        ksort($param);
        return $param;
    }

    protected static function getRequestDaifuData()
    {
        if(!input('post.')) showmessage('必须有post数据！');
        //保存参数数组
        $param = [];
        //商户编号
        $param['member_id'] = input('member_id', '');
        if(!$param['member_id']) showmessage('member_id：不能为空！');
        //订单发起金额
        $param['amount'] = input('amount', '');
        $amount = (float) $param['amount'];
        if(!$amount) showmessage('amount：不能为空！');
        if(floor($amount) > 1 && floor($amount) != $amount) showmessage('amount：不能为小数');
        if(floor($amount) <= 0) showmessage('amount：必须是正整数！');
        //开户行名称
        $param['bank_name'] = input('bank_name', '');
        if(!$param['bank_name']) showmessage('bank_name：不能为空！');
        //支行名称
        $param['bank_branch_name'] = input('bank_branch_name', '');
        if(!$param['bank_branch_name']) showmessage('bank_branch_name：不能为空！');
        //开户名
        $param['bank_account'] = input('bank_account', '');
        if(!$param['bank_account']) showmessage('bank_account：不能为空！');
        //银行卡号
        $param['bank_card_no'] = input('bank_card_no', '');
        if(!$param['bank_card_no']) showmessage('bank_card_no：不能为空！');
        //省份
        $param['province'] = input('province', '');
        if(!$param['province']) showmessage('province：不能为空！');
        //城市
        $param['city'] = input('city', '');
        if(!$param['city']) showmessage('city：不能为空！');
        //时间戳
        $param['time'] = input('time', '');
        if(!$param['time']) showmessage('time：不能为空！');
        //下游提交的提现订单号
        $param['submit_order_id'] = input('submit_order_id', '');
        if(!$param['submit_order_id']) showmessage('submit_order_id：不能为空！');
        ksort($param);
        return $param;
    }

    protected static function getRequestDaifuQueryData()
    {
        if(!input('post.')) showmessage('必须有post数据！');
        //保存参数数组
        $param = [];
        //商户编号
        $param['member_id'] = input('member_id', '');
        if(!$param['member_id']) showmessage('member_id：不能为空！');
        //提现单号
        $param['submit_order_id'] = input('submit_order_id', '');
        if(!$param['submit_order_id']) showmessage('submit_order_id：不能为空！');
        //时间戳
        $param['time'] = input('time', '');
        if(!$param['time']) showmessage('time：不能为空！');
        ksort($param);
        return $param;
    }

    protected static function getRequestTradeQueryData()
    {
        if(!input('post.')) showmessage('必须有post数据！');
        //保存参数数组
        $param = [];
        //商户编号
        $param['member_id'] = input('member_id', '');
        if(!$param['member_id']) showmessage('member_id：不能为空！');
        //提现单号
        $param['submit_order_id'] = input('submit_order_id', '');
        if(!$param['submit_order_id']) showmessage('submit_order_id：不能为空！');
        //时间戳
        $param['time'] = input('time', '');
        if(!$param['time']) showmessage('time：不能为空！');
        ksort($param);
        return $param;
    }

    protected static function getRequestMemberBalanceQueryData()
    {
        if(!input('post.')) showmessage('必须有post数据！');
        //保存参数数组
        $param = [];
        //商户编号
        $param['member_id'] = input('member_id', '');
        if(!$param['member_id']) showmessage('member_id：不能为空！');
        $param['time'] = input('time', '');
        if(!$param['time']) showmessage('time：不能为空！');
        ksort($param);
        return $param;
    }

    protected static function makeSign($param, $apikey)
    {
        ksort($param);
        $md5_str = '';
        foreach ($param as $k => $v) {
            if(!$v) continue;
            $md5_str .= $k. '='. $v. "&";
        }
        $md5_str .= 'key='. $apikey;
        return strtoupper(md5($md5_str));
    }

    protected static function makeSign2($param, $apikey)
    {
        $md5_str = '';
        foreach ($param as $k => $v) {
            if(!$v) continue;
            $md5_str .= $k. '='. $v. "&";
        }
        $md5_str .= 'key='. $apikey;
        return md5(strtoupper($md5_str));
    }

    //返回给下游的成功数据
    protected static function returnError($error, $orderInfo)
    {
        //数据库保存上游错误提示
        db('order')->where(['platform_order_id' => $orderInfo['platform_order_id']])->setField([
            'poundage_error' => $error,
            'pay_status' => 98,
        ]);
        //上游错误提示不走record_error
        showmessage($error);
    }

    //返回给下游的成功数据
    protected static function returnSuccess($qrcode, $orderInfo)
    {
        header('Content-type:text/html;charset=utf-8');
        $return_arr = [
            'amount' => $orderInfo['amount'],
            'qrcode' => $qrcode,
        ];
        $return_arr['sign'] = self::makeSign($return_arr, $orderInfo['apikey']);
        $data = ["code" => 200, "msg" => 'success', "data" => $return_arr];
        //返回成功json
        show_success($data);
    }

    //记录访问日志
    protected static function logAccessData($action, $module = 'api')
    {
        $ip = getClientIP();
        file_put_contents('../extend/log/'. $module .'/'.date('Ymd').'.txt', PHP_EOL. date('H:i:s') . ' | '. $action. ' | '. $ip, FILE_APPEND);
        return $ip;
    }

    //返回post或get参数数据并做log
    protected static function logReturnArr($controllerName, $fileUrl = '../extend/log/notify/')
    {
        if(!file_exists($fileUrl)) mkdir($fileUrl);
        $str = file_get_contents("php://input");
        if(!$str){
            $params_arr = $_POST ? $_POST : $_GET;
            $str = json_encode($params_arr, 320);
        }else{
            $params_arr = json_decode($str, true);
            $params_arr ? $params_arr : parse_str($str, $params_arr);
        }
        if(!$str) exit('接收到空数据！');
        ksort($params_arr);
        $str = json_encode($params_arr, 320);
        file_put_contents($fileUrl.date('Ymd').'.txt', PHP_EOL. date('H:i:s') . ' | '. $controllerName .  ' | ' . $str , FILE_APPEND);
        return $params_arr;
    }

    //返回post或get参数数据并做log
    protected static function logReturnHeader($controllerName, $fileUrl = '../extend/log/notify/')
    {
        if(!file_exists($fileUrl)) mkdir($fileUrl);
        $str = file_get_contents("php://input");
        if(!$str){
            $params_arr = $_POST ? $_POST : $_GET;
            $str = json_encode($params_arr, 320);
        }else{
            $params_arr = json_decode($str, true);
            $params_arr ? $params_arr : parse_str($str, $params_arr);
        }
        if(!$str) exit('接收到空数据！');
        ksort($params_arr);
        $str = json_encode($params_arr, 320);
      	$headers = getallheaders();
		$httpAuthorization = isset($headers ['Authorization']) ? $headers ['Authorization'] : '';
      	if($httpAuthorization) {
      		$params_arr['Authorization'] = $httpAuthorization;
        }
        file_put_contents($fileUrl.date('Ymd').'.txt', PHP_EOL. date('H:i:s') . ' | '. $controllerName .  ' | ' . $str .  ' | ' .$httpAuthorization , FILE_APPEND);
        return $params_arr;
    }

    protected static function echoForm($param, $action = 'post')
    {
        $str = '<form id="form" action="'. static::$gateway. '" method="'. $action.'" style="display:none;">';
        foreach ($param as $k => $v) {
            $str .= '<input type="hidden" name="'. $k.'" value="'. $v. '">';
        }
        $str .= '</form>';
        $str .= '<script>document.getElementById("form").submit()</script>';
        echo $str;
    }

    protected static function changeChannel($param, $orderInfo)
    {
        if(!$param){
            $channelInfo = db('channel')->where(['name_en' => static::$controllerName])->find();
            $result = db('order')->where(['platform_order_id' => $orderInfo['platform_order_id']])->setField([
                'channel_id' => $channelInfo['id'],
                'channel_name' => $channelInfo['name_cn'],
            ]);
            if(!$result) showmessage('修改通道-'. $channelInfo['name_cn'] .'-失败！');
            log_operation('订单号：' .$orderInfo['platform_order_id']. '，修改通道：[ <span style="color:red">'. $orderInfo['channel_name']. '</span> ] -> [  <span style="color:red">' . $channelInfo['name_cn'] .'</span> ] 成功！');
            showmessage('修改通道-'. $channelInfo['name_cn'] .'-成功！', 1);
        }else{
            $content = curl_post($param, static::$gateway);
            $content_arr = json_decode($content, true);
            if($content_arr){
                if($content_arr['code'] == 200){
                    $channelInfo = db('channel')->where(['name_en' => static::$controllerName])->find();
                    $result = db('order')->where(['platform_order_id' => $orderInfo['platform_order_id']])->setField([
                        'channel_id' => $channelInfo['id'],
                        'channel_name' => $channelInfo['name_cn'],
                    ]);
                    if(!$result) showmessage('修改通道-'. $channelInfo['name_cn'] .'-失败！');
                    log_operation('订单号：' .$orderInfo['platform_order_id']. '，修改通道：[ <span style="color:red">'. $orderInfo['channel_name']. '</span> ] -> [  <span style="color:red">' . $channelInfo['name_cn'] .'</span> ] 成功！');
                    showmessage('修改通道-'. $channelInfo['name_cn'] .'-成功！', 1);
                }else{
                    showmessage($content_arr['msg']);
                }
            }else if(strpos($content, 'post("/pay/order')){
                $channelInfo = db('channel')->where(['name_en' => static::$controllerName])->find();
                $result = db('order')->where(['platform_order_id' => $orderInfo['platform_order_id']])->setField([
                    'channel_id' => $channelInfo['id'],
                    'channel_name' => $channelInfo['name_cn'],
                ]);
                if(!$result) showmessage('修改通道-'. $channelInfo['name_cn'] .'-失败！');
                log_operation('订单号：' .$orderInfo['platform_order_id']. '，修改通道：[ <span style="color:red">'. $orderInfo['channel_name']. '</span> ] -> [  <span style="color:red">' . $channelInfo['name_cn'] .'</span> ] 成功！');
                showmessage('修改通道-'. $channelInfo['name_cn'] .'-成功！', 1);
            }else{
                showmessage('无法成功获得链接，修改失败');
            }
        }
    }

}