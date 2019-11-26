<?php
namespace app\admin2020\controller;
use think\Controller;
use think\Db;
use app\index2020\model\Statistics;
use app\admin2020\model\Admin;

class Common extends Controller
{
    protected $is_check_rule = true;//标识符设置是否需要检查权限
    protected $_admin = []; //保存用户相关的信息

    public function __construct()
    {
        parent::__construct();
        //禁止nginx代理服务器过来的数据
        if($_SERVER['REMOTE_ADDR'] == config('nginx_ip')) die('非法访问！');
        $ip = getClientIP();
        $blacklistInfo = db('blacklist')->where(['ip' => $ip])->find();
        if($blacklistInfo) showmessage('ip：'. $ip. '，已加入黑名单！');
        //检验后台用户权限
        $url2 = strtolower(request()->module(). '/'. request()->controller());
        //检验后台用户权限
        $url = strtolower($url2. '/'. request()->action());
        $urlException = [
            'admin2020/index/index',
            'admin2020/index/welcome',
        ];
        if($url == 'admin2020/api/paid' || $url2 != 'admin2020/api'){
            $this->_admin = session('_admin');
            if(!$this->_admin) $this->redirect('/admin2020/login/index');
            $this->_rule_ids = $this->_admin['rule_ids'];
            if(!$this->_rule_ids) $this->redirect('/admin2020/login/index');
            if(!defined('ADMIN_ID')) define('ADMIN_ID', $this->_admin['id']);
            if(!defined('ROLE_ID')) define('ROLE_ID', $this->_admin['role_id']);
            if(!defined('IS_INNER')) define('IS_INNER', $this->_admin['is_inner']);
            if(!defined('USER_NAME')) define('USER_NAME', $this->_admin['username']);
            if(!defined('RENT_RATE')) define('RENT_RATE', $this->_admin['rent_rate']);
            if(!defined('CHANNEL_ID')) define('CHANNEL_ID', $this->_admin['channel_id']);
            if(!defined('CHANNEL_RATE')) define('CHANNEL_RATE', $this->_admin['channel_rate']);
            $rule_ids = array_column($this->_rule_ids, 'url');
            //防止未登录操作
            if(!$this->_admin) $this->redirect('/admin2020/login/index');
            if(!in_array($url, $urlException) && !in_array($url, $rule_ids)) showmessage('权限不足！');
            //检验当前正在使用的后台管理是否存在或合法，否则就掉线
            $adminInfo = db('admin')->where(['id' => ADMIN_ID, 'username' => USER_NAME])->find();
            if(!$adminInfo) {
                $this->error('提示：账户不存在！', '/admin2020/login/logout', '', 15);
                die;
            }
            //验证密码是否被修改，否则就掉线
            if($adminInfo['password'] != session('_admin')['password']) {
                $this->error('提示：密码被修改！', '/admin2020/login/logout', '', 15);
                die;
            }
            //验证账户状态，否则就掉线
            if($adminInfo['status'] != 1) {
                $this->error('提示：账户状态被禁用！', '/admin2020/login/logout', '', 15);
                die;
            }
            //当前操作者IP是否是常用IP，若不是登录日志最后一次IP是否一致，否则掉线
            $used_ip = explode('|', config('used_ip'));
            if(!in_array($ip, $used_ip) && $adminInfo['ip'] != $ip) {
                $this->error('提示：当前网络（'.$ip.' | '. ip_to_city($ip, true). '）与最近登录（'.$adminInfo['ip'].' | '. ip_to_city($adminInfo['ip'], true). '）不一致！', '/admin2020/login/logout', '', 15);
                die;
            }
        }
    }

    public static function send_sms()
    {
        $userFlag = USER_NAME. '_sms';
        $time = time();
        $smsInfo = cache($userFlag);
        if($smsInfo && $smsInfo['success'] === true && $smsInfo['create_time'] && $time - $smsInfo['create_time'] < 60){
            showmessage('1分钟内只可发送1次验证码');
        }
        if($smsInfo && $smsInfo['success'] === true && $smsInfo['create_time'] && $time - $smsInfo['create_time'] < 60 * 5){
            $code = $smsInfo['code'];
        }else{
            $code = rand(10000, 99999);
        }
        // showmessage($code);
        $ret = sendSmsAli(config('PhoneNumbers'), $code);
        if($ret['status']){
            $data = [
                'code' => $code,
                'create_time' => $time,
                'success' => true,
            ];
            cache($userFlag, $data, 3600 * 12); //12小时内有效
            showmessage('短信发送成功！', 1);
        }
        $data = [
            'success' => false,
        ];
        cache($userFlag, $data, 3600 * 1); //1小时内有效
        showmessage('短信发送失败！'. $code. ' | ' .$ret['msg']);
    }

    protected static function setOrderPaid($platform_order_id, $innerType = 0)
    {
        $time = time();
        $orderModel = db('order');
        $orderInfo = $orderModel->lock(true)->where(['platform_order_id' => $platform_order_id])->find();
        if(!$orderInfo){
            self::showmessage2('订单号：'. $platform_order_id.'不存在！', $innerType);
        }
        if($orderInfo['pay_status'] != 1 && $orderInfo['pay_status'] != 3){ // 1：等待支付  3：成功未返回
            self::showmessage2('订单状态异常！', $innerType);
        }
        //记录已支付日志
        if($innerType == 1){
            if($orderInfo['pay_status'] == 1)  $msg = '未支付';
            if($orderInfo['pay_status'] == 3)  $msg = '成功未返回';
            log_operation('正在修改'. '订单状态 [ <font color="red">'.$msg .'</font> ] -> [ '. '<font color="red">已支付</font>'. ' ]，订单号：'. $platform_order_id. '，金额：' .$orderInfo['amount']);
        }
        // 启动事务
        Db::startTrans();
        try{
            //商户表member
            $memberInfo = db('member')->lock(true)->where(['member_id' => $orderInfo['member_id']])->find();
            //
            if($orderInfo['pay_status'] == 1 || $orderInfo['pay_status'] == 3 && $innerType == 1){  //未支付 || 成功未返回
                $data = [
                    'pay_status' => 3, //先设置成功未返回
                    'success_date' => $time,
                ];
                if($innerType == 1){
                    $data['username'] = USER_NAME; //内部手动补单回调
                }else{
                    $data['username'] = ''; //系统自动回调
                }
                //订单表order
                $result = $orderModel->where(['platform_order_id' => $platform_order_id])->setField($data);
                if(!$result){
                    Db::rollback();
                    self::showmessage2('设置订单状态失败', $innerType);
                }
            }

            //资金变动表money_change 此处增加商户明细订单数据
            $moneyChangeInfo = db('money_change')->lock(true)->where([
                'member_id' => $orderInfo['member_id'],
                'platform_order_id' => $orderInfo['platform_order_id'],
            ])->find();
            if($moneyChangeInfo) self::showmessage2('资金变动表记录已存在！', $innerType);
            //资金变动表money_change插入数据
            $data = [
                'member_id' => $memberInfo['member_id'],
                'before_money' => $memberInfo['balance'],
                'change_money' => $orderInfo['income_amount'],
                'after_money' => $memberInfo['balance'] + $orderInfo['income_amount'],
                'change_type' => 11, // 11：增加：订单充值
                'platform_order_id' => $orderInfo['platform_order_id'],
                'submit_order_id' => $orderInfo['submit_order_id'],
                'channel_id' => $orderInfo['channel_id'],
                'channel_name' => $orderInfo['channel_name'],
                'type_name' => $orderInfo['type_name'],
                'create_date' => time(),
            ];
            $result = db('money_change')->insert($data);
            if(!$result){
                Db::rollback();
                self::showmessage2('商户的资金变动表插入数据失败', $innerType);
            }
            //商户表member 此处增加商户余额
            $memberInfo = db('member')->lock(true)->where([
                'member_id' => $orderInfo['member_id']
            ])->find();
            if(!$memberInfo) self::showmessage2('商户不存在', $innerType);
            $result = db('member')->where(['member_id' => $memberInfo['member_id']])->setInc('balance', $orderInfo['income_amount']);
            if(!$result) {
                Db::rollback();
                self::showmessage2('商户余额增加失败', $innerType);
            }
            //判断当前商户是否有上级代理
            if($memberInfo['pid']){
                $memberInfoPid = db('member')->lock(true)->find($memberInfo['pid']);
                if(!$memberInfoPid)  self::showmessage2('商户上级代理账户不存在！', $innerType);
                //代理手续费必须大于0才可执行
                if($orderInfo['agent_poundage'] > 0){
                    $data = [
                        'member_id' => $memberInfoPid['member_id'],
                        'before_money' => $memberInfoPid['balance'],
                        'change_money' => $orderInfo['agent_poundage'],
                        'after_money' => $memberInfoPid['balance'] + $orderInfo['agent_poundage'],
                        'change_type' => 14, //增加：代理分成
                        'platform_order_id' => $orderInfo['platform_order_id'],
                        'submit_order_id' => $orderInfo['submit_order_id'],
                        'channel_id' => $orderInfo['channel_id'],
                        'channel_name' => $orderInfo['channel_name'],
                        'type_name' => $orderInfo['type_name'],
                        'create_date' => time(),
                    ];
                    $result = db('money_change')->insert($data);
                    if(!$result) {
                        Db::rollback();
                        self::showmessage2('商户上级代理账户的资金变动表插入数据失败', $innerType);
                    }
                    //商户表member 此处增加商户上级代理的余额
                    $result = db('member')->where(['member_id' => $memberInfoPid['member_id']])->setInc('balance', $orderInfo['agent_poundage']);
                    if(!$result) {
                        Db::rollback();
                        self::showmessage2('商户上级代理账户的余额增加失败', $innerType);
                    }
                }
            }
            $channelInfo = db('channel')->lock(true)->find($orderInfo['channel_id']);
            if(!$channelInfo) self::showmessage2('通道不存在', $innerType);
            //通道表channel 此处增加通道可下发余额
            $result = db('channel')->where(['id' => $orderInfo['channel_id']])->setInc('channel_money', $orderInfo['income_amount']);
            if(!$result) {
                Db::rollback();
                self::showmessage2('通道余额增加失败', $innerType);
            }
            //数据统计
            $params = [
                'member_id' => $orderInfo['member_id'],
                'total_money' => $orderInfo['amount'],
                'platform_deposit' => $orderInfo['platform_poundage'],
                'passage_deposit' => $orderInfo['passage_poundage'],
                'type' => 2, // 2为成功支付订单，累计商户入金，代理收入，平台入金
            ];
            //如果代理存在 就累积代理收入
            if($orderInfo['agent_poundage'] > 0){
                $params['agent_income'] = $orderInfo['agent_poundage'];
            }

            // Statistics::checkMember($params);

            //对微信店员通回调成功的账户入库做特殊处理
            if($orderInfo['channel_id'] == '301' && $orderInfo['relate_key'] && $orderInfo['access_ip']){
                $result = db('wechat_order_success')->lock(true)->where([
                    'platform_order_id' => $orderInfo['platform_order_id'],
                ])->find();
                if(!$result && (float)$orderInfo['amount'] > 1){
                    $province_city = ip_to_city($orderInfo['access_ip'], false);
                    if($province_city == '中国' || strpos($province_city, '省') === false){
                        $province_city = baidu_map_ip($orderInfo['access_ip']);
                    }
                    $province_city = only_province_city($province_city);
                    $data = [
                        'name' => $orderInfo['relate_key'],
                        'platform_order_id' => $orderInfo['platform_order_id'],
                        'amount' => $orderInfo['amount'],
                        'ip' => $orderInfo['access_ip'],
                        'province_city' => $province_city,
                        'success_date' => $time,
                    ];
                    db('wechat_order_success')->insert($data);
                }
            }
            // 提交事务
            Db::commit();
            //事务成功提交后
            $param = [
                'return_code' => 0, //判断回调接收的标准（只有成功才发送）
                'member_id' => $orderInfo['member_id'],
                'amount' => $orderInfo['amount'],
                'submit_order_id' => $orderInfo['submit_order_id'],
                'success_date' => $time,
            ];
            $param['sign'] = self::makeSign($param, $memberInfo['apikey']);
            //开始发回调给下游
            $contents = curl_post($param, $orderInfo['notify_url']);
            //做下游回调日志
            file_put_contents(__DIR__. '/../../../extend/log/send/'.date('Ymd').'.txt', date('Y-m-d H:i:s').' | '.$platform_order_id.' | '.json_encode($param). ' | '. $orderInfo['notify_url']. ' | ' .$contents. PHP_EOL, FILE_APPEND);
            $data = ['return_msg' => $contents];
            $flag = false;
            if (strstr(strtolower($contents), "ok") != false  || strstr(strtolower($contents), "success") != false) {
                $flag = true;
                $data[ 'pay_status'] = 2; //此处成功已返回
                //订单表order
                $result = $orderModel->where(['platform_order_id' => $platform_order_id])->update($data);
                if(!$result){
                    self::showmessage2('设置订单状态【已返回】失败', $innerType);
                }
            }
            if($innerType == 1){
                self::showmessage2('补发地址：'.$orderInfo['notify_url'].'<br/>补发结果：'. ($flag ? '成功' : '失败' ).'！<br/>下游返回值：<br/>'. $contents, $innerType, $flag ? 1 : 0);
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            showmessage($e->getMessage());
        }
    }

    protected static function makeSign($param, $apikey)
    {
        ksort($param);
        $md5str = "";
        foreach ($param as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        return strtoupper(md5($md5str . "key=" . $apikey));
    }

    protected static function showmessage2($msg, $innerType, $type=0)
    {
        if($innerType == 0) exit($msg);
        if($innerType == 1) showmessage($msg, $type);
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
        file_put_contents($fileUrl.date('Ymd').'.txt', PHP_EOL. date('H:i:s') . ' | '. $controllerName .  ' | ' . $str , FILE_APPEND);
        if(!$str) exit('接收到空数据！');
        return $params_arr;
    }

}
