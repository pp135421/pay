<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 13:10
 */

namespace app\admin2020\model;

class Dingding extends Common
{
    protected $table = 'dingding';

    public static function setDingding()
    {
        $id = input('id', '');
        // $signkey = input('signkey', '');
        // if(!$signkey) showmessage('signkey不能为空！');
        $name =  input('name', '');
        if(!$name) showmessage('关联账户不能为空');
        $nat_domin =  input('nat_domin', '');
        if(!$nat_domin) showmessage('穿透域名不能为空');
        $data = [
            // 'signkey' => $signkey,
            'name' => $name,
            'nat_domin' => trim($nat_domin, '/'),
        ];
        $primary = [];
        if($id){
            if(self::where('id', 'neq', $id)->where(['name'=>$name])->select()){
                showmessage('关联账户[ '.$name.' ] 已存在');
            }
            $primary['id'] = $id;
        }else{
            if(self::where(['name'=>$name])->select()){
                showmessage('关联账户[ '.$name.' ] 已存在');
            }
            $data['status'] = 2; //2：默认先禁用状态，方便调试成功后在轮询上线
            $data['signkey'] = uniqid();
        }
        $model = new self();
        $result = $model->save($data, $primary);
        if(!$id) $result = $model->getLastInsID();
        return $result;
    }
}