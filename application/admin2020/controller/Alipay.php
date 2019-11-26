<?php
namespace app\admin2020\controller;
use app\admin2020\model\Member;
use app\admin2020\model\Role;
use app\admin2020\model\Alipayaccount;
use app\admin2020\model\Channel;
use app\api\controller\Common as CommonApi;

class Alipay extends Common
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $where = [];
        $appid = input('appid', '');
        if($appid){
            $where['appid'] = $appid;
        }
        $name = input('name', '');
        if($name){
            $where['name'] = $name;
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
        $hongbaochannelInfo = Channel::where(['name_en' => 'Alipay_hongbao'])->find();
        //取出支付宝转网银的数据库通道id
        $shoukuanChannelInfo = Channel::where(['name_en' => 'Alipay_shoukuan'])->find();
        //每页显示20条数据
        $perCount = 20;
        $list = Alipayaccount::where($where)->order('status asc, id desc')->paginate($perCount, false, ['query'=>input()]);
        //取出对应订单
        $list2 = $list->toArray()['data'];
        $keyField = 'account';
        //修改这里的$v[$key]
        $keyFieldArr = array_column($list2,  $keyField);
        $orderData = db('order')->where(['relate_key' => ['in', $keyFieldArr]])->select();
        // dump($orderData);die;
        foreach ($list2 as $k => $v) {
            if(!isset($list2[$k]['countTotal'])) $list2[$k]['countTotal'] = [];
            if(!isset($list2[$k]['countSuccess'])) $list2[$k]['countSuccess'] = [];
            if(!isset($list2[$k]['moneySuccess'])) $list2[$k]['moneySuccess'] = 0;
            foreach ($orderData as $k2 => $v2) {
                if($v[$keyField] == $v2['relate_key']){
                    $list2[$k]['countTotal'][] = $v2['platform_order_id'];
                    if($v2['pay_status'] == 2 || $v2['pay_status'] == 3){
                        $list2[$k]['countSuccess'][] = $v2['platform_order_id'];
                        $list2[$k]['moneySuccess'] += $v2['actual_amount'];
                    }
                }
            }
        }
        //获得当前表的全部条数
        $count = Alipayaccount::count();
        $showData = array(
            'appid' => $appid,
            'name' => $name,
            'account' => $account,
            'status' => $status,
        );
        return view('index', compact('list', 'list2', 'showData', 'hongbaochannelInfo', 'shoukuanChannelInfo','count'));
    }

    public function del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $ret = Alipayaccount::del($id);
        if(!$ret) showmessage('删除失败');
        log_operation('删除支付宝账户-'.$id);
        showmessage('删除成功', 1);
    }

    public function set()
    {
        $id = input('id', 0);
        if(request()->isGet()){
            $info = Alipayaccount::get($id);
            $roleData = Role::all();
            return view('set', compact('info', 'roleData'));
        }
        $result = Alipayaccount::setAlipayAccout();
        $desc = $id ? '修改' : '添加';
        if(!$result) showmessage($desc. '支付宝账户失败');
        log_operation($desc.'支付宝账户-'. ($id ? $id : $result));
        showmessage($desc. '支付宝账户成功', 1);

    }

    public function status()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $ret = Alipayaccount::changeStatus($id);
        log_operation('切换支付宝账户状态');
        if(!$ret) showmessage('切换失败');
        showmessage($ret == 1 ? '启用成功' : '禁用成功' , 1);
    }

}
