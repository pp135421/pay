<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 13:10
 */

namespace app\admin2020\model;

class Wechataccount extends Common
{
    protected $table = 'wechat_account';

    public static function setWechatAccout()
    {
        $id = input('id', '');
        $account = input('account', '');
        if(!$account) showmessage('微信账户不能为空！');
        $name =  input('name', '');
        if(!$name) showmessage('微信昵称不能为空，必须正确昵称');
        $day_max_money =  input('day_max_money/f', '0.00');
        $is_unusual =  input('is_unusual', '2');
        $data = [
            'account' => $account,
            'name' => base64_encode($name),
            'day_max_money' => $day_max_money,
            'is_unusual' => $is_unusual,
        ];
        $pid = input('pid', '0');
        if($pid) $data['pid'] = $pid;
        $primary = [];
        if($id){
            if(self::where('id', 'neq', $id)->where(['account'=>$account])->select()){
                showmessage('微信账户 [ '.$account.' ] 已存在！！');
            }
            if(self::where('id', 'neq', $id)->where(['name'=> base64_encode($name)])->select()){
                showmessage('微信昵称 [ '.$name.' ] 已存在！！');
            }
            $primary['id'] = $id;
        }else{
            if(self::where(['account'=>$account])->select()){
                showmessage('微信账户 [ '.$account.' ] 已存在！');
            }
            if(self::where('id', 'neq', $id)->where(['name'=>base64_encode($name)])->select()){
                showmessage('微信昵称 [ '.$name.' ] 已存在！！');
            }
            $data['status'] = 2; //2：默认先禁用状态，方便调试成功后在轮询上线
            $data['create_date'] = time();
        }
        $model = new self();
        $result = $model->save($data, $primary);
        if(!$id) $result = $model->getLastInsID();
        return $result;
    }
}