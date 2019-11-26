<?php
namespace app\admin2020\controller;
use app\admin2020\model\Member;
use app\admin2020\model\Role;
use app\admin2020\model\Wechataccount;
use app\admin2020\model\Channel;
use app\api\controller\Common as CommonApi;

class Wechat extends Common
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $where = [];
        $name = input('name', '');
        if($name){
            $where['name'] = $name == '-' ? '' : base64_encode($name);
        }
        $pid = input('pid', '-');
        $where['pid'] = $pid == '-' ? ['gt', 0] : $pid;
        $account = input('account', '');
        if($account){
            $where['account'] = $account;
        }
        $status = input('status', '');
        if($status){
            $where['status'] = $status;
        }
        $is_unusual = input('is_unusual', '');
        if($is_unusual){
            $where['is_unusual'] = $is_unusual;
        }
        $orderBy = input('orderBy', 'used_date');
        //取出支付宝转网银的数据库通道id
        $dianyuanChannelInfo = Channel::where(['name_en' => 'Wechat_dianyuantong'])->find();
        //每页显示20条数据
        $perCount = 999999;
        $list = Wechataccount::where($where)->paginate($perCount, false, ['query'=>input()]);
        //获得当前表的全部条数
        $wechatData = Wechataccount::field('status, is_unusual')->where($where)->select();
        $count = count($wechatData);
        $countOpen = 0;
        $countClose = 0;
        $isUnusualCount = 0;
        foreach ($wechatData as $k => $v) {
            if($v['status'] == 1){
                $countOpen++;
            }else{
                $countClose++;
            }
            if($v['is_unusual'] == 1) {
                $isUnusualCount++;
            }
        }
        //取出对应订单
        $list2 = $list->toArray()['data'];
        $keyField = 'name';
        $where = [];
        //处理订单创建时间范围
        $create_time = input('create_time', date('Y-m-d 00:00:00').' | '.date('Y-m-d 23:59:59'));
        $arr_create_time = explode('|', $create_time);
        if($arr_create_time && count($arr_create_time) == 2){
            if(count($arr_create_time) == 2){
                $where['create_date'] = array('between', array(strtotime($arr_create_time[0]), strtotime($arr_create_time[1])));
            }
        }
        //修改这里的$v[$key]
        $keyFieldArr = array_column($list2,  $keyField);
        $where['relate_key'] = ['in', $keyFieldArr];
        $where['channel_id'] = $dianyuanChannelInfo['id'];
        $where['actual_amount'] = ['egt', '1'];
        $orderData = db('order')->field('platform_order_id, income_amount, amount, relate_key, channel_id, pay_status')->where($where)->order('id desc')->select();
        $adminData = db('admin')->select();
        $wechatAccountData = db('wechat_account')->select();
        foreach ($list2 as $k => $v) {
            //更新异常丢失的收款二维码数据
            if(!file_exists('.'. $v['img_path'])){
                $img_path = qrcode($v['url']);
                $result = Wechataccount::where([
                    'id' => $v['id'],
                ])->setField([
                    'img_path' => $img_path,
                ]);
                if($result) $list2[$k]['img_path'] = $img_path;
            }
            if(!isset($list2[$k]['countTotal'])) $list2[$k]['countTotal'] = 0;
            if(!isset($list2[$k]['countSuccess'])) $list2[$k]['countSuccess'] = 0;
            if(!isset($list2[$k]['curTotal'])) $list2[$k]['curTotal'] = 0;
            if(!isset($list2[$k]['curSuccess'])) $list2[$k]['curSuccess'] = 0;
            if(!isset($list2[$k]['moneySuccess'])) $list2[$k]['moneySuccess'] = 0;
            if(!isset($list2[$k]['moneyIncome'])) $list2[$k]['moneyIncome'] = 0;
            if(!isset($list2[$k]['dianyuan'])) $list2[$k]['dianyuan'] = '';
            if(!isset($list2[$k]['dianyuanAccount'])) $list2[$k]['dianyuanAccount'] = '';
            if(!isset($list2[$k]['dianzhang'])) $list2[$k]['dianzhang'] = [];
            foreach ($wechatAccountData as $k2 => $v2) {
                if($v['pid'] == $v2['id']){
                    $list2[$k]['dianyuan'] = $v2['name'];
                    $list2[$k]['dianyuanAccount'] = $v2['account'];
                }else if($v['id'] == $v2['pid']){
                    $list2[$k]['dianzhang'][] = $v2['name'];
                }

            }
        }
        $orderSuccessMoney = db('order')->where([
            'pay_status' => ['in', [2, 3 ,4]],
            'channel_id' => 301,
            'amount' => ['egt', 1],
            'success_date' => array('between', array(strtotime($arr_create_time[0]), strtotime($arr_create_time[1]))),
        ])->sum('amount');
        foreach ($list2 as $k => $v) {
            foreach ($orderData as $k2 => $v2) {
                if($v[$keyField] == $v2['relate_key']){
                    $list2[$k]['countTotal']++;
                    $list2[$k]['curTotal']++;
                    if(in_array($v2['pay_status'], [2, 3, 4])){
                        if($list2[$k]['curTotal'] <= 3){
                            $list2[$k]['curSuccess']++;
                        }
                        $list2[$k]['countSuccess']++;
                        $list2[$k]['moneySuccess'] += (float)$v2['amount'];
                        $list2[$k]['moneyIncome'] += (float)$v2['income_amount'];
                    }
                }
            }
        }
        foreach ($list2 as $k => $v) {
           $list2[$k]['successRate'] = $v['countTotal'] ? $v['countSuccess'] / $v['countTotal'] : 0;
           $curTotal = $v['curTotal'] >= 3 ? 3 : $v['curTotal'];
           $list2[$k]['curSuccessRate'] = $curTotal ? $v['curSuccess'] / $curTotal : 0;
        }
        $noNameCount = db('wechat_account')->where([
            'name' => '',
            'pid' => array('neq', '0'),
        ])->count();
        $dianyuanNameCount = db('wechat_account')->where([
            'pid' => 0,
        ])->count();
        $showData = array(
            'name' => $name,
            'pid' => $pid,
            'account' => $account,
            'status' => $status,
            'is_unusual' => $is_unusual,
            'create_time' => $create_time,
            'noNameCount' => $noNameCount,
            'dianyuanNameCount' => $dianyuanNameCount,
            'orderBy' => $orderBy,
            'orderSuccessMoney' => $orderSuccessMoney,
            'isUnusualCount' => $isUnusualCount,
            'count' => $count,
            'countOpen' => $countOpen,
            'countClose' => $countClose,
        );
        $dianyuanName = db('wechat_account')->where(['pid' => 0])->select();

        //按照 orderBy 降序
        $orderByInfo = array_column($list2, $orderBy);
        array_multisort($orderByInfo, SORT_DESC, $list2);
        return view('index', compact('list', 'list2', 'showData', 'dianyuanChannelInfo', 'dianyuanName'));
    }

    public function index2()
    {
        $where = [];
        $name = input('name', '');
        if($name){
            $info = Wechataccount::get(['name' => $name]);
            if($info) $where['pid'] = $info['id'];
        }else{
            $pid = input('pid', '-');
            if($pid != '-'){
                if(!$pid){
                    $where['pid'] = $pid;
                }else{
                    $where['pid'] = array('neq', '0');
                }
            }
        }
        $account = input('account', '');
        if($account){
            $where['account'] = $account;
        }
        $status = input('status', '');
        if($status){
            $where['status'] = $status;
        }
        //取出支付宝转网银的数据库通道id
        $dianyuanChannelInfo = Channel::where(['name_en' => 'Wechat_dianyuantong'])->find();
        //每页显示20条数据
        $perCount = 20;
        $list = Wechataccount::where($where)->order('status asc, id desc')->paginate($perCount, false, ['query'=>input()]);
        //取出对应订单
        $list2 = $list->toArray()['data'];
        $keyField = 'name';
        //修改这里的$v[$key]
        $keyFieldArr = array_column($list2,  $keyField);
        $orderData = db('order')->where([
            'relate_key' => ['in', $keyFieldArr],
            'channel_id' => $dianyuanChannelInfo['id'],
        ])->order('id desc')->select();
        $adminData = db('admin')->select();
        $wechatAccountData = db('wechat_account')->select();
        // dump($orderData);die;
        foreach ($list2 as $k => $v) {
            if(!isset($list2[$k]['countTotal'])) $list2[$k]['countTotal'] = [];
            if(!isset($list2[$k]['countSuccess'])) $list2[$k]['countSuccess'] = [];
            if(!isset($list2[$k]['curSuccess'])) $list2[$k]['curSuccess'] = [];
            if(!isset($list2[$k]['curTotal'])) $list2[$k]['curTotal'] = [];
            if(!isset($list2[$k]['moneySuccess'])) $list2[$k]['moneySuccess'] = 0;
            if(!isset($list2[$k]['moneyIncome'])) $list2[$k]['moneyIncome'] = 0;
            if(!isset($list2[$k]['dianyuan'])) $list2[$k]['dianyuan'] = '';
            if(!isset($list2[$k]['dianzhang'])) $list2[$k]['dianzhang'] = [];
            foreach ($wechatAccountData as $k2 => $v2) {
                if(!isset($list2[$k]['dianyuan'])) $list2[$k]['dianyuan'] = '-';
                if($v['pid'] == $v2['id']){
                    $list2[$k]['dianyuan'] = $v2['name'];
                }else if($v['id'] == $v2['pid']){
                    $list2[$k]['dianzhang'][] = $v2['name'];
                }

            }
        }
        foreach ($list2 as $k => $v) {
            foreach ($orderData as $k2 => $v2) {
                if($v[$keyField] == $v2['relate_key']){
                    $list2[$k]['countTotal'][] = $v2['platform_order_id'];
                    $list2[$k]['curTotal'][] = $v2['platform_order_id'];
                    if($v2['pay_status'] == 2 || $v2['pay_status'] == 3){
                        if(count($list2[$k]['curTotal']) <= 5){
                            $list2[$k]['curSuccess'][] = $v2['platform_order_id'];
                        }
                        $list2[$k]['countSuccess'][] = $v2['platform_order_id'];
                        $list2[$k]['moneySuccess'] += $v2['actual_amount'];
                        $list2[$k]['moneyIncome'] += $v2['income_amount'];
                    }
                }

            }
        }
        //获得当前表的全部条数
        $count = Wechataccount::count();
        $showData = array(
            'name' => $name,
            'account' => $account,
            'status' => $status,
            'pid' => isset($pid) ? $pid : '-',
        );
        $province_city = static::$province_city;
        return view('index', compact('list', 'list2', 'showData', 'dianyuanChannelInfo', 'count', 'province_city'));
    }

    public function uploadx()
    {
        $name = input('name', '');
        $pid = input('pid', 0);
        return view('upload', compact('name', 'pid'));
    }

    // public function upload()
    // {
    //     $file = request()->file('file');
    //     if($file){
    //         header("Content-type:text/html;charset=utf-8");
    //         $uploads = ROOT_PATH .'public/static/runtime/uploads';
    //         $fileNameXXX2 = $file->getInfo()['name'];
    //         if($fileNameXXX2 && strpos($fileNameXXX2, '.') !== false){
    //             $fileNameXXXArr = explode('.', $fileNameXXX2);
    //             $num = count($fileNameXXXArr) -1;
    //             unset($fileNameXXXArr[$num]);
    //             $fileNameXXX = implode('.', $fileNameXXXArr);
    //         }else{
    //             $fileNameXXX = $fileNameXXX2;
    //         }
    //         $fileName = date('YmdHis').'_'.uniqid().'.jpg';
    //         $info = $file->move($uploads, $fileName);
    //         if($info){
    //             $image = new \ZBarCodeImage($uploads. '/'. $fileName);
    //             $scanner = new \ZBarCodeScanner();
    //             $barcode = $scanner->scan($image);
    //                         // $res = ['code' => 1, 'msg' => $uploads. '/'. $fileName];
    //                         // echo json_encode($res, 320);die;
    //             $url = '';
    //             if (!empty($barcode)) {
    //                 foreach ($barcode as $code) {
    //                     $url = $code['data'];
    //                 }
    //             }
    //             if(!$url){
    //                 include_once ROOT_PATH. '/extend/parseQrcode/lib/QrReader.php';
    //                 $qrcode = new \QrReader($uploads. '/'. $fileName);  //图片路径
    //                 $url = $qrcode->text(); //返回识别后的文本
    //             }
    //             $pid = input('pid', 0);
    //             $name = input('name', '');
    //             //必须解析出链接才加入数据库
    //             if($url){
    //                 if($pid){
    //                     $result = db('wechat_account')->where(['url' => $url])->find();
    //                     if($result){
    //                         $res = ['code' => 1, 'msg' => '文件：[ '.$fileNameXXX2.' ] 二维码链接已存在，无需上传！'];
    //                         echo json_encode($res, 320);die;
    //                     }
    //                     $result = db('wechat_account')->where(['account' => $fileNameXXX])->find();
    //                     if($result){
    //                         $res = ['code' => 1, 'msg' => '文件：[ '.$fileNameXXX2.' ] 微信账户已存在！'];
    //                         echo json_encode($res, 320);die;
    //                     }
    //                     // require_once ROOT_PATH.'/extend/ocr/AipOcr.php';
    //                     // // 初始化
    //                     // $aipOcr = new \AipOcr('16433011', 'n2OOElyO0QrKQnkS9iXGj77Q', 'FQcGAXjgNi9OAKRlcKS6YvaKFaLdKQoF');
    //                     // $arr = $aipOcr->general(file_get_contents($uploads.'/'.$fileName));
    //                     // $str = '';
    //                     // $count = count($arr['words_result']);
    //                     // foreach ($arr['words_result'] as $k => $v) {
    //                     //     if($k == $count - 2){
    //                     //         $str .= $v['words'];
    //                     //     }
    //                     // }
    //                     // // $res = ['code' => 1, 'msg' => $str];
    //                     // // echo json_encode($res, 320);die;
    //                     // $str = str_replace('口', '', $str);
    //                     // $str = str_replace(' ', '', $str);
    //                     // $str = remove_emoji($str);
    //                     // $leftLastIndex = strrpos($str, '(');
    //                     // $name = substr($str, 0, $leftLastIndex);
    //                     $data = [
    //                         'url' => $url,
    //                         'img_path' => qrcode($url),
    //                         'account' => uniqid(),
    //                         'name' => '',
    //                         'status' => '2',
    //                         'pid' => $pid,
    //                         'create_date' => time(),
    //                     ];
    //                     $result = db('wechat_account')->insert($data);
    //                     if(!$result){
    //                         $res = ['code' => 1, 'msg' => '插入数据失败！'];
    //                         echo json_encode($res, 320);die;
    //                     }else{
    //                         log_operation('上传二维码图片');
    //                         $res = ['code' => 0, 'msg' => '上传成功'];
    //                         echo json_encode($res, 320);die;
    //                     }
    //                 }else{
    //                     $data = [
    //                         'url' => $url,
    //                         'img_path' => qrcode($url),
    //                     ];
    //                     $result = db('wechat_account')->where(['name' => $name])->setField($data);
    //                     if(!$result){
    //                         $res = ['code' => 1, 'msg' => '更新数据失败！'];
    //                         echo json_encode($res, 320);die;
    //                     }else{
    //                         log_operation('上传二维码图片');
    //                         $res = ['code' => 0, 'msg' => '上传成功'];
    //                         echo json_encode($res, 320);die;
    //                     }
    //                 }
    //             }else{
    //                 $res = ['code' => 1, 'msg' => '微信：[ '.$name.' ] 解析url失败！请重新上传收款二维码图片！'];
    //                 echo json_encode($res, 320);die;
    //             }
    //         }else{
    //             // 上传失败获取错误信息
    //             echo $file->getError();die;
    //         }
    //     }
    // }

    public function del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $info = db('wechat_account')->find($id);
        if(!$info) showmessage('非法');
        $orderInfo = db('order')->where(['relate_key' => $info['name']])->find();
        if($orderInfo) showmessage('存在关联数据【订单号】，无法删除！');
        $wechatAccountInfo = db('wechat_account')->where(['pid' => $id])->find();
        if($wechatAccountInfo) showmessage('存在关联数据的【店长微信号】，无法删除！');
        $ret = Wechataccount::del($id);
        if(!$ret) showmessage('删除失败');
        log_operation('删除微信账户-'. $info['account']);
        showmessage('删除成功', 1);
    }

    public function status()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $info = Wechataccount::get($id);
        $ret = Wechataccount::changeStatus($id);
        if(!$ret) showmessage('切换失败');
        $msg = $ret == 1 ? '启用成功' : '禁用成功';
        log_operation('切换微信商户状态：'. $info['account']. '，'.$msg);
        showmessage($msg , 1);
    }

    public function set()
    {
        $id = input('id', 0);
        $info = Wechataccount::get($id);
        if(request()->isGet()){
            $pid = input('pid', '0');
            $roleData = Role::all();
            $wechatNameData = db('wechat_name')->field('name')->order('id desc')->select();
            $wechatNameArr = array_column($wechatNameData, 'name');
            $wechatAccountData = db('wechat_account')->field('name')->order('id desc')->select();
            $wechatAccountArr = array_column($wechatAccountData, 'name');
            $wechatNameData = array_diff($wechatNameArr, $wechatAccountArr);
            // $province_city = static::$province_city;
            return view('set', compact('info', 'roleData', 'pid', 'province_city', 'wechatNameData'));
        }
        $result = Wechataccount::setWechatAccout();
        $desc = $id ? '修改' : '添加';
        if(!$result) showmessage($desc. '微信账户失败');
        log_operation($desc.'微信账户-'. $info['account']);
        showmessage($desc. '微信账户成功', 1);

    }

    public function notify_name()
    {
        $wechatNameData = db('wechat_name')->field('name')->order('id desc')->select();
        $wechatNameArr = array_column($wechatNameData, 'name');
        $wechatAccountData = db('wechat_account')->field('name')->order('id desc')->select();
        $wechatAccountArr = array_column($wechatAccountData, 'name');
        $wechatNameData = array_diff($wechatNameArr, $wechatAccountArr);
        return view('notify_name', compact('wechatNameData'));
    }

    public function name_del()
    {
        $name = input('name', 0);
        if(!$name) showmessage('非法参数');
        $ret = db('wechat_name')->where(['name' => base64_encode($name)])->delete();
        if(!$ret) showmessage('删除失败');
        log_operation('删除微信回调昵称-'. $name);
        showmessage('删除微信成功', 1);
    }

    public function set_config()
    {
        $crontab_close_wechat_account_order_fail_acount = input('crontab_close_wechat_account_order_fail_acount', 2);
        $crontab_close_wechat_account_day_max_money = input('crontab_close_wechat_account_day_max_money', 2);
        $order_fail_acount = input('order_fail_acount', 3);
        $delay_dispatch_second = input('delay_dispatch_second', 3);
        $info = db('config')->find(1);
        if(request()->isGet()){
            return view('set_config', compact('info'));
        }
        $result = db('config')->where(['id' => 1])->setField([
            'crontab_close_wechat_account_order_fail_acount' => $crontab_close_wechat_account_order_fail_acount,
            'crontab_close_wechat_account_day_max_money' => $crontab_close_wechat_account_day_max_money,
            'order_fail_acount' => $order_fail_acount,
            'delay_dispatch_second' => $delay_dispatch_second,
        ]);
        if(!$result) showmessage('修改配置-失败');
        $msg = $crontab_close_wechat_account_order_fail_acount == 1 ? '定时关闭异常微信账户启用成功' : '定时关闭异常微信账户禁用成功';
        $msg2 = $crontab_close_wechat_account_day_max_money == 1 ? '定时关闭限额微信账户启用成功' : '定时关闭限额微信账户禁用成功';
        $msg .= '，'. $msg2;
        $msg .= '，'. '连续失败次数：'.$order_fail_acount;
        $msg .= '，'. '延时派单分钟：'.$delay_dispatch_second;
        log_operation('修改微信配置-'. $msg);
        showmessage('修改微信配置-成功', 1);
    }

    public function mutil_del()
    {
        $ids = input('id/a', 0);
        // if(!$id) showmessage('非法参数');
        $ret = db('wechat_account')->where([
            'id' => ['in', $ids],
        ])->delete();
        if(!$ret) showmessage('批量删除失败');
        $msg = '批量删除成功' ;
        log_operation('批量删除微信商户：id（'. implode(' | ', $ids). '），'.$msg);
        showmessage($msg , 1);
    }

    public function mutil_status()
    {
        $ids = input('id/a', 0);
        $status = input('status', 2);
        // if(!$id) showmessage('非法参数');
        $ret = db('wechat_account')->where([
            'id' => ['in', $ids],
        ])->setField(['status' => $status]);
        if(!$ret) showmessage('批量切换失败');
        $msg = $status == 1 ? '批量启用成功' : '批量禁用成功';
        log_operation('批量切换微信商户状态：id（'. implode(' | ', $ids). '），'.$msg);
        showmessage($msg , 1);
    }

}
