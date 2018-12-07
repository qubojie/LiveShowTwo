<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午5:15
 */
use think\Route;

//前台管理路由群组
Route::group(['name' => 'reception','prefix' => 'reception/'],function (){
    //基础操作
    Route::group(['name' => 'auth'],function (){
        //登陆
        Route::rule('login','main.Auth/login');
    });
    //前台人员信息
    Route::group(['name' => 'manageInfo'],function (){
        //修改密码
        Route::rule('changePass','personal.ManageInfo/changePass');
    });
    //消息
    Route::group(['name' => 'tableMessage'],function (){
        //消息列表
        Route::rule('messageList','personal.TableMessage/messageList');
        //消息确认
        Route::rule('confirm','personal.TableMessage/confirm','post|options');
    });
    //会员消费
    Route::group(['name' => 'consumption'],function (){
        //消费列表
        Route::rule('infoList','dining.BillPayAssistInfo/index','post|options');
        //新增消费待处理单据
        Route::rule('insertWaitDoOrder','dining.BillPayAssistInfo/insertWaitDoOrder','post|options');
        //确认Or取消消费
        Route::rule('cancelOrConfirm','dining.BillPayAssistInfo/cancelOrConfirm','post|options');
        //确认Or取消礼券
        Route::rule('cancelOrConfirmVoucher','dining.BillPayAssistInfo/cancelOrConfirmVoucher','post|options');
        //堂吃退款
        Route::rule('fullRefund','dining.BillPayAssistInfo/fullRefund','post|options');
    });

    //会员卡
    Route::group(['name' => 'vipCard'],function (){
        //储值
        Route::group(['name' => 'storageValue'],function (){
            //储值列表
            Route::rule('index','card.StorageValue/index','post|options');
            //确认充值
            Route::rule('rechargeConfirm','card.StorageValue/rechargeConfirm','post|options');
            //确认收款
            Route::rule('confirmMoney','card.StorageValue/confirmMoney','post|options');
        });
        //开卡
        Route::group(['name' => 'openCard'],function (){
            //获取所有的卡列表
            Route::rule('getCardInfo','card.OpenCard/getAllCardInfo','post|options');
            //开卡订单列表
            Route::rule('index','card.OpenCard/index','post|options');
            //确认开卡
            Route::rule('confirmOpenCard','card.OpenCard/confirmOpenCard','post|options');
        });
    });
    //统计
    Route::group(['name' => 'count'],function (){
        //桌消费统计列表
        Route::rule('tableConsumer','card.CountMoney/tableConsumer');
        //结算数据统计
        Route::rule('settlementCount','card.CountMoney/settlementCount');
        //结算操作
        Route::rule('settlementAction','card.CountMoney/settlementAction');
        //结算历史筛选列表
        Route::rule('settlementHistory','card.CountMoney/settlementHistory');
        //结算历史详情
        Route::rule('settlementHistoryDetails','card.CountMoney/settlementHistoryDetails');
        //插入结算数据详情
        Route::rule('insertDetailsInfo','card.CountMoney/insertDetailsInfo');
    });
    //堂吃
    Route::group(['name' => 'diningRoom'],function (){
        //首页
        Route::rule('index','dining.DiningRoom/todayTableInfo','post|options');
        //桌位详情
        Route::rule('tableInfo','dining.DiningRoom/tableInfo','post|options');
        //手机号码检索
        Route::rule('phoneRetrieval','dining.DiningRoom/phoneRetrieval','post|options');
        //开台
        Route::rule('openTable','dining.DiningRoom/openTable','post|options');
        //取消开台
        Route::rule('cancelOpenTable','dining.DiningRoom/cancelOpenTable','post|options');
        //补全已开台用户信息
        Route::rule('supplementInfo','dining.DiningRoom/supplementRevenueInfo','post|options');
        //开拼
        Route::rule('openSpelling','dining.DiningRoomTurnSpelling/openSpelling','post|options');
        //获取今日已开台或者空台的桌
        Route::rule('alreadyOpenTable','dining.DiningRoomTurnSpelling/alreadyOpenTable','post|options');
        //获取今日已开台或者空台(未预约)的信息
        Route::rule('openOrEmptyTable','dining.DiningRoomTurnSpelling/openOrEmptyTable','post|options');
        //转台或转拼
        Route::rule('turnTableOrSpelling','dining.DiningRoomTurnSpelling/turnTableOrSpelling','post|options');
    });

    //预定
    Route::group(['name' => 'reservation'],function (){
        //预定列表
        Route::rule('index','dining.Reservation/index','post|options');
        //桌位详情
        Route::rule('tableDetails','dining.Reservation/tableDetails','post|options');
        //预约确认
        Route::rule('reservationConfirm','dining.Reservation/createReservation','post|options');
        //取消预约
        Route::rule('cancelReservation','dining.Reservation/cancelReservation','post|options');
        //到店
        Route::rule('goToShop','dining.Reservation/goToShop','post|options');
    });

});