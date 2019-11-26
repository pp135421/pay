<?php
namespace app\admin2020\controller;
use app\admin2020\model\Member;
use app\admin2020\model\Role;
use app\admin2020\model\Dingding as DingdingModel;
use app\admin2020\model\Channel;
use app\api\controller\Common as CommonApi;

class Dingding extends Common
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
            $where['name'] = $name;
        }
        $signkey = input('signkey', '');
        if($signkey){
            $where['signkey'] = $signkey;
        }
        $nat_domin = input('nat_domin', '');
        if($nat_domin){
            $where['nat_domin'] = $nat_domin;
        }
        $status = input('status', '');
        if($status){
            $where['status'] = $status;
        }
        //取出钉钉红包的数据库通道id
        $channelInfo = Channel::where(['name_en' => 'Alipay_dingding'])->find();
        //每页显示20条数据
        $perCount = 20;
        $list = DingdingModel::where($where)->order('status asc, id desc')->paginate($perCount, false, ['query'=>input()]);
        //取出对应订单
        $list2 = $list->toArray()['data'];
        $keyField = 'name';
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
        // dump($dingdingData);die;
        //获得当前表的全部条数
        $count = DingdingModel::count();
        $showData = array(
            'name' => $name,
            'signkey' => $signkey,
            'status' => $status,
        );
        return view('index', compact('list', 'list2', 'showData', 'dingdingData', 'channelInfo', 'count'));
    }

    public function del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $ret = DingdingModel::del($id);
        if(!$ret) showmessage('删除失败');
        log_operation('删除关联账户-'.$id);
        showmessage('删除成功', 1);
    }

    public function set()
    {
        $id = input('id', 0);
        if(request()->isGet()){
            $info = DingdingModel::get($id);
            $roleData = Role::all();
            return view('set', compact('info', 'roleData'));
        }
        $result = DingdingModel::setDingding();
        $desc = $id ? '修改' : '添加';
        if(!$result) showmessage($desc. '关联账户失败');
        log_operation($desc.'关联账户-'. ($id ? $id : $result));
        showmessage($desc. '关联账户成功', 1);

    }

    public function status()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $ret = DingdingModel::changeStatus($id);
        if(!$ret) showmessage('切换失败');
        showmessage($ret == 1 ? '启用成功' : '禁用成功' , 1);
    }

}
