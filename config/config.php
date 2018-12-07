<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午4:37
 */
return [
    // 是否开启路由
    'url_route_on'           => true,
    // 路由使用完整匹配
    'route_complete_match'   => false,
    // 路由配置文件（支持配置多个）
    'route_config_file'      => ['route' , 'admin_route' , 'member_xcx_route', 'manage_xcx_route' , 'reception_route' , 'h_five_route'],
    // 是否强制使用路由
    'url_route_must'         => true,

    // 默认输出类型
    'default_return_type'    => 'json',

    //页大
    'page_size'              => 20,

    'xcx_page_size'          => 5,

    'default_password'       => '000000',
];