<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 13:10
 */

namespace app\admin2020\model;

class Alipaybank extends Common
{
    protected $table = 'alipay_bank';

    public static function setAlipayBank()
    {
        $id = input('id', '');
        $bank_name = input('bank_name', '');
        if(!$bank_name) showmessage('银行名字不能为空！');
        $bank_mark =  input('bank_mark', '');
        if(!$bank_mark) showmessage('银行英文编码不能为空');
        $bank_account =  input('bank_account', '');
        if(!$bank_account) showmessage('银行开户名不能为空');
        $card_no =  input('card_no', '');
        if(!$card_no) showmessage('银行卡号不能为空');
        $card_id =  input('card_id', '');
        $data = [
            'bank_name' => $bank_name,
            'bank_mark' => $bank_mark,
            'bank_account' => $bank_account,
            'card_no' => $card_no,
            'card_id' => $card_id,
        ];
        $primary = [];
        if($id){
            if(self::where('id', 'neq', $id)->where(['card_no'=>$card_no])->select()){
                showmessage('银行卡号 [ '.$card_no.' ] 已存在');
            }
            $primary['id'] = $id;
        }else{
            if(self::where(['card_no'=>$card_no])->select()){
                showmessage('银行卡号 [ '.$card_no.' ] 已存在');
            }
            $data['key'] = md5(uniqid()); //2：默认先禁用状态，方便调试成功后在轮询上线
            $data['status'] = 2; //2：默认先禁用状态，方便调试成功后在轮询上线
        }
        $model = new self();
        $result = $model->save($data, $primary);
        if(!$id) $result = $model->getLastInsID();
        return $result;
    }
}