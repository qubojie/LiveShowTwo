<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午5:15
 */
use think\Route;

//微信小程序接口路由群组
Route::group(['name' => 'wechat' , 'prefix' => 'xcx_member/'],function (){
    //前台获取系统设置信息
    Route::rule('getSettingInfo','main.GetSettingInfo/getSettingInfo');
    //微信三方登陆
    Route::rule('wechatLogin','main.ThirdLogin/wechatLogin');
    //发送验证码
    Route::rule('captcha','main.Auth/sendVerifyCode','post|options');
    //验证验证码
    Route::rule('checkVerifyCode','main.Auth/checkVerifyCode','post|options');
    //手机号码注册
    Route::rule('phoneRegister','main.Auth/phoneRegister','post|options');
    //确认变更授权信息
    Route::rule('confirmChangeInfo','main.Auth/confirmChangeInfo','post|options');
    //变更手机号码
    Route::rule('changePhone','main.Auth/changePhone','post|options');
    //推荐人绑定
    Route::rule('referrerUser','main.Auth/referrerUser','post|options');
    //手机号+验证码登陆
    Route::rule('phoneVerifyLogin','main.Auth/phoneVerifyLogin','post|options');
    //扫码支付
    Route::rule('scavengingPay','main.WechatPay/scavengingPay','get|post|options');
    //H5支付
    Route::rule('wappay','main.WechatPay/wappay','get|post|options');
    //公众号支付
    Route::rule('jspay','main.WechatPay/jspay','get|post|options');
    //小程序支付
    Route::rule('smallapp','main.WechatPay/smallapp','get|post|options');
    //退款
    Route::rule('reFund','main.WechatPay/reFund','get|post|options');
    //订单查询
    Route::rule('query','main.WechatPay/query','get|post|options');
    //下载对账单
    Route::rule('download','main.WechatPay/download','get|post|options');
    //回调地址
    Route::rule('notify','main.WechatPay/notify','get|post|options');
    //钱包支付
    Route::rule('walletPay','orders.DishOrderPay/walletPay','post|options');
    //礼金支付
    Route::rule('cashGiftPay','orders.DishOrderPay/cashGiftPay','post|options');
    //扫码支付回调
    Route::rule('scavengingNotify','main.WechatPay/scavengingNotify','get|post|options');
    //检测手机号码是否存在
    Route::rule('checkPhoneExist','main.PublicAction/checkPhoneExist');

    Route::group(['name' => 'h5'],function (){
        //获取用户兴趣之类的标签列表
        Route::rule('tagList','main.UserInfo/tagList','post|options');
        //提交用户选中标签
        Route::rule('postInfo','main.UserInfo/postInfo','post|options');
        //获取用户姓名列表
        Route::rule('getUserList','main.UserInfo/getUserList','post|options');
        //获取卡类别
        Route::rule('getCardType','main.Auth/getCardType');
        //Vip卡列表
        Route::rule('cardList','main.Auth/cardList','post|options');
        //所有有效礼品列
        Route::rule('giftList','main.OpenCard/getGiftListInfo');
        //开卡操作
        Route::rule('OpenCard','main.OpenCard/index');
        //取消订单
        Route::rule('cancelOrder','main.BillOrder/cancelOrder');
    });

    //小程序
    Route::group(['name' => 'xcx'],function(){
        //获取预约时退款提示信息
        Route::rule('getReserveWaringInfo','main.PublicAction/getReserveWaringInfo');
        //分众广告
        Route::group(['name' => 'focus'],function (){
            //获取卡信息
            Route::rule('getCardInfo','focus.FocusAdvertising/getCardInfo','post|options');
            //去支付
            Route::rule('toPay','focus.FocusAdvertising/toPay','post|options');
            //获取分众二维码
            Route::rule('getSmallAppQrCode','focus.FocusAdvertising/getSmallAppQrCode','get|options');
        });
        //首页Banner
        Route::rule('bannerList','home.Banner/index');
        //首页文章列表
        Route::rule('article','home.Article/getArticle','post|options');
        //我的信息
        Route::rule('myInfo','personal.MyInfo/index','post|options');
        //修改个人信息
        Route::rule('changeInfo','personal.MyInfo/changeInfo','post|options');
        //钱包明细
        Route::rule('wallet','personal.MyInfo/wallet','post|options');
        //礼品券列表
        Route::rule('giftVoucher','personal.MyInfo/giftVoucher','post|options');
        //通知信息
        Route::group(['name' => 'tableCallMessage'],function (){
            //获取桌台号列表
            Route::rule('tableNumber','personal.TableCallMessage/tableNumber','post|options');
            //获取呼叫服务信息
            Route::rule('getCallMessage','personal.TableCallMessage/getCallMessage','post|options');
        });
        //联盟商家
        Route::group(['name' => 'merchantAction'],function (){
            //分类列表 ———— 键值对（小程序）
            Route::rule('cateList','personal.MerchantAction/cateList','post|options');
            //联盟商家列表（小程序）
            Route::rule('merchatList','personal.MerchantAction/merchatList','post|options');
        });
        //充值
        Route::group(['name' => 'Recharge'],function (){
            //充值列表
            Route::rule('index','personal.Recharge/rechargeList','post|options');
            //充值确认
            Route::rule('rechargeConfirm','personal.Recharge/rechargeConfirm','post|options');
        });
        //个人信息
        Route::group(['name' => 'myCenter'],function (){
            //获取充值按钮是否显示
            Route::rule('getRefillSwitch','personal.MyInfo/getRefillSwitch','get|options');
            //我的预约列表
            Route::rule('reservationOrder','personal.MyInfo/reservationOrder','post|options');
            //我的订单列表
            Route::rule('dishOrder','personal.MyInfo/dishOrder','post|options');
            //我的积分以及排行
            Route::rule('myPointDetails','personal.MyInfo/myPointDetails','post|options');
        });
        //预约
        Route::group(['name' => 'reservation'],function (){
            //筛选条件获取
            Route::rule('reserveCondition','main.PublicAction/reserveCondition','post|options');
            //可预约吧台列表
            Route::rule('tableList','appointment.Reservation/tableList','post|options');
            //检测桌台是否可被预约
            Route::rule('checkTableReservationCan','appointment.Reservation/checkTableReservationCan','post|options');
            //预约确认(交定金)
            Route::rule('reservationConfirm','appointment.Reservation/reservationConfirm','post|options');
            //取消预约
            Route::rule('cancelReservation','appointment.Reservation/cancelReservation','post|options');
            //用户主动取消支付,释放桌台
            Route::rule('releaseTable','appointment.Reservation/releaseTable','post|options');
            //预约点单结算 TODO '此接口暂无,此接口暂无'
            Route::rule('settlementOrder','appointment.ReservationOrder/settlementOrder','post|options');
        });
        //商品(菜品)
        Route::group(['name' => 'dishes'],function (){
            //菜品分类
            Route::rule('dishClassify','orders.Goods/dishClassify','post|options');
            //菜品列表
            Route::rule('index','orders.Goods/index','post|options');
            //菜品详情
            Route::rule('dishDetail','orders.Goods/dishDetail','post|options');
        });
        //点单
        Route::group(['name' => 'pointList'],function (){
            //获取扫码点台 trid
            Route::rule('getTableRevenueInfo','orders.PointList/getTableRevenueInfo','post|options');
            //用户点单
            Route::rule('createPointList','orders.PointList/createPointList','post|options');
            //用户手动取消未支付订单
            Route::rule('cancelDishOrder','orders.PointList/cancelDishOrder','post|options');
            //我的订单列表支付,更改支付方式
            Route::rule('changePayType','orders.DishOrderPay/changePayType','post|options');
            //获取支付方式
            Route::rule('getPayMethod','orders.PointList/getPayMethod','get|options');
        });


        //二维码
        Route::group(['name' => 'qrCode'],function (){
            //使用
            Route::rule('useQrCode','main.QrCodeAction/useQrCode','post|options');
        });

    });


});