<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午5:16
 */
return [
    "params" => [
        "SUCCESS"              => "成功",
        "FAIL"                 => "失败",
        "NOT_HAVE_MORE"        => "暂无更多",
        "PASSWORD_DIF"         => "两次输入密码不一致",
        "ACCOUNT_PASSWORD_DIF" => "账号密码不匹配,请核对后重试",
        "ABNORMAL_ACTION"      => "服务器异常操作",
        "PURVIEW_SHORT"        => "权限不足",
        "ROLE_HAVE_ADMIN"      => "当前角色下存在管理员,不可删除",
        "POINT_POST_RETURN"    => "积分最大值不能小于积分最小值",
        'LEVEL_USER_EXIST'     => "该等级下存在会员,不可删除",
        'EXIST_SUBCLASS'       => "该部门下存在子部门,不可删除",
        'EXIST_MANAGE'         => "该部门下存在人员,不可删除",
        "DATE_IS_EXIST"        => "指定押金预定日期已存在",
        "AREA_IS_EXIST"        => "当前位置该区域名称已存在",
        "AREA_TALE_EXIST"      => "该区域下存在吧台,不可直接删除",
        "CARD_EXIST_NOT_D"     => "卡种下存在未删除的有效卡片,不可直接删除",

        //联盟商户
        'MERCHANT' => [
            "CLASS_EXIST_MERCHANT"=>  "当前分类下存在菜品,不可直接删除",
        ],

        "DISHES"                => [
            "CLASS_EXIST_DISHES"=>  "当前分类下存在菜品,不可直接删除",
            "ATTR_EXIST_DISHES" =>  "当前属性下存在菜品,不可直接删除",
            "CARD_PRICE_EMPTY"  =>  "vip价格不能为空",
            "COMBO_DIST_EMPTY"  =>  "套餐内的单品不能为空",
            "COMBO_ID_NOT_EMPTY"=>  "换品组内的单品不能为空",
            "LOW_ELIMINATION"   =>  "不满足最低消费,请核对订单"
        ],

        "TEMP"             => [
            "SC_DELETE_NO" => "该分类下存在素材，请先删除素材后再删除分类",
            "MOVE_FAIL"    => "素材移动失败,请稍后重试"
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 系统设置配置
    |--------------------------------------------------------------------------
    */
    'sys' => [
        'sys'     => "系统设置",
        'card'    => "会籍卡设置",
        'reserve' => "预约设置",
        'sms'     => "短信设置",
        'user'    => "用户设置",
    ],
];