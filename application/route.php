<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;

Route::rule([
    'df_request/:deposit_order_id/:channel_id'=>'admin2020/api/df_request',
    'df_notify/:deposit_order_id'=>'admin2020/api/df_notify',
    'df_query'=>'admin2020/api/df_query',
    'df_query_status'=>'admin2020/api/df_query_status',
    'notify/:orderid'=>'admin2020/api/notify',
    'paid'=>'admin2020/api/paid',
    'paofen'=>'admin2020/api/paofen',
    'hongbao'=>'admin2020/api/hongbao',
    'dingding'=>'admin2020/api/dingding',
    'nongxin'=>'admin2020/api/nongxin',
    'shoukuan'=>'admin2020/api/shoukuan',
    'bank'=>'admin2020/api/bank',
    'receive_notify'=>'admin2020/api/receive_notify',
    'fenghuang'=>'admin2020/api/fenghuang',
    'zhongju'=>'admin2020/api/zhongju',
    'jubao'=>'admin2020/api/jubao',
    'dianyuantong'=>'admin2020/api/dianyuantong',
]);
