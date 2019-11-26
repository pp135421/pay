<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/24
 * Time: 15:49
 */

namespace app\admin2020\model;

use think\Db;
use think\Model;
use app\admin2020\model\MemberChannel AS MemberChannelModel;
class Member extends Common
{
    protected static $memberChanel = NULL;

    public static function setMember()
    {
        $id = input('id', 0);
        $username = input('username', '');
        $nickname = input('nickname', '');
        $password = input('password', '');
        $paypwd = input('paypwd', '');
        $deposit_amount = input('deposit_amount', '');
        $deposit_type = input('deposit_type', 1);
        $agent = input('agent', '1');
        $pid = input('pid', '0');
        $safe_ip = input('safe_ip', '');
        $login_error_count = input('login_error_count', 0);
        if($pid){
            $memberPidInfo = self::get($pid);
            if(!$memberPidInfo){
                showmessage('上级ID账户不存在！');
            }
            if($memberPidInfo->agent != 2){
                showmessage('上级ID账户不是代理商户！');
            }
        }

        $primary = [];
        if($id){
            if($password) $data['password'] = setPwdSalt($password);
            if($paypwd) $data['paypwd'] = setPwdSalt($paypwd);
            $memberInfo = self::get(['id' => $id]);
            if($agent == 1 && $memberInfo && $memberInfo->agent == 2){
                $memberInfo = self::get(['pid' => $id]);
                if($memberInfo) showmessage('代理商户被关联普通商户，无法修改！');
            }
            $primary['id'] = $id;
        }else{
            if(!$username) showmessage('账户不能为空！');
            $data['username'] = $username;
            $memberInfo = self::get($data);
            if($memberInfo) showmessage('账户已存在！');
            if(!$password) $password = '123456'; //默认登录密码固定 123456
            if(!$paypwd) $paypwd = '123456'; //默认支付密码固定 123456
            $data['password'] = setPwdSalt($password);
            $data['paypwd'] = setPwdSalt($paypwd);
            //生成商户号
            $data['member_id'] = chr(rand(65,90)). date('m'). time();
            //支付密钥
            $data['apikey'] = md5(setPwdSalt($data['paypwd']. rand(10000,99999)));
            $data['create_date'] = time();
        }
        if($nickname){
            $data['nickname'] = $nickname;
        }else{
            $data['nickname'] = $username;
        }
        $data['deposit_amount'] = $deposit_amount;
        $data['deposit_type'] = $deposit_type;
        $data['login_error_count'] = $login_error_count;
        $data['pid'] = $pid;
        $data['safe_ip'] = $safe_ip;
        $data['agent'] = $agent;
        if($data['agent'] == 2){
            unset($data['pid']);
        }
        $result = (new self)->save($data, $primary);
        if(!$result) return false;
        return true;
    }

    //通道编辑
    public static  function memberChannelEdit()
    {
        $data = [];
        $member_id = input('member_id', '0');
        if(!$member_id) showmessage('修改的商户号id异常！');
        $alipay_status = input('alipay_status', '') ? 1 : 2;
        $wechat_status = input('wechat_status', '') ? 1 : 2;

        //支付宝单独
        $alipayRadio = input('alipayRadio', '1');
        if($alipayRadio == 1){
            $channelId = input('alipayChannelId', []);
            if($alipay_status == 1 && !$channelId) showmessage('支付宝：单独通道未设置！');
            $params = [];
            $params['channel_id'] = $channelId;
            $params['type_name'] = 'alipay';
            $params['member_id'] = $member_id;
            $params['status'] = $alipay_status;
            $params['regulation'] = 1;
            $data[] = $params;
        }
        //支付宝轮询
        if($alipayRadio == 2){
            $channelIds = input('alipayChannelIdText/a', []);
            if($alipay_status == 1 && !$channelIds) showmessage('支付宝：轮询通道未设置！');
            $channelIds = array_unique($channelIds);
            foreach ($channelIds as $k => $v) {
                if($alipay_status == 1 && !$v){
                    showmessage('支付宝：轮询通道未设置2！');
                }
            }
            foreach ($channelIds as $k => $v) {
                $params = [];
                $params['channel_id'] = $v;
                $params['type_name'] = 'alipay';
                $params['member_id'] = $member_id;
                $params['status'] = $alipay_status;
                $params['regulation'] = 2;
                $data[] = $params;
            }
        }

        //微信单独
        $wechatRadio = input('wechatRadio', '1');
        if($wechatRadio == 1){
            $channelId = input('wechatChannelId', []);
            if($wechat_status == 1 && !$channelId) showmessage('微信：单独通道未设置！');
            $params = [];
            $params['channel_id'] = $channelId;
            $params['type_name'] = 'wechat';
            $params['member_id'] = $member_id;
            $params['status'] = $wechat_status;
            $params['regulation'] = 1;
            $data[] = $params;
        }
        //微信轮询
        if($wechatRadio == 2){
            $channelIds = input('wechatChannelIdText/a', []);
            if($wechat_status == 1 && !$channelIds) showmessage('微信：轮询通道未设置！');
            foreach ($channelIds as $k => $v) {
                if($wechat_status == 1 && !$v){
                    showmessage('微信：轮询通道未设置2！');
                }
            }
            $channelIds = array_unique($channelIds);
            foreach ($channelIds as $k => $v) {
                $params = [];
                $params['channel_id'] = $v;
                $params['type_name'] = 'wechat';
                $params['member_id'] = $member_id;
                $params['status'] = $wechat_status;
                $params['regulation'] = 2;
                $data[] = $params;
            }
        }

        // 启动事务
        Db::startTrans();
        db('member_channel')->where([
            'member_id' => $member_id,
        ])->delete();
        $result = db('member_channel')->insertAll($data);
        if(!$result){
            // 回滚事务
            Db::rollback();
            return false;
        }
        db('member')->where(['member_id' => $member_id])->setField([
            'regulation_alipay' => $alipayRadio,
            'alipay_rate' => input('alipay_rate', '0'),
            'regulation_wechat' => $wechatRadio,
            'wechat_rate' => input('wechat_rate', '0'),
        ]);
        // 提交事务
        Db::commit();
        return true;
    }
}