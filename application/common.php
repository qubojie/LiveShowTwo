<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

/**
 * 生成验证码
 * @param int $length
 * @param int $numeric
 * @return string
 */
function getRandCode($length = 6 , $numeric = 0)
{
    PHP_VERSION < '4.2.0' && mt_srand((double)microtime() * 1000000);

    if ($numeric){
        $hash = sprintf('%0'.$length.'d',mt_rand(0,pow(10,$length) - 1));
    } else {
        $hash = '';
        $chars = '0123456789';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0,$max)];
        }
    }
    return $hash;
}


/**
 * 将数组中值为Int转换为string
 * @param $arr
 * @return mixed
 */
function arrIntToString($arr){
    foreach ($arr as $k => $v){
        if (is_numeric($v)){
            $arr[$k] = (string)$v;
        }
    }
    return $arr;
}

/**
 * 递归方式把数组或字符串 null转换为空''字符串
 * @param $arr
 * @return array|string
 */
function _unsetNull($arr){
    if ($arr !== null){
        if (is_array($arr)){
            if (!empty($arr)){
                foreach ($arr as $key => $val){
                    if ($val === null)  $arr[$key] = '';
                    else  $arr[$key] = _unsetNull($val);//递归,再去执行
                }
            }else $arr = '';

        }else if ($arr === null)  $arr = '';

    }else $arr = '';

    return $arr;
}

/**
 * 递归方式把数组或字符串 null转换为空 0
 * @param $arr
 * @return array|string
 */
function _unsetNull_to_o($arr){
    if ($arr !== null){
        if (is_array($arr)){
            if (!empty($arr)){
                foreach ($arr as $key => $val){
                    if ($val === null)  $arr[$key] = 0;
                    else  $arr[$key] = _unsetNull_to_o($val);//递归,再去执行
                }
            }else $arr = 0;

        }else if ($arr === null)  $arr = 0;

    }else $arr = 0;

    return $arr;
}


/**
 * 获取某个时间戳的周几，以及未来几天以后的周几
 * @param $time
 * @param int $i
 * @return mixed
 */
function getTimeWeek($time, $i = 0){
    $weekArray = ["7", "1", "2", "3", "4", "5", "6"];
    $oneD = 24 * 60 * 60;

    return $weekArray[date("w", $time + $oneD * $i)];
}


/**
 * 删除数组中指定的key
 * @param $arr
 * @param $keys '多个以逗号隔开'
 * @return mixed
 */
function array_remove($arr, $keys){
    $key_arr = explode(",",$keys);

    for ($i = 0; $i < count($key_arr); $i ++){
        $key = $key_arr[$i];
        if (!array_key_exists($key, $arr)) {
            return $arr;
        }
        $keys = array_keys($arr);
        $index = array_search($key, $keys);
        if ($index !== FALSE) {
            array_splice($arr, $index, 1);
        }
    }
    return $arr;
}

/**
 * 获取默认头像
 * @param $key
 * @return mixed
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function getSysSetting($key)
{
    $sysSettingModel = new \app\admin\model\SysSetting();

    $value_res = $sysSettingModel
        ->where('key',$key)
        ->field("value")
        ->find();

    $value_res = json_decode(json_encode($value_res),true);

    $value = $value_res['value'];

    return $value;

}

/**
 * 记录禁止登陆解禁登陆 单据操作等日志
 * @param string $uid           '被操作用户id'
 * @param string $gid           '被操作的的商品id'
 * @param string $oid           '相关单据id'
 * @param string $action        '操作内容'
 * @param string $reason        '操作原因描述'
 * @param string $action_user   '操作管理员id'
 * @param string $action_time   '操作时间'
 */
function addSysAdminLog($uid = '',$gid = '',$oid = '',$action = 'empty',$reason = '',$action_user = '',$action_time = '')
{
    $params  = [
        'uid'         => $uid,
        'gid'         => $gid,
        'oid'         => $oid,
        'action'      => $action,
        'reason'      => $reason,
        'action_user' => $action_user,
        'action_time' => $action_time,
    ];

    \think\Db::name('sys_adminaction_log')
        ->insert($params);
}


/**
 * 酒桌操作记录日志(预约,取消预约,转台,转拼,开拼,开台等操作)
 * @param $log_time
 * @param $type
 * @param $table_id
 * @param $table_no
 * @param $action_user
 * @param $desc
 * @param string $table_o_id
 * @param string $table_o_no
 * @return bool
 */

function insertTableActionLog($log_time,$type,$table_id,$table_no,$action_user,$desc,$table_o_id = "",$table_o_no = "")
{
    $params = [
        "log_time"     => $log_time,
        "type"         => $type,
        "table_id"     => $table_id,
        "table_no"     => $table_no,
        "action_user"  => $action_user,
        "desc"         => $desc,
        "table_o_id"   => $table_o_id,
        "table_o_no"   => $table_o_no,
    ];

    $is_ok = \think\Db::name("table_log")
        ->insert($params);

    if ($is_ok){
        return true;
    }else{
        return false;
    }

}

/**
 * 根据uid获取用户信息
 * @param $uid
 * @return array|false|PDOStatement|string|\think\Model
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function getUserInfo($uid)
{
    $userModel = new \app\admin\model\User();

    $column = $userModel->column;

    $user_info = $userModel
        ->where('uid',$uid)
        ->field($column)
        ->find();

    $user_info = json_decode(json_encode($user_info),true);

    return $user_info;
}

/**
 * 获取用户新积分等级
 * @param $point
 * @return int
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function getUserNewLevelId($point)
{
    $userLevelModel = new \app\admin\model\MstUserLevel();

    $list = $userLevelModel
        ->where("point_min <= $point AND point_max >= $point")
        ->find();

    $list = json_decode(json_encode($list),true);

    if (!empty($list)){
        $level_id = $list['level_id'];
    }else{
        $level_id = 0;
    }

    return $level_id;
}

/**
 * 将二维数组按指定相同key=>val的key分组
 * [array_group_by ph]
 * @param  [type] $arr [二维数组]
 * @param  [type] $key [键名]
 * @return [type]      [新的二维数组]
 */
function array_group_by($arr, $key){
    $grouped = array();
    foreach ($arr as $value) {
        $grouped[$value[$key]][] = $value;
    }

    if (func_num_args() > 2) {
        $args = func_get_args();
        foreach ($grouped as $key => $value) {
            $parms = array_merge($value, array_slice($args, 2, func_num_args()));
            $grouped[$key] = call_user_func_array('array_group_by', $parms);
        }
    }
    return $grouped;
}
