<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/24
 * Time: 14:45
 */
namespace app\admin2020\controller;
use app\admin2020\model\Member as MemberModel;
use app\admin2020\model\MemberChannel AS MemberChannelModel;
class Member extends Common
{
    public function __construct(){
        parent::__construct();
    }

    public function index()
    {
        $where = [];
        //商户号
        $member_id = input('member_id', '');
        if($member_id){
            $where['member_id'] = $member_id;
        }
        //商户类型
        $agent = input('agent', '');
        if($agent){
            $where['agent'] = $agent;
        }
        //商户状态
        $status = input('status', '');
        if($status){
            $where['status'] = $status;
        }
        //商户状态
        $orderBy = input('orderBy', 'sort');

        //每页显示20条数据
        $perCount = 20;
        $list = MemberModel::where($where)->order($orderBy.' desc'.', id desc')->paginate($perCount, false, ['query'=>input()]);
        $memberData = $list->toArray()['data'];
        $memberDataAll = db('member')->select();
        $adminDataAll = db('admin')->select();
        $memberChannelData = db('member_channel')->select();
        $channelData = db('channel')->select();

        foreach ($memberChannelData as $k => $v){
            if(!isset($memberChannelData[$k]['channel_rate'])) $memberChannelData[$k]['channel_rate'] = '';
            if(!isset($memberChannelData[$k]['channel_name'])) $memberChannelData[$k]['channel_name'] = '';
            if(!isset($memberChannelData[$k]['channel_name_en'])) $memberChannelData[$k]['channel_name_en'] = '';
            foreach ($channelData as $k2 => $v2) {
                if($v['channel_id'] == $v2['id']){
                    $memberChannelData[$k]['channel_rate'] = $v2['rate'];
                    $memberChannelData[$k]['channel_name'] = $v2['name_cn'];
                    $memberChannelData[$k]['channel_name_en'] = $v2['name_en'];
                }
            }
        }
        foreach ($memberData as $k => $v) {
            if(!isset($memberData[$k]['is_inner'])) $memberData[$k]['is_inner'] = '-';
            if(!isset($memberData[$k]['channel_rate_x'])) $memberData[$k]['channel_rate_x'] = '-';
            if(!isset($memberData[$k]['loginNoDanger'])) $memberData[$k]['loginNoDanger'] = true;
            if(!isset($memberData[$k]['payNoDanger'])) $memberData[$k]['payNoDanger'] = true;
            if($v['password'] == setPwdSalt('123456')){
                $memberData[$k]['loginNoDanger'] = false;
            }
            if($v['paypwd'] == setPwdSalt('123456')){
                $memberData[$k]['payNoDanger'] = false;
            }
            if(!isset($memberData[$k]['type_name'])) $memberData[$k]['type_name'] = '';
            foreach ($memberChannelData as $k2 => $v2) {
                if($v['id'] == $v2['member_id']){
                    $memberData[$k]['type_name'] = $v2['type_name'];
                }
            }
        }
        foreach ($memberData as $k => $v) {
            if(!isset($memberData[$k]['pid_nickname'])) $memberData[$k]['pid_nickname'] = '-';
            if(!isset($memberData[$k]['pid_username'])) $memberData[$k]['pid_username'] = '-';
            if(!isset($memberData[$k]['pid_alipay_rate'])) $memberData[$k]['pid_alipay_rate'] = '-';
            if(!isset($memberData[$k]['pid_wechat_rate'])) $memberData[$k]['pid_wechat_rate'] = '-';
            foreach ($memberDataAll as $k2 => $v2) {
                if($v['pid'] == $v2['id']){
                    $memberData[$k]['pid_nickname'] = $v2['nickname'];
                    $memberData[$k]['pid_username'] = $v2['username'];
                    $memberData[$k]['pid_alipay_rate'] = $v2['alipay_rate'];
                    $memberData[$k]['pid_wechat_rate'] = $v2['wechat_rate'];
                }
            }
        }
        foreach ($memberData as $k => $v) {
            if(!isset($memberData[$k]['alipay_channel'])) $memberData[$k]['alipay_channel'] = [];
            if(!isset($memberData[$k]['wechat_channel'])) $memberData[$k]['wechat_channel'] = [];
            if(!isset($memberData[$k]['alipay_channel_rate'])) $memberData[$k]['alipay_channel_rate'] = [];
            if(!isset($memberData[$k]['wechat_channel_rate'])) $memberData[$k]['wechat_channel_rate'] = [];
            if(!isset($memberData[$k]['alipay_status'])) $memberData[$k]['alipay_status'] = '2';
            if(!isset($memberData[$k]['wechat_status'])) $memberData[$k]['wechat_status'] = '2';
            foreach ($memberChannelData as $k2 => $v2) {
                if($v['member_id'] == $v2['member_id']){
                    if($v2['type_name'] == $v['type_name']){
                        $memberData[$k][$v['type_name'].'_channel'] = $v2['channel_name'];
                        $memberData[$k][$v['type_name'].'_channel_rate'] = $v['channel_rate_x'];
                        $memberData[$k][$v['type_name'].'_status'] = $v2['status'];
                    }else{
                        if($v2['type_name'] == 'alipay' && $v2['channel_name']){
                            $memberData[$k]['alipay_channel'][] = $v2['channel_name'];
                            $memberData[$k]['alipay_channel_rate'][] = $v2['channel_rate'];
                            $memberData[$k]['alipay_status'] = $v2['status'];
                        }
                        if($v2['type_name'] == 'wechat' && $v2['channel_name']){
                            $memberData[$k]['wechat_channel'][] = $v2['channel_name'];
                            $memberData[$k]['wechat_channel_rate'][] = $v2['channel_rate'];
                            $memberData[$k]['wechat_status'] = $v2['status'];
                            $memberData[$k]['channel_status'] = $v2['status'];
                        }
                    }
                }
            }
        }
        foreach ($memberData as $k => $v) {
            if(!isset($memberData[$k]['channel_status_alipay'])) $memberData[$k]['channel_status_alipay'] = [];
            if(!isset($memberData[$k]['channel_status_wechat'])) $memberData[$k]['channel_status_wechat'] = [];
            foreach ($channelData as $k2 => $v2) {
                if(in_array($v2['name_cn'], $v['alipay_channel'])){
                    $memberData[$k]['channel_status_alipay'][] = $v2['status'];
                }
                if(in_array($v2['name_cn'], $v['wechat_channel'])){
                    $memberData[$k]['channel_status_wechat'][] = $v2['status'];
                }
            }
        }
        //回显HTML数据
        $showData = [
            'member_id' => $member_id,
            'agent' => $agent,
            'status' => $status,
            'orderBy' => $orderBy,
        ];
        $count = MemberModel::where($where)->count();
        return view('index', compact('list', 'memberData', 'count', 'showData'));
    }

    public function ip_limit()
    {
        //每页显示20条数据
        $perCount = 20;
        $list = db('ip')->paginate($perCount, false, ['query'=>input()]);
        $count = db('ip')->count();
        return view('ip_limit', compact('list', 'count'));
    }

    public function ip_del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $info = db('ip')->find($id);
        $ret = db('ip')->delete($id);
        if(!$ret) showmessage('删除黑名单失败');
        $time = time();
        db('log_login')->where([
            'ip' => $info['ip'],
            'result' => 2, //2->失败
        ])->delete();
        log_operation('删除黑名单-'.$info['ip']);
        showmessage('删除成功', 1);
    }

    public function set(){
        $id = input('id', '');
        $info = MemberModel::get($id);
        if(request()->isGet()){
            $memberAgentData = MemberModel::all(['agent' => 2]);
            return view('set', compact('info', 'memberAgentData'));
        }
        $result = MemberModel::setMember();
        $desc = $id ? '修改' : '添加';
        if(!$result) showmessage($desc. '商户失败');
        log_operation('修改商户-'. $info['username']);
        cache('channel_choose', null);
        showmessage($desc. '商户成功', 1);
    }


    public function status()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $info = MemberModel::get($id);
        $ret = MemberModel::changeStatus($id);
        if(!$ret) showmessage('切换失败');
        $msg = $ret == 1 ? '启用成功' : '禁用成功';
        log_operation('切换商户状态：'. $info['username']. '，'.$msg);
        showmessage($msg , 1);
    }

    public function del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        if($id == '8') showmessage('内部商户号无法删除');
        $info = MemberModel::get($id);
        if($info['balance'] > 0) showmessage('无法删除还有余额商户！');
        $memberPidInfo = MemberModel::get(['pid' => $id]);
        if($memberPidInfo) showmessage('无法删除存在下级商户的代理！');
        $ret = MemberModel::del($id);
        if(!$ret) showmessage('删除失败');
        log_operation('删除商户-'.$info['username']);
        showmessage('删除成功', 1);
    }

    public function channel(){
        $member_id = input('member_id');
        if(request()->isGet()){
            $memberInfo = db('member')->where(['member_id' => $member_id])->find();
            $memberChannelData = db('member_channel')->where([
                'member_id' => $member_id,
            ])->select();
            $channelData = db('channel')->order('is_inner asc, create_date asc')->select();//只展示状态为正常的通道
            foreach ($memberChannelData as $k => $v) {
                if(!isset($memberChannelData[$k]['channel_name'])) $memberChannelData[$k]['channel_name'] = '-';
                if(!isset($memberChannelData[$k]['min_money_poll'])) $memberChannelData[$k]['min_money_poll'] = '0.00';
                if(!isset($memberChannelData[$k]['max_money_poll'])) $memberChannelData[$k]['max_money_poll'] = '0.00';
                if(!isset($memberChannelData[$k]['weight'])) $memberChannelData[$k]['weight'] = '5';
                foreach ($channelData as $k2 => $v2) {
                    if($v['channel_id'] == $v2['id']){
                        $memberChannelData[$k]['channel_name'] = $v2['name_cn'].'_'. bcadd($v2['rate'] * 100, 0, 2). '%';
                        $memberChannelData[$k]['min_money_poll'] = $v2['min_money_poll'];
                        $memberChannelData[$k]['max_money_poll'] = $v2['max_money_poll'];
                        $memberChannelData[$k]['weight'] = $v2['weight'];
                    }
                }
            }
            $alipayArr = [];
            $wechatArr = [];
            $alipayArr2 = [];
            $wechatArr2 = [];
            foreach ($memberChannelData as $k => $v) {
                if($v['member_id'] == $memberInfo['member_id']){
                    if($v['type_name'] == 'alipay'){
                        if($memberInfo['regulation_alipay'] == 1){
                            $alipayArr[] = $v;
                        }
                        if($memberInfo['regulation_alipay'] == 2){
                            $alipayArr2[] = $v;
                        }
                    }
                    if($v['type_name'] == 'wechat'){
                        if($memberInfo['regulation_wechat'] == 1){
                            $wechatArr[] = $v;
                        }
                        if($memberInfo['regulation_wechat'] == 2){
                            $wechatArr2[] = $v;
                        }
                    }
                }
            }
            return view('channel',compact('member_id', 'memberInfo', 'channelData', 'alipayArr', 'wechatArr', 'alipayArr2', 'wechatArr2'));
        }
        $ret = MemberModel::memberChannelEdit();
        if(!$ret) showmessage('修改失败！');
        log_operation('修改商户通道配置-'.$member_id);
        //删除轮询通道缓存
        cache('channel_choose', null);
        showmessage('修改成功', 1);
    }

    public function drawings()
    {
        $list = db('drawings')->find();
        if(request()->isPost()){
            $max_money = input('max_money','');//最大金额
            $min_money = input('min_money','');//最小金额
            $time = input('time','');//次数



            $saveData = [];
            $max_money?$saveData['max_money']=$max_money:'';
            $min_money?$saveData['min_money']=$min_money:'';
            $time?$saveData['time']=$time:'';
            $res = db('drawings')->where('id',1)->update($saveData);
            ajaxReturn(1,'提款规则编辑成功');

        }
        return view('drawings',compact('list'));
    }
}
