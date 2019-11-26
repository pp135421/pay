<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/1
 * Time: 21:08
 */

namespace app\index2020\controller;

use app\index2020\model\Moneychange;
class Finance extends Common
{
    public function index(){
        $where = [];
        //处理订单创建时间范围
        $create_time = input('create_time', '');
        $arr_create_time = explode('|', $create_time);
        if($arr_create_time && count($arr_create_time) == 2){
            if(count($arr_create_time) == 2){
                $where['create_date'] = array('between', array(strtotime($arr_create_time[0]), strtotime($arr_create_time[1])));
            }
        }

        //处理平台订单号或下游订单号或商户号
        $keyword = input('keyword', '');
        if($keyword){
            if(strpos($keyword, config('platform_name_en')) !== false){
                $where['platform_order_id'] = $keyword;
            }else if(preg_match('/^[A-Z][0-1][0-9]\d{10}$/', $keyword)){
                $where['member_id'] = $keyword;
            }else{
                $where['submit_order_id'] = $keyword;
            }
        }

        //处理通道
        $channel_id = input('channel_id', '');
        if($channel_id){
            $where['channel_id'] = $channel_id;
        }

        //处理接口
        $type_name = input('type_name', '');
        if($type_name){
            $where['type_name'] = $type_name;
        }

        //资金变动类型
        $change_type = input('change_type', '');
        if($change_type){
            $where['change_type'] = $change_type;
        }

        //对应商户号
        if(MEMBER_ID){
            $where['member_id'] = MEMBER_ID;
        }

        //每页显示30条数据
        $perCount = 30;
        $list = Moneychange::where($where)->order('id desc')->paginate($perCount, false, ['query'=>input()]);

        //获取全部通道数据
        $channelData = db('channel')->field('id,name_cn')->select();

        //回显HTML数据
        $showData = [
            'create_time' => $create_time,
            'keyword' => $keyword,
            'channel_id' => $channel_id,
            'type_name' => $type_name,
            'change_type' => $change_type,
        ];
        $change_type = config('change_type');
        return view('index', compact('list', 'showData', 'moneyChangeData', 'channelData', 'change_type'));
    }

    public function getExcel(){
        $data = input();
        $condition = [];

        isset($data['create_time'])&&$data['create_time']?$condition['create_date'] = strtotime($data['create_time']):'';
        isset($data['channel_id'])&&$data['channel_id']?$condition['channel_id'] = $data['channel_id']:'';
        isset($data['type_name'])&&$data['type_name']?$condition['type_name'] = $data['type_name']:'';
        isset($data['change_type'])&&$data['change_type']?$condition['change_type'] = $data['change_type']:'';

        if($data['keyword']){
            if(strpos($data['keyword'], config('platform_name_en')) !== false){
                $condition['platform_order_id'] = $data['keyword'];
            }else if(preg_match('/^[A-Z][0-1][0-9]\d{10}$/', $data['keyword'])){
                $condition['member_id'] = $data['keyword'];
            }else{
                $condition['submit_order_id'] = $data['keyword'];
            }
        }

        $condition['member_id'] = MEMBER_ID;

        $data = db('money_change')->field('submit_order_id,member_id,before_money,change_money,after_money,create_date,change_type,type_name')->where($condition)
            ->order('id desc')->select();
        foreach ($data AS $key => $value){
            $data[$key]['create_date'] = date('Y-m-d H:i:s',$value['create_date']);
            $content = '';
            switch ($value['change_type']){
                case 11:
                    $content = '增加：订单充值';
                    break;
                case 12:
                    $content = '增加：订单解冻';
                    break;
                case 13:
                    $content = '增加：下发驳回';
                    break;
                case 21:
                    $content = '减少：订单冻结';
                    break;
                case 22:
                    $content = '减少：下发手续费';
                    break;
                case 23:
                    $content = '减少：结算申请';
                    break;
            }
            $data[$key]['change_type'] = $content;
        }
        $title = ['订单号','商户号','变动前金额','变动金额','变动后金额','变动时间','资金变动类型','通道类型'];
        exportExcel($title, $data, date('Ymd'). '-'. config('platform_name_en'). '-资金变动记录报表');
    }
}