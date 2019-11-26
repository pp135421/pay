<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 13:08
 */

namespace app\admin2020\controller;
use app\admin2020\model\Order as OrderModel;
use app\admin2020\model\Moneychange;
use think\Controller;
use think\Db;

class Order extends Common
{
    public  function index()
    {
        $where = [];
        $whereChannel = [];
        //处理订单创建时间范围
        $create_time = input('create_time', '', 'trim,urldecode');
        $arr_create_time = explode('|', $create_time);
        if($arr_create_time && count($arr_create_time) == 2){
            if(count($arr_create_time) == 2){
                $where['create_date'] = array('between', array(strtotime($arr_create_time[0]), strtotime($arr_create_time[1])));
            }
        }

        //处理订单成功时间范围
        $success_time = input('success_time', '', 'trim');
        $arr_success_time = explode('|', $success_time);
        if($arr_success_time && count($arr_success_time) == 2){
            if(count($arr_success_time) == 2){
                $where['success_date'] = array('between', array(strtotime($arr_success_time[0]), strtotime($arr_success_time[1])));
            }
        }

        //处理通道
        $member_id = input('member_id', '', 'trim');
        if($member_id){
            $where['member_id'] = $member_id;
            $whereChannel['member_id'] = $member_id;
        }

        //处理平台订单号或下游订单号或商户号或实际付款金额
        $whereX = [];
        $whereX2 = [];
        $keyword = input('keyword', '', 'trim');
        if($keyword){
            $whereX['platform_order_id|submit_order_id|actual_amount|province_city|access_ip'] = $keyword;
            $whereX2['relate_key'] = base64_encode($keyword);
        }

        //处理连接方式
        $access_type = input('access_type', '');
        if($access_type){
            if($access_type == 'H5'){
                $where['access_type'] = [['like' ,'%Android%'] ,['like' , '%iphone%'] ,'or'] ;
                $whereChannel['access_type'] = [['like' ,'%Android%'] ,['like' , '%iphone%'] ,'or'] ;
            }else if($access_type == 'Android' || $access_type == 'iphone'){
                $where['access_type'] = array('like', '%'.$access_type.'%');
                $whereChannel['access_type'] = array('like', '%'.$access_type.'%');
            }else if($access_type == 'PC'){
                $where['access_type'] = array('like', '%Win%');
                $whereChannel['access_type'] = array('like', '%Win%');
            }else{
                $where['access_type'] = 'wait';
                $whereChannel['access_type'] = 'wait';
            }
        }

        //处理通道
        $channel_id = input('channel_id', '');
        if($channel_id){
            $where['channel_id'] = $channel_id;
            $whereChannel['channel_id'] = $channel_id;
        }

        //处理订单状态
        $pay_status = input('pay_status', '');
        if($pay_status){
            $where['pay_status'] = $pay_status;
        }

        //处理订单状态
        $is_freeze = input('is_freeze', '');
        if($is_freeze){
            $where['is_freeze'] = $is_freeze;
        }

        //回调类型
        $username = input('username', '');
        if($username){
            $where['username'] = ['neq', ''];
        }

        //处理接口
        $type_name = input('type_name', '');
        if($type_name){
            $where['type_name'] = $type_name;
        }
        //处理其他控制器传过来的指定平台订单号数据
        $platform_order_id_arr = input('platform_order_id_arr', '') ? explode(',', input('platform_order_id_arr', '')) : [];
        if($platform_order_id_arr){
            $where['platform_order_id'] = ['in', $platform_order_id_arr];
        }
        //处理其他控制器传过来的指定平台订单号数据
        $relate_key = input('relate_key', '') ;
        if($relate_key){
            $where['relate_key'] = urldecode($relate_key);
        }
        //每页显示30条数据
        $perCount = 30;
        $list = OrderModel::where($where)->where(function($query) use($whereX, $whereX2){
            $query->where($whereX)->whereOr($whereX2);
        })->order('id desc')->paginate($perCount, false, ['query'=>input()]);
        // dump(db('order')->getLastSql());
        $orderArr = $list->toArray()['data'];
        //获得当前表的全部条数
        // $orderTempArr = OrderModel::field('pay_status')->where($where)->select();
        // $count = count($orderTempArr);
        // $countSuccess = 0;
        // foreach ($orderTempArr as $k => $v) {
        //     if(in_array($v['pay_status'], [2, 3, 4])){
        //          $countSuccess++;
        //     }
        // }
        $count = 0;
        $countSuccess = 0;
        // foreach ($orderTempArr as $k => $v) {
        //     if(in_array($v['pay_status'], [2, 3, 4])){
        //          $countSuccess++;
        //     }
        // }
        //获取全部商户数据
        $memberData = db('member')->field('member_id, nickname')->order('balance desc, id desc')->select();
        foreach ($orderArr as $k => $v) {
            foreach ($memberData as $k2 => $v2) {
                if($v['member_id'] == $v2['member_id']){
                    $orderArr[$k]['nickname'] = $v2['nickname'];
                }
            }
        }

        //判断表alipay_account是否存在
        $isTable = db()->query('SHOW TABLES LIKE "alipay_account"');
        if($isTable){
        //获取全部支付宝账户数据
            $alipayAccountData = db('alipay_account')->select();
            foreach ($orderArr as $k => $v) {
                foreach ($alipayAccountData as $k2 => $v2) {
                    if($v['relate_key'] == $v2['account']){
                        $orderArr[$k]['relate_key_name'] = $v2['name'];
                    }
                }
            }
        }

        //判断表alipay_bank是否存在
        $isTable = db()->query('SHOW TABLES LIKE "alipay_bank"');
        if($isTable){
            //获取全部支付宝账户数据
            $alipayAccountData = db('alipay_bank')->select();
            foreach ($orderArr as $k => $v) {
                foreach ($alipayAccountData as $k2 => $v2) {
                    if($v['relate_key'] == $v2['card_no']){
                        $orderArr[$k]['relate_key_name'] = $v2['bank_mark'].'_'.$v2['bank_account'];
                    }
                }
            }
        }

        //判断表dingding是否存在
        $isTable = db()->query('SHOW TABLES LIKE "dingding"');
        if($isTable){
            $dingdingData = db('dingding')->select();
            foreach ($orderArr as $k => $v) {
                foreach ($dingdingData as $k2 => $v2) {
                    if($v['relate_key'] == $v2['name']){
                        $orderArr[$k]['relate_key_name'] = $v2['name'];
                    }
                }
            }
        }

        //获取全部通道数据
        $channelData = db('channel')->field('id, name_cn, type_name')->order('is_inner asc, create_date asc')->select();
        // $tempArr = array_column($channelData, 'name_cn');
        // if($tempArr) array_multisort($tempArr, SORT_ASC, $channelData);
        //计算最近1小时成功率
        $countHourSuccess = 0;
        $countHourTotal = 0;
        $countDaySuccess = 0;
        $countDayTotal = 0;

        $orderData = db('order')->field('amount, create_date, success_date, pay_status, channel_name, access_type, type_name')->where($whereChannel)->select();
        $successPassway = [];
        //1分钟内订单数
        $time = time();
        $secondCount = 0;
        $successAmount = 0;
        $hourCountNoAccess = 0;
        foreach ($orderData as $k => $v){
            if($v['create_date'] <= $time  && $v['create_date'] >= $time - 60){
                $secondCount++;
            }
            if(date('Y-m-d') == date('Y-m-d', $v['success_date'])){
                $successAmount += (float)$v['amount'];
            }
            if($v['create_date'] <= $time  && $v['create_date'] >= $time - 3600){
                if(!isset($successPassway[$v['channel_name']]['allCount'])) $successPassway[$v['channel_name']]['allCount'] = 0;
                if(!isset($successPassway[$v['channel_name']]['successCount'])) $successPassway[$v['channel_name']]['successCount'] = 0;
                if(!isset($successPassway[$v['channel_name']]['type_name'])) $successPassway[$v['channel_name']]['type_name'] = $v['type_name'];
                $successPassway[$v['channel_name']]['allCount'] ++;
                $countHourTotal++;
                if($v['pay_status'] == 2 || $v['pay_status'] == 3){
                    $successPassway[$v['channel_name']]['successCount'] ++;
                    $countHourSuccess++;
                }
                if($v['access_type'] == 'wait'){
                    $hourCountNoAccess++;
                }
            }
            if($v['create_date'] <= $time  && $v['create_date'] >= $time - 3600 * 24){
                $countDayTotal++;
                if($v['pay_status'] == 2 || $v['pay_status'] == 3){
                    $countDaySuccess++;
                }
            }
        }
        foreach ($successPassway as $k => $v) {
            $successPassway[$k]['rate'] = $v['allCount'] ? bcadd(($v['successCount'] / $v['allCount']) * 100, 0, 2) : '0';
        }
        $rateArr = array_column($successPassway, 'rate');
        array_multisort($rateArr, SORT_DESC, $successPassway);
        $successHourRate = $countHourTotal ? bcadd(($countHourSuccess / $countHourTotal) * 100, 0, 2) . '%' : '0%';
        $hourCountNoAccessRate = $countHourTotal ? bcadd(($hourCountNoAccess / $countHourTotal) * 100, 0, 2) . '%' : '0%';
        $successDayRate = $countDayTotal ? bcadd(($countDaySuccess / $countDayTotal) * 100, 0, 2) . '%' : '0%';

        $blacklistData = db('blacklist')->select();
        foreach ($orderArr as $k => $v) {
            foreach ($blacklistData as $k2 => $v2) {
                if($v['access_ip'] == $v2['ip']){
                    $orderArr[$k]['access_ip'] = $v2['ip']. '（黑）';
                }
            }
        }
        //回显HTML数据
        $showData = [
            'create_time' => $create_time,
            'success_time' => $success_time,
            'keyword' => $keyword,
            'pay_status' => $pay_status,
            'access_type' => $access_type,
            'channel_id' => $channel_id,
            'type_name' => $type_name,
            'is_freeze' => $is_freeze,
            'successHourRate' => $successHourRate,
            'successDayRate' => $successDayRate,
            'hourCountNoAccessRate' => $hourCountNoAccessRate,
            'secondCount' => $secondCount,
            'successAmount' => $successAmount,
            'hourCountNoAccess' => $hourCountNoAccess,
            'changeTime' => input('changeTime', ''),
            'username' => $username,
            'relate_key' => $relate_key,
            'member_id' => $member_id,
            'count' => $count,
            'countSuccess' => $countSuccess,
        ];
        return view('index', compact('list', 'showData', 'channelData', 'orderArr', 'successPassway', 'memberData'));
    }

    public function change_channel()
    {
        $platform_order_id = input('platform_order_id', '');
        $info = db('order')->where(['platform_order_id' => $platform_order_id])->find();
        if(request()->isGet()){
            $channelData = db('channel')->where(['status' => 1])->select();
            return view('change_channel', compact('info', 'channelData'));
        }
        $channel_id = input('channel_id', 0);
        if(!$channel_id) showmessage('请选择一个通道修改！');
        $channelInfo = db('channel')->find($channel_id);
        $memberInfo = db('member')->where(['member_id' => $info['member_id']])->find();
        //找到商户分配的对应通道文件类
        $file = ROOT_PATH.'application/api/controller/'. $channelInfo['name_en']. '.php';
        if(!is_file($file)) showmessage($channelInfo['name_en']. '：文件不存在！！！');
        include_once $file ;
        $className = '\\app\api\controller\\'. $channelInfo['name_en'];
        $info['apikey'] = $memberInfo['apikey'];
        $info['curl'] = 1;
        $info['change_channel'] = 1;
        // showmessage($info['curl']);
        $className::pay($info);
    }

    public function blacklist()
    {
        $ip = input('ip', '');
        if(!$ip) showmessage('ip地址不能为空！');
        $flag = input('flag', false);
        if($flag) {
            $blacklistInfo = db('blacklist')->where([
                'ip' => $ip
            ])->find();
            if($blacklistInfo){
                $result = db('blacklist')->where([
                    'ip' => $ip,
                ])->delete();
                if($result){
                    showmessage('黑名单：'. $ip. '，移出成功！', 200);
                }else{
                    showmessage('黑名单：'. $ip. '，移出失败！');
                }
            }else{
                showmessage('黑名单：'. $ip. '，不存在');
            }
        }else{
            $blacklistInfo = db('blacklist')->where([
                'ip' => $ip
            ])->find();
            if(!$blacklistInfo){
                $result = db('blacklist')->insert([
                    'ip' => $ip,
                    'create_date' => time(),
                ]);
                if($result){
                    showmessage('黑名单：'. $ip. '，添加成功！', 200);
                }else{
                    showmessage('黑名单：'. $ip. '，添加失败！');
                }
            }else{
                showmessage('黑名单：'. $ip. '，已存在！');
            }
        }
    }

    public function money_change()
    {
        $where = [];
        //处理订单创建时间范围
        $create_time = input('create_time', '');
        $arr_create_time = explode('|', $create_time);
        if($arr_create_time && count($arr_create_time) == 2){
            if(count($arr_create_time) == 2){
                $where['create_date'] = array('between', array(strtotime($arr_create_time[0]), strtotime($arr_create_time[1])));
            }
        }

        //处理平台订单号或下游订单号或商户号
        $keyword = input('keyword', '');
        if($keyword){
            if(strpos($keyword, config('platform_name_en')) !== false){
                $where['platform_order_id'] = $keyword;
            }else if(preg_match('/^[A-Z][0-1][0-9]\d{10}$/', $keyword)){
                $where['member_id'] = $keyword;
            }else{
                $where['submit_order_id'] = $keyword;
            }
        }

        //处理通道
        $channel_id = input('channel_id', '');
        if($channel_id){
            $where['channel_id'] = $channel_id;
        }

        //处理接口
        $type_name = input('type_name', '');
        if($type_name){
            $where['type_name'] = $type_name;
        }

        //资金变动类型
        $change_type = input('change_type', '');
        if($change_type){
            $where['change_type'] = $change_type;
        }

        //每页显示10条数据
        $perCount = 10;
        $list = Moneychange::where($where)->order('id desc')->paginate($perCount, false, ['query'=>input()]);
        //获得当前表的全部条数
        $count = OrderModel::count();

        //获取全部通道数据
        $channelData = db('channel')->field('id, name_cn')->select();

        //回显HTML数据
        $showData = [
            'create_time' => $create_time,
            'keyword' => $keyword,
            'channel_id' => $channel_id,
            'type_name' => $type_name,
            'change_type' => $change_type,
        ];
        $change_type = config('change_type');
        return view('money_change', compact('list', 'showData', 'moneyChangeData', 'channelData', 'change_type', 'count'));
    }

    public function freeze()
    {
        $platform_order_id = input('platform_order_id', '');
        $is_freeze = input('is_freeze', 2);
        $msg = ($is_freeze == 2 ? '冻结' : '解冻');
        $msg2 = ($is_freeze == 2 ? '减少' : '增加');
        // 启动事务
        Db::startTrans();
        try{
            $orderInfo = db('order')->lock(true)->where(['platform_order_id' => $platform_order_id])->find();
            if($orderInfo && $orderInfo['is_freeze'] == 2 && $is_freeze == 2)  showmessage('订单无法再次被'. $msg);
            if($orderInfo && $orderInfo['is_freeze'] == 1 && $is_freeze == 1)  showmessage('订单无法再次被'. $msg);
            //冻结订单
            $result = db('order')->where(['platform_order_id' => $platform_order_id])->setField('is_freeze', $is_freeze);
            if(!$result) {
                Db::rollback();
                showmessage($msg. '订单失败', 1);
            }
            //处理冻结订单的资金 减少资金
            $memberInfo = db('member')->lock(true)->where(['member_id' => $orderInfo['member_id']])->find();

            if($is_freeze == 2){
                //减少商户金额
                $after_money = $memberInfo['balance'] - $orderInfo['income_amount'];
                //增加商户冻结余额
                $blockedbalance = $memberInfo['blockedbalance'] + $orderInfo['income_amount'];
                $change_type = 21;
            }else{
                //增加商户金额
                $after_money = $memberInfo['balance'] + $orderInfo['income_amount'];
                //减少商户冻结余额
                $blockedbalance = $memberInfo['blockedbalance'] - $orderInfo['income_amount'];
                $change_type = 12;
            }
            if($after_money < 0 || $blockedbalance < 0) showmessage('商户余额或冻结金额不足！');
            $data = [
                'member_id' => $memberInfo['member_id'],
                'before_money' => $memberInfo['balance'],
                'change_money' => $orderInfo['income_amount'],
                'after_money' => $after_money,
                'change_type' => $change_type, // 21：减少：订单冻结  12：增加：订单解冻
                'platform_order_id' => $orderInfo['platform_order_id'],
                'submit_order_id' => $orderInfo['submit_order_id'],
                'channel_id' => $orderInfo['channel_id'],
                'channel_name' => $orderInfo['channel_name'],
                'type_name' => $orderInfo['type_name'],
                'create_date' => time(),
            ];
            $result = db('money_change')->insert($data);
            if(!$result){
                Db::rollback() ;
                showmessage('资金变动表插入失败', 1);
            }
            $data = [
                'balance' => $after_money,
                'blockedbalance' => $blockedbalance,
            ];
            $result = db('member')->where(['member_id' => $memberInfo['member_id']])->setField($data);
            if(!$result){
                Db::rollback();
                showmessage('商户金额'. $msg2. '失败', 1);
            }
            //判断当前商户是否有上级代理
            if($memberInfo['pid']){
                $memberInfoPid = db('member')->lock(true)->find($memberInfo['pid']);
                if(!$memberInfoPid)  showmessage('商户上级代理账户不存在！');
                //代理手续费必须大于0才可执行
                if($orderInfo['agent_poundage'] > 0){
                    if($is_freeze == 2){
                        //减少商户金额
                        $after_money = $memberInfoPid['balance'] - $orderInfo['agent_poundage'];
                        //增加商户冻结余额
                        $blockedbalance = $memberInfoPid['blockedbalance'] + $orderInfo['agent_poundage'];
                        $change_type = 21;
                    }else{
                        //增加商户金额
                        $after_money = $memberInfoPid['balance'] + $orderInfo['agent_poundage'];
                        //减少商户冻结余额
                        $blockedbalance = $memberInfoPid['blockedbalance'] - $orderInfo['agent_poundage'];
                        $change_type = 12;
                    }
                    // if($after_money < 0 || $blockedbalance < 0) showmessage('商户余额或冻结金额不足！');

                    if($after_money && $blockedbalance){
                        $data = [
                            'member_id' => $memberInfoPid['member_id'],
                            'before_money' => $memberInfoPid['balance'],
                            'change_money' => $orderInfo['agent_poundage'],
                            'after_money' => $after_money,
                            'change_type' => $change_type, // 21：减少：订单冻结  12：增加：订单解冻
                            'platform_order_id' => $orderInfo['platform_order_id'],
                            'submit_order_id' => $orderInfo['submit_order_id'],
                            'channel_id' => $orderInfo['channel_id'],
                            'channel_name' => $orderInfo['channel_name'],
                            'type_name' => $orderInfo['type_name'],
                            'create_date' => time(),
                        ];
                        $result = db('money_change')->insert($data);
                        if(!$result){
                            Db::rollback() ;
                            showmessage('商户上级代理资金变动表插入失败', 1);
                        }
                        $data = [
                            'balance' => $after_money,
                            'blockedbalance' => $blockedbalance,
                        ];
                        $result = db('member')->where(['member_id' => $memberInfoPid['member_id']])->setField($data);
                        if(!$result){
                            Db::rollback();
                            showmessage('上级代理商户金额'. $msg2. '失败', 1);
                        }
                    }
                }
            }
            //提交数据
            Db::commit();
            log_operation('修改'. '订单状态 [ '. '<font color="red">'.$msg.'</font>'. ' ]，订单号：'. $platform_order_id. '，金额：' .$orderInfo['amount']);
            showmessage($msg. '成功！', 1);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            showmessage($e->getMessage());
        }
    }

    public function set_order_paid()
    {
        $codeRecode = cache(USER_NAME. '_sms');
        $info = db('order')->where([
            'platform_order_id' => input('platform_order_id', ''),
        ])->find();
        if(request()->isGet()){
            if(ROLE_ID == 15){
                if($codeRecode && isset($codeRecode['success']) && $codeRecode['success'] === false){
                    $info['show_sms'] = false;
                }else{
                    $info['show_sms'] = true;
                }
            }else{
                $info['show_sms'] = false;
            }
            return view('set_order_paid', compact('info'));
        }
        $platform_order_id = input('platform_order_id', '');
        if(ROLE_ID == 15){
            if(config('PhoneNumbers')){
                $code = input('code', '');
                if(!$codeRecode || !isset($codeRecode['success'])){
                    showmessage('请点击发送短信！');
                }
                if($codeRecode['success'] && isset($codeRecode['code']) && $code != $codeRecode['code']){
                    showmessage('验证码错误！');
                }
            }
        }else{
            if($info['amount'] >= 100) {
                showmessage('');
            }
        }
        self::setOrderPaid($platform_order_id, 1); //1 代表内部强制回调
    }

    public function getExcel(){
        $data = input();
        $condition = [];

        if($data['member_id']){
            $condition['a.member_id'] = $data['member_id'];
        }
        $condition['a.pay_status'] = ['in', [2, 3, 4]];

        $data = db('order')
            ->alias('a')
            ->join('member b', 'a.member_id=b.member_id', 'left')
            ->field('a.platform_order_id, a.submit_order_id, a.member_id, b.nickname, a.amount, a.create_date, a.success_date, a.type_name, a.channel_name, a.pay_status')
            ->where($condition)
            ->order('a.id desc')
            ->select();
        foreach ($data AS $key => $value){
            $data[$key]['create_date'] = date('Y-m-d H:i:s',$value['create_date']);
            $data[$key]['success_date'] = date('Y-m-d H:i:s',$value['success_date']);
            $data[$key]['type_name'] = $value['type_name'] == 'alipay' ? '支付宝' : '微信';
            $data[$key]['pay_status'] = $value['pay_status'] == 2 ? '成功已返' : '成功未返';
        }
        $title = ['平台订单号', '下游订单号', '商户号', '商户昵称',  '订单金额', '创建时间', '成功时间', '通道类型', '通道名称', '支付状态'];
        exportExcel($title, $data, date('Ymd'). '-'. config('platform_name_en'). '-成功订单记录报表');
    }

}