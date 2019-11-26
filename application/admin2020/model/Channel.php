<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 15:54
 */

namespace app\admin2020\model;

use think\Db;
use think\Model;

class Channel extends Common
{
    public static function setChannel()
    {
        $name_en = input('name_en', '');
        $name_cn =  input('name_cn', '');
        $data = [
            'name_en' => $name_en,
            'name_cn' => $name_cn,
            'channel_money' => input('channel_money/f', 0),
            'min_money' => input('min_money/f', 0),
            'max_money' => input('max_money/f', 0),
            'min_money_poll' => input('min_money_poll/f', 0),
            'max_money_poll' => input('max_money_poll/f', 0),
            'weight' => input('weight', 5),
            'rate' => input('rate/f', 0),
            'is_inner' => input('is_inner', '2'),
            'type_name' => input('type_name', ''),
        ];
        if(!$data['name_en']) showmessage('必须选择英文名字！');
        if(!$data['name_cn']) showmessage('必须选择中文名字！');
        if(!$data['type_name']) showmessage('必须选择通道类型！');
        if($data['rate'] > 0.05) showmessage('通道费率异常！');
        $id = input('id', 0);
        $primary = [];
        if($id){
            if(self::where('id', 'neq', $id)->where(['name_en'=>$name_en])->select()){
                showmessage('通道名称[ '.$name_cn.' ] 已存在');
            }
            $primary['id'] = $id;
        }else{
            if(self::where(['name_en'=>$name_en])->select()){
                showmessage('通道名称[ '.$name_cn.' ] 已存在');
            }
            $data['create_date'] = time();
            $data['status'] = 2;
        }

        $model = new self();
        $result = $model->save($data, $primary);
        if(!$id) $result = $model->getLastInsID();
        return $result;
    }
}