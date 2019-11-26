<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/1
 * Time: 20:11
 */

namespace app\index2020\model;


class Bank extends Common
{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'bank_card';

    public static function bank_edit($params){
        $bank_id = $params['bank_id'];

        $bankCode = db('bank')->where('id',$params['bank_code'])->find();
        $saveData = [
            'member_id' => MEMBER_ID,
            'bank_id' => $bankCode['id'],
            'bankname' => $bankCode['bankname'],
            'bankzhiname' => $params['bankzhiname'],
            'bank_account' => $params['account'],
            'card_number' => $params['card_number'],
            'province' => $params['province'],
            'city' => $params['city'],
            'ip' => getClientIP(),
        ];

        //银行卡号ID存在则更新
        if($bank_id){
            $saveData['update_date'] = time();
            (new self)->save($saveData,['id'=>$bank_id]);
        }else{
            $bankCardInfo = db('bank_card')->where('card_number', $params['card_number'])->find();
            if($bankCardInfo) ajaxReturn(2,'银行卡号：'. $params['card_number']. ' 已存在，请更换其他卡号！');
            $saveData['create_date'] = time();
            $saveData['status'] = 1;
            (new self)->save($saveData);
        }
        $bank_id?$msg='更新成功':$msg='添加银行卡成功';
        ajaxReturn(1,$msg);
    }
}