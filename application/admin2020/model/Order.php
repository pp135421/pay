<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 13:10
 */

namespace app\admin2020\model;


use think\Model;

class Order extends Common
{
    public function member()
    {
        return $this->belongsTo('member', 'member_id', 'member_id');
    }

    public function alipay_account()
    {
        return $this->belongsTo('alipay_account', 'relate_key', 'account');
    }

}