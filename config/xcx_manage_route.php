<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午5:15
 */
use think\Route;

//微信小程序接口路由群组
Route::group(['name' => 'wechat' , 'prefix' => 'xcx_manage/'],function (){
    //管理端小程序获取openid
    Route::rule('getManageOpenId','main.ThirdLogin/getManageOpenId');
    //manage小程序支付
    Route::rule('manageSmallApp','main.WechatPay/manageSmallApp','get|post|options');

    //工作人员
    Route::group(['name' => 'manage'],function (){
        //登陆
        Route::rule('login','main.ManageAuth/login','post|options');
        //绑定授权信息
        Route::rule('phoneBindWechat','main.ManageAuth/phoneBindWechat','post|options');
        //变更密码
        Route::rule('changePass','personal.ManageInfo/changePass','post|options');
        //小程序获取当前登录人员的所有信息
        Route::rule('getManageAuth','personal.ManageInfo/getManageAuth','get|options');
        //筛选手机号码
        Route::rule('phoneRetrieval','appointment.HelpOther/phoneRetrieval','post|options');
        //我的预约列表
        Route::rule('reservationOrder','personal.ManageInfo/reservationOrder','post|options');
        //电话获取用户姓名
        Route::rule('getUserName','appointment.ManageReservation/phoneGetUserName','post|options');
        //可预约吧台列表
        Route::rule('tableList','appointment.ManageReservation/tableList','post|options');
        //预约确认
        Route::rule('reservationConfirm','appointment.ManageReservation/reservationConfirm','post|options');
        //主动取消支付,释放桌台
        Route::rule('releaseTable','appointment.ManageReservation/releaseTable','post|options');
        //取消预约
        Route::rule("cancelReservation","appointment.ManageReservation/cancelReservation",'post|options');
        //礼券
        Route::group(['name' => 'voucher'],function (){
            //所有的桌位列表
            Route::rule('getTableList','main.Voucher/getTableList','post|options');
            //获取所有桌位列表不联动小区
            Route::rule('getTableAllList','main.Voucher/getTableAllList','post|options');
            //申请使用礼券
            Route::rule('applyUseVoucher','main.Voucher/applyUseVoucher','post|options');
        });
        //辅助客户
        Route::group(['name' => 'helpUser'],function (){
            //充值确认
            Route::rule('confirmRecharge','main.StoredValue/confirmRecharge','post|options');
        });
        //桌台操作
        Route::group(['name' => 'tableAction'],function (){
            //清台
            Route::rule('cleanTable','appointment.TableAction/cleanTable','post|options');

        });

        //点单
        Route::group(['name' => 'pointList'],function (){
            //扫码获取台位开台用户身份列表
            Route::rule('qrCodeGetUserIdentity','appointment.PointList/qrCodeGetUserIdentity','get|options');
            //点单选台列表
            Route::rule('selectionTableList','appointment.PointList/selectionOpenTableList','post|options');

        });
    });

});