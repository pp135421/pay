<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 13:10
 */

namespace app\admin2020\model;

class Alipayaccount extends Common
{
    protected $table = 'alipay_account';

    public static function setAlipayAccout()
    {
        $id = input('id', '');
        $account = input('account', '');
        if(!$account) showmessage('支付宝账户不能为空！');
        $name =  input('name', '');
        if(!$name) showmessage('支付宝昵称不能为空，建议为账户内部昵称');
        $appid =  input('appid', '');
        if(!$appid) showmessage('appid（userID）不能为空');
        $data = [
            'account' => $account,
            'name' => $name,
            'appid' => $appid,
        ];
        $primary = [];
        if($id){
            if(self::where('id', 'neq', $id)->where(['account'=>$account])->select()){
                showmessage('支付宝账户[ '.$account.' ] 已存在');
            }
            $primary['id'] = $id;
        }else{
            if(self::where(['account'=>$account])->select()){
                showmessage('支付宝账户[ '.$account.' ] 已存在');
            }
            $data['status'] = 2; //2：默认先禁用状态，方便调试成功后在轮询上线
        }
        $model = new self();
        $result = $model->save($data, $primary);
        if(!$id) $result = $model->getLastInsID();
        return $result;
    }
}