<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午5:14
 */
use think\Route;

Route::group(['name' => 'admin' , 'prefix' => 'admin/'],function (){
    //应用内容管理
    Route::group(['name' => 'appContent'],function (){
        //首页文章管理
        Route::group(['name' => 'HomeArticles'],function (){
            //文章列表
            Route::rule('index','content.HomeArticles/index','post/options');
            //文章添加
            Route::rule('add','content.HomeArticles/add','post/options');
            //文章编辑
            Route::rule('edit','content.HomeArticles/edit','post/options');
            //置顶OR取消置顶
            Route::rule('is_top',"content.HomeArticles/is_top",'post/options');
            //显示OR不显示
            Route::rule('is_show',"content.HomeArticles/is_show",'post/options');
            //排序编辑
            Route::rule('sortEdit',"content.HomeArticles/sortEdit",'post/options');
            //文章删除
            Route::rule('delete','content.HomeArticles/delete','post/options');
        });
        //首页Banner管理
        Route::group(['name' => 'HomeBanner'],function (){
            //Banner列表
            Route::rule('index','content.HomeBanner/index','post/options');
            //Banner添加
            Route::rule('add','content.HomeBanner/add','post/options');
            //Banner编辑
            Route::rule('edit','content.HomeBanner/edit','post/options');
            //Banner是否显示
            Route::rule('isShow',"content.HomeBanner/isShow",'post/options');
            //Banner排序编辑
            Route::rule('sortEdit',"content.HomeBanner/sortEdit",'post/options');
            //Banner删除
            Route::rule('delete','content.HomeBanner/delete','post/options');
        });
    });

    //联盟商家管理
    Route::group(['name' => 'merchantUnion'],function (){
        //联盟商家信息设置
        Route::group(['name' => 'merchant'],function (){
            //联盟商家列表
            Route::rule("index",'business.Merchant/index','post/options');
            //联盟商家添加
            Route::rule("add",'business.Merchant/add','post/options');
            //联盟商家编辑提交
            Route::rule("edit",'business.Merchant/edit','post/options');
            //联盟商家删除
            Route::rule("delete",'business.Merchant/delete','post/options');
            //联盟商家删除排序
            Route::rule("sortEdit",'business.Merchant/sortEdit','post/options');
            //联盟商家是否启用
            Route::rule("enable",'business.Merchant/enable','post/options');
        });
        //联盟商家分类
        Route::group(['name' => 'merchantCategory'],function (){
            //联盟商家分类列表无分页
            Route::rule("merchantType",'business.MerchantCategory/merchantType','post/options');
            //联盟商家分类列表
            Route::rule("index",'business.MerchantCategory/index','post/options');
            //联盟商家分类添加
            Route::rule("add",'business.MerchantCategory/add','post/options');
            //联盟商家分类编辑
            Route::rule("edit",'business.MerchantCategory/edit','post/options');
            //联盟商家分类删除
            Route::rule("delete",'business.MerchantCategory/delete','post/options');
            //联盟商家分类排序
            Route::rule("sortEdit",'business.MerchantCategory/sortEdit','post/options');
            //联盟商家分类是否启用
            Route::rule("enable",'business.MerchantCategory/enable','post/options');
            //分类列表 ———— 键值对
            Route::rule("cateList",'business.MerchantCategory/cateList','post/options');
        });
    });

    Route::group(['name' => 'card'],function (){
        //会籍及储蓄卡设置
        Route::group(['name' => 'cardSetting'],function (){
            //获取卡类型
            Route::rule('type','card.Card/type','post/options');
            //获取推荐人类型
            Route::rule('recommendUserType','card.Card/getRecommendUserType','post/options');
            //VIP会员卡信息列表
            Route::rule('index','card.Card/index','post/options');
            //VIP会员卡信息添加
            Route::rule('add','card.Card/add','post/options');
            //Vip会员卡信息编辑
            Route::rule('edit','card.Card/edit','post/options');
            //Vip会员卡信息删除
            Route::rule('delete','card.Card/delete','post/options');
            //Vip会员卡是否启用
            Route::rule('enable','card.Card/enable','post/options');
            //Vip会员卡排序
            Route::rule('sortEdit','card.Card/sortEdit','post/options');
            //Vip会员卡新增赠品
            Route::rule('addGiftOrVoucher','card.Card/addGiftOrVoucher','post/options');
            //Vip会员卡赠品删除
            Route::rule('deleteGiftOrVoucher','card.Card/deleteGiftOrVoucher','post/options');
        });
        //卡种设置
        Route::group(['name' => 'cardType'],function (){
            //获取卡类型列表
            Route::rule('index','card.CardType/index','post/options');
            //卡种添加
            Route::rule('add','card.CardType/add','post/options');
            //卡种编辑
            Route::rule('edit','card.CardType/edit','post/options');
            //卡种删除
            Route::rule('delete','card.CardType/delete','post/options');
            //卡种激活
            Route::rule('enable','card.CardType/enable','post/options');
        });
    });

    //会员管理
    Route::group(['name' => 'member'],function (){
        //会员信息管理
        Route::group(['name' => 'user'],function(){
            //会员状态类型
            Route::rule('userStatus','member.UserInfo/userStatus','post/options');
            //获取卡种
            Route::rule('cardType','member.UserInfo/cardType','post/options');
            //会员列表
            Route::rule('index','member.UserInfo/index','post/options');
            //会员编辑
            Route::rule('edit','member.UserInfo/edit','post/options');
            //禁止登陆或解禁
            Route::rule('noLogin','member.UserInfo/noLogin','post/options');
            //后台操作变更会员密码
            Route::rule('changePass','member.UserInfo/changePass','post/options');
            //钱包明细
            Route::rule('accountInfo','member.UserInfo/accountInfo','post/options');
            //押金明细
            Route::rule('depositInfo','member.UserInfo/depositInfo','post/options');
            //礼金明细
            Route::rule('cashGiftInfo','member.UserInfo/cashGiftInfo','post/options');
            //积分明细
            Route::rule('accountPointInfo','member.UserInfo/accountPointInfo','post/options');
        });
        //会员等级设置
        Route::group(['name' => 'level'],function (){
            //等级列表
            Route::rule('index','member.UserLevel/index','post/options');
            //等级添加
            Route::rule('add','member.UserLevel/add','post/options');
            //等级编辑
            Route::rule('edit','member.UserLevel/edit','post/options');
            //等级删除
            Route::rule('delete','member.UserLevel/delete','post/options');
        });
        //开卡赠卷设置
        Route::group(['name' => 'voucher'],function (){
            //礼券列表
            Route::rule('index','member.Voucher/index','post/options');
            //礼券添加
            Route::rule('add','member.Voucher/add','post/options');
            //礼券编辑
            Route::rule('edit','member.Voucher/edit','post/options');
            //礼券删除
            Route::rule('delete','member.Voucher/delete','post/options');
            //礼券启用
            Route::rule('enable','member.Voucher/enable','post/options');
            //指定会员 - 检索用户信息
            Route::rule('retrievalUserInfo','member.Voucher/retrievalUserInfo','post/options');
            //指定会员卡 - 获取卡列表
            Route::rule('getCardInfoNum','member.Voucher/getCardInfoNum','post/options');
            //礼券发放
            Route::rule('grantVoucher','member.Voucher/grantVoucher','post/options');
            //生成二维码
            Route::rule('makeQrCode','member.Voucher/makeQrCode','post/options');
        });
        //开卡订单管理
        Route::group(['name' => 'openCardOrder'],function (){
            //开卡订单分组
            Route::rule('orderType','member.OpenCardOrder/orderType','post/options');
            //开卡礼寄送分组
            Route::rule('giftShipType','member.OpenCardOrder/giftShipType','post/options');
            //订单列表
            Route::rule('index','member.OpenCardOrder/index','post/options');
            //发货操作
            Route::rule('ship','member.OpenCardOrder/ship','post/options');
            //付款操作
            Route::rule('adminPay','member.OpenCardOrder/adminPay','post/options');
            //快递公司列表
            Route::rule('getLogisticsCompany','member.OpenCardOrder/getLogisticsCompany','post/options');
        });
        //充值金额设置
        Route::group(['name' => 'refillAmount'],function (){
            //充值金额列表
            Route::rule('index','member.RefillAmount/index','post|options');
            //充值金额添加
            Route::rule('add','member.RefillAmount/add','post/options');
            //充值金额编辑
            Route::rule('edit','member.RefillAmount/edit','post/options');
            //充值金额删除
            Route::rule('delete','member.RefillAmount/delete','post/options');
            //充值金额是否启用
            Route::rule('enable','member.RefillAmount/enable','post/options');
            //充值金额排序
            Route::rule('sortEdit','member.RefillAmount/sortEdit','post/options');
        });

    });

    //组织结构
    Route::group(['name' => 'sales'],function (){
        //营销人员管理
        Route::group(['name' => 'salesUser'],function (){
            //人员状态分组
            Route::rule("salesmanStatus",'structure.SalesUser/salesmanStatus','post/options');
            //营销人员列表
            Route::rule("index",'structure.SalesUser/index','post/options');
            //营销人员添加
            Route::rule("add",'structure.SalesUser/add','post/options');
            //营销人员编辑
            Route::rule("edit",'structure.SalesUser/edit','post/options');
            //营销人员删除
            Route::rule("changeStatus",'structure.SalesUser/changeStatus','post/options');
            //人员状态操作 销售员状态 0入职待审核  1销售中  2停职  9离职
            Route::rule("changeStatus",'structure.SalesUser/changeStatus','post/options');
        });
        //营销人员类型设置
        Route::group(['name' => 'salesType'],function (){
            //营销人员类型列表
            Route::rule("index",'structure.SalesType/index','post/options');
            //营销人员类型添加
            Route::rule("add",'structure.SalesType/add','post/options');
            //营销人员类型编辑
            Route::rule("edit",'structure.SalesType/edit','post/options');
            //营销人员类型删除
            Route::rule("delete",'structure.SalesType/delete','post/options');
        });
        //会籍部门设置
        Route::group(['name' => 'department'],function (){
            //会籍部门列表
            Route::rule("index",'structure.Department/index','post/options');
            //会籍部门添加
            Route::rule("add",'structure.Department/add','post/options');
            //会籍部门编辑
            Route::rule("edit",'structure.Department/edit','post/options');
            //会籍部门删除
            Route::rule("delete",'structure.Department/delete','post/options');
        });
    });

    //酒桌管理
    Route::group(['name' => 'barCounter'],function (){
        //台位信息管理
        Route::group(['name' => 'tableInfo'],function (){
            //位置类型列表
            Route::rule("tableLocation",'table.TableInfo/tableLocation','post/options');
            //台位列表
            Route::rule("index",'table.TableInfo/index','post/options');
            //台位添加
            Route::rule("add",'table.TableInfo/add','post/options');
            //台位编辑
            Route::rule("edit",'table.TableInfo/edit','post/options');
            //台位删除
            Route::rule("delete",'table.TableInfo/delete','post/options');
            //是否启用
            Route::rule("enable",'table.TableInfo/enable','post/options');
            //台位排序
            Route::rule("sortEdit",'table.TableInfo/sortEdit','post/options');
            //桌位二维码打包下载
            Route::rule("zipTableQrCode",'table.DownloadTableQrCode/zipTableQrCode');
        });
        //节日设置
        Route::group(['name' => 'specialDate'],function (){
            //特殊日期列表
            Route::rule("index",'table.TableSpecialDate/index','post/options');
            //特殊日期添加
            Route::rule("add",'table.TableSpecialDate/add','post/options');
            //特殊日期编辑
            Route::rule("edit",'table.TableSpecialDate/edit','post/options');
            //特殊日期删除
            Route::rule("delete",'table.TableSpecialDate/delete','post/options');
            //是否启用,是否允许预定,是否可退押金
            Route::rule("statusAction",'table.TableSpecialDate/enableOrRevenueOrRefund','post/options');
            //是否启用
            Route::rule("enable",'table.TableSpecialDate/enable','post/options');
            //是否启用预约
            Route::rule("revenue",'table.TableSpecialDate/revenue','post/options');
        });
        //大区设置
        Route::group(['name' => 'tablePosition'],function (){
            //位置列表
            Route::rule("index",'table.TablePosition/index','post/options');
            //位置添加
            Route::rule("add",'table.TablePosition/add','post/options');
            //位置编辑
            Route::rule("edit",'table.TablePosition/edit','post/options');
            //位置删除
            Route::rule("delete",'table.TablePosition/delete','post/options');
            //排序编辑
            Route::rule("sortEdit",'table.TablePosition/sortEdit','post/options');
        });
        //小区设置
        Route::group(['name' => 'tableArea'],function (){
            //获取负责人信息列表
            Route::rule("getGovernorSalesman",'table.TableArea/getGovernorSalesman','post/options');
            //获取所有的有效卡种
            Route::rule("getCardInfo",'table.TableArea/getCardInfo','post/options');
            //区域列表
            Route::rule("index",'table.TableArea/index','post/options');
            //区域添加
            Route::rule("add",'table.TableArea/add','post/options');
            //区域编辑
            Route::rule("edit",'table.TableArea/edit','post/options');
            //区域删除
            Route::rule("delete",'table.TableArea/delete','post/options');
            //是否启用
            Route::rule("enable",'table.TableArea/enable','post/options');
        });
        //酒桌品项设置
        Route::group(['name' => 'tableAppearance'],function (){
            //品项列表
            Route::rule("index",'table.TableAppearance/index','post/options');
            //品项添加
            Route::rule("add",'table.TableAppearance/add','post/options');
            //品项编辑
            Route::rule("edit",'table.TableAppearance/edit','post/options');
            //品项删除
            Route::rule("delete",'table.TableAppearance/delete','post/options');
            //排序编辑
            Route::rule("sortEdit",'table.TableAppearance/sortEdit','post/options');
        });
        //人数设置
        Route::group(['name' => 'tableSize'],function (){
            //容量列表
            Route::rule("index",'table.TableSize/index','post/options');
            //容量添加
            Route::rule("add",'table.TableSize/add','post/options');
            //容量编辑
            Route::rule("edit",'table.TableSize/edit','post/options');
            //容量删除
            Route::rule("delete",'table.TableSize/delete','post/options');
            //排序编辑
            Route::rule("sortEdit",'table.TableSize/sortEdit','post/options');
        });
    });

    //菜品商品管理
    Route::group(['name' => 'dishesGoods'],function (){
        //菜品信息
        Route::group(['name' => 'dish'],function (){
            //打印机测试
            Route::rule("test",'dishes.Dish/test','post/options');
            //菜品类型
            Route::rule("dishType",'dishes.Dish/dishType','post/options');
            //菜品列表
            Route::rule("index",'dishes.Dish/index','post/options');
            //菜品添加
            Route::rule("add",'dishes.Dish/add','post/options');
            //主菜品编辑提交
            Route::rule("edit",'dishes.Dish/edit','post/options');
            //菜品套餐编辑提交
            Route::rule("combEdit",'dishes.Dish/combEdit','post/options');
            //菜品详情
            Route::rule("dishDetails",'dishes.Dish/dishDetails','post/options');
            //菜品删除
            Route::rule("delete",'dishes.Dish/delete','post/options');
            //菜品排序
            Route::rule("sortEdit",'dishes.Dish/sortEdit','post/options');
            //菜品是否启用
            Route::rule("enable",'dishes.Dish/enable','post/options');
        });
        //菜品分类设置
        Route::group(['name' => 'dishClassify'],function (){
            //菜品分类列表无分页
            Route::rule("dishType",'dishes.DishClassify/dishType','post/options');
            //菜品分类列表
            Route::rule("index",'dishes.DishClassify/index','post/options');
            //菜品分类添加
            Route::rule("add",'dishes.DishClassify/add','post/options');
            //菜品分类编辑
            Route::rule("edit",'dishes.DishClassify/edit','post/options');
            //菜品分类删除
            Route::rule("delete",'dishes.DishClassify/delete','post/options');
            //菜品分类排序
            Route::rule("sortEdit",'dishes.DishClassify/sortEdit','post/options');
            //菜品分类是否启用
            Route::rule("enable",'dishes.DishClassify/enable','post/options');
        });
        //菜品属性设置
        Route::group(['name' => 'dishAttribute'],function (){
            //菜品属性列表 无分页
            Route::rule("dishAttr",'dishes.DishAttribute/dishAttr','post/options');
            //菜品属性列表
            Route::rule("index",'dishes.DishAttribute/index','post/options');
        });
    });

    //统计报表
    Route::group(['name' => 'totalTable'],function (){
        //会籍卡充值收入统计
        Route::rule('userCardReport','statistics.TotalTable/userCardReport','get|options');
        //会员卡开卡充值统计
        Route::rule('userCardRechargeReport','statistics.TotalTable/userCardRechargeReport','get|options');
    });

    //财务管理
    Route::group(['name' => 'finance'],function (){
        //充值单据管理
        Route::group(['name' => 'refillOrder'],function (){
            //单据状态组获取
            Route::rule('orderStatus','finance.Recharge/orderStatus');
            //充值单据列表
            Route::rule('index','finance.Recharge/order','post|options');
            //新增充值信息
            Route::rule('addRecharge','finance.Recharge/addRechargeOrder','post|options');
            //充值收款操作
            Route::rule('receipt','finance.Recharge/receipt','post|options');
        });
    });

    //支付方式
    Route::group(['name' => 'payMethod'],function (){
        //支付方式列表
        Route::rule('index','whore.PayMethod/index','get|options');
        //支付方式添加
        Route::rule('add','whore.PayMethod/add','post|options');
        //支付方式编辑
        Route::rule('edit','whore.PayMethod/edit','post|options');
        //支付方式删除
        Route::rule('delete','whore.PayMethod/delete','post|options');
        //支付方式是否激活
        Route::rule('is_enable','whore.PayMethod/is_enable','post|options');

    });

    //系统设置
    Route::group(['name' => 'system'],function (){
        //管理员路由组
        Route::group(['name' => 'manager'],function (){
            //管理员列表
            Route::rule('index','system.AdminUser/index','post|options');
            //登陆管理员详细
            Route::rule("detail",'system.AdminUser/detail','post|options');
            //添加管理员
            Route::rule("create",'system.AdminUser/create','post|options');
            //编辑管理员
            Route::rule("edit",'system.AdminUser/edit','post|options');
            //删除管理员
            Route::rule("delete",'system.AdminUser/delete','post|options');
            //更改管理员密码
            Route::rule("changePass",'system.AdminUser/changeManagerPass','post|options');
            //修改自身信息
            Route::rule("changeManagerInfo","system.AdminUser/changeManagerInfo",'post|options');
        });
        //角色路由组
        Route::group(['name' => 'roles'],function (){
            //角色一览
            Route::rule('index','system.Roles/index','post|options');
            //角色添加
            Route::rule('add','system.Roles/add','post|options');
            //角色编辑
            Route::rule('edit','system.Roles/edit','post|options');
            //角色删除
            Route::rule('delete','system.Roles/delete','post|options');
        });
        //设置
        Route::group(['name' => 'setting'],function (){
            //设置类型列表
            Route::rule('lists','system.Setting/lists','post|options');
            //类型下详情获取
            Route::rule('get_info','system.Setting/get_info','post|options');
            //编辑系统设置
            Route::rule('edit','system.Setting/edit','post|options');
            //添加系统设置
            Route::rule('create','system.Setting/create','post|options');
        });
        //系统日志
        Route::group(['name' => 'sysLog'],function (){
            //系统日志列表
            Route::rule('sysLogList','system.SysLog/sysLogList');
        });

    });

    //素材库
    Route::group(['name' => 'material'],function (){
        //素材分类管理
        Route::group(['name' => 'sourceMaterialCategory'],function (){
            //素材分类列表
            Route::rule('index','material.SourceMaterialCategory/index','post/options');
            //素材分类添加
            Route::rule('add','material.SourceMaterialCategory/add','post/options');
            //素材分类删除
            Route::rule('delete','material.SourceMaterialCategory/delete','post/options');
            //素材分类编辑
            Route::rule('edit','material.SourceMaterialCategory/edit','post/options');
        });
        //素材管理
        Route::group(['name' => 'sourceMaterial'],function (){
            //素材列表
            Route::rule('index','material.SourceMaterial/index','post/options');
            //素材上传
            Route::rule('upload','material.SourceMaterial/upload','post/options');
            //素材删除
            Route::rule('delete','material.SourceMaterial/delete','post/options');
            //移动素材至新的分组
            Route::rule('moveMaterial','material.SourceMaterial/moveMaterial','post/options');
            //前端获取七牛上传token
            Route::rule('webGetQiNiuToken','material.SourceMaterial/webGetQiNiuToken','get/options');
            //图片存入素材库
            Route::rule('uploadImageUrl','material.SourceMaterial/uploadImageUrl','post/options');
        });

    });

    //统计
    Route::rule('count','whore.Count/index','get|post|options');
    //图片上传至本地
    Route::rule('imageUpload','whore.ImageUpload/uploadLocal');
    //登陆
    Route::rule('auth/login','whore.Auth/login','post|options');
    //退出登录,刷新token
    Route::rule('auth/refresh_token','whore.Auth/refresh_token','post|options');
    //后台导航栏菜单
    Route::rule('menus','whore.Menus/index','post|options');
    //后台导航栏所有列表
    Route::rule('menusLists','whore.Menus/lists','post|options');
    //菜单小红点
    Route::rule('menuRedDot','whore.Menus/menuRedDot','post|options');

});