<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 13:10
 */

namespace app\index2020\model;


use think\Model;

class Moneychange extends Common
{
    protected $table = 'money_change';
    public function member()
    {
        return $this->belongsTo('member', 'member_id', 'member_id');
    }

}