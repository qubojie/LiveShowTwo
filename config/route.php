<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;

//测试
Route::rule("test","test/Test/index","POST");

//个人测试单据退款接口
Route::rule("refundMoney","test/Test/refundMoney","POST");

//储值金额获取赠送礼金金额
Route::rule('rechargeAmountGetCashAmount','xcx_manage/main.ManageAuth/rechargeAmountGetCashAmount','post|options');

Route::group(['name' => 'sys'],function (){

    //定时处理未支付超出指定时间的订单selectionTableList
    Route::rule('changeOrderStatus','index/ChangeStatus/changeOrderStatus');

    Route::rule('autoCancelRevenueListOrder','index/ChangeStatus/autoCancelRevenueListOrder');
    //定时删除服务message
    Route::rule('AutoDeleteCallMessage','index/ChangeStatus/AutoDeleteCallMessage');
});

