<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 15:53
 */

namespace app\admin2020\controller;
use app\admin2020\model\Channel as ChannelModel;
use app\admin2020\model\Member ;
use app\api\controller\Common as CommonApi;


class Channel extends Common
{
    public function index()
    {
        $where = [];
        $status = input('status', '');
        if($status){
            $where['status'] = $status;
        }
        $type_name = input('type_name', '');
        if($type_name){
            $where['type_name'] = $type_name;
        }
        $is_inner = input('is_inner', '');
        if($is_inner){
            $where['is_inner'] = $is_inner;
        }
        //每页显示30条数据
        $perCount = 30;
        $list = ChannelModel::where($where)->order('id desc')->paginate($perCount, false, ['query'=>input()]);
        //取出对应订单
        $list2 = $list->toArray()['data'];
        //获得当前表的全部条数
        $count = ChannelModel::count();
        $orderData = db('order')->field('pay_status,channel_id,amount')->select();
        foreach ($list2 as $k => $v) {
            if(!isset($list2[$k]['passway_amount_all'])) $list2[$k]['passway_amount_all'] = 0;
            foreach ($orderData as $k2 => $v2) {
                if($v['id'] == $v2['channel_id']){
                    if($v2['pay_status'] == 2 || $v2['pay_status'] == 3  || $v2['pay_status'] == 4){
                        $list2[$k]['passway_amount_all'] += $v2['amount'];
                    }
                }
            }
        }

        $showData = [
            'status' => $status,
            'type_name' => $type_name,
            'is_inner' => $is_inner,
        ];
        return view('index', compact('list', 'list2', 'showData', 'count'));
    }

    public function test()
    {
        $member_id = config('inner_member_id');
        if(!$member_id) showmessage('内部商户号未设置！');
        $name_en = input('name_en', 0);
        if(!$name_en) showmessage('name_en不能为空！');
        $relate_key = input('relate_key', 0);
        $channelInfo = ChannelModel::get(['name_en' => $name_en]);
        if(request()->isGet()){
            return view('test', compact('channelInfo', 'relate_key'));
        }
        $amount = input('amount/f', 0);
        if(!$amount || $amount <= 0 || $amount > 1 && $amount != intval($amount)) showmessage('请输入正整数！');
        $memberInfo = Member::get(['member_id' => $member_id]);
        //收集订单数据
        $testData['member_id'] = config('inner_member_id');
        $testData['amount'] = $amount ;
        $testData['notify_url'] = $_SERVER['REQUEST_SCHEME']. '://'. config('admin_domin').'/receive_notify';
        $testData['channel_id'] = $channelInfo['id'];;
        $testData['channel_name'] = $channelInfo['name_cn'];
        $testData['type_name'] = $channelInfo['type_name'];
        $testData['submit_order_id'] = '（'. USER_NAME. '）'. date('YmdHis').rand(100000,999999);
        //加入数据并生成订单并找到对应通道控制器处理订单
        CommonApi::orderToController($testData, $memberInfo, $channelInfo);
    }

    public function create_img()
    {
        $get_url = input('get_url', '');
        if(!$get_url) showmessage('url不能为空');
        $img = qrcode($get_url);
        showmessage($img, 1);
    }

    public function set()
    {
        $id = input('id', 0);
        $only_money = input('only_money', 0);
        $info = ChannelModel::get($id);
        if(request()->isGet()){
            if($info) $info['only_money'] = $only_money;
            return view('set', compact('info'));
        }
        //post数据操作
        $type = $id ? '修改' : '添加';
        $result = ChannelModel::setChannel();
        if(!$result) showmessage($type.'失败');
        log_operation($type.'通道-'. ($id ? $info['name_cn'] : $result));
        showmessage($type.'成功', 1);
    }

    public function del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $info = ChannelModel::get($id);
        $ret = ChannelModel::del($id);
        if(!$ret) showmessage('删除失败');
        log_operation('删除通道-'.$info['name_cn']);
        showmessage('删除成功', 1);
    }

    public function status()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $ret = ChannelModel::changeStatus($id);
        if(!$ret) showmessage('切换失败');
        $msg = $ret == 1 ? '启用成功' : '禁用成功' ;
        $info = ChannelModel::get($id);
        log_operation('切换通道状态-'.$info['name_cn'].'，'.$msg);
        showmessage($msg, 1);
    }
}