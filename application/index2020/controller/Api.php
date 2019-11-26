<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 13:08
 */

namespace app\index2020\controller;

class Api extends Common
{
    public  function rate()
    {
        $info = db('member')->where(['member_id' => MEMBER_ID])->find();
        $memberChannelData = db('member_channel')->field('type_name, status')->where(['member_id' => MEMBER_ID])->select();
        $memberChannelData = array_unique($memberChannelData, SORT_REGULAR);
        return view('rate', compact('memberChannelData', 'info'));
    }

    public  function member()
    {
        $info = db('member')->where(['member_id' => MEMBER_ID])->find();
        return view('member', compact('info'));
    }

}