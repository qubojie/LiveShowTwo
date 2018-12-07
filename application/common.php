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
 * 加密token
 * @param $str
 * @param string $prefix
 * @return string
 */
function jmToken($str , $prefix = 'QBJ')
{
    return md5(sha1($prefix.$str).time());
}

/**
 * 加密密码
 * @param $password
 * @param string $prefix
 * @return string
 */
function jmPassword($password , $prefix = "QBJ")
{
//    return sha1(md5($prefix.$password));
    return sha1($password);
}

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
 * 获取默认设置
 * @param $key
 * @return mixed
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function getSysSetting($key)
{
    $sysSettingModel = new \app\common\model\SysSetting();

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
    $userModel = new \app\common\model\User();

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
 * @return ints
 */
function getUserNewLevelId($point)
{
    try {
        $userLevelModel = new \app\common\model\MstUserLevel();

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
    } catch (Exception $e) {
        return 0;
    }
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

/**
 * 生成唯一字符串 最长32位
 * @param int $length
 * @return bool|string
 */
function uniqueCode($length = 8)
{
    if ($length > 32) $length = 32;

    $charid = strtoupper(md5(uniqid(rand(),true)));
    $hyphen = chr(45);// "-"
    $uuid = chr(123)// "{"
        .substr($charid, 0, 8).$hyphen
        .substr($charid, 8, 4).$hyphen
        .substr($charid,12, 4).$hyphen
        .substr($charid,16, 4).$hyphen
        .substr($charid,20,12)
        .chr(125);// "}"
    $code = $uuid;
//        return strtolower(substr(str_replace('-', '', $code), 4, 8));
    return (substr(str_replace('-', '', $code), 1, $length));
}

/**
 * 生成无限极分类树
 * @param $arr '数据数组结构'
 * @param $key_id '主键id的key'
 * @param $parent_id '区分层级关系的 Key名'
 * @return array
 */
function make_tree($arr,$key_id,$parent_id)
{
    $refer = array();
    $tree  = array();

    foreach($arr as $k => $v){
        $refer[$v[$key_id]] = & $arr[$k]; //创建主键的数组引用
    }

    foreach($arr as $k => $v){
        $pid = $v[$parent_id];  //获取当前分类的父级id
        if($pid == 0){
            $arr[$k]['parent_id'] = (string)$arr[$k]['parent_id'];
            $arr[$k]['children'] = [];
            $tree[] = & $arr[$k];  //顶级栏目

        }else{
            if(isset($refer[$pid])){
                $arr[$k]['parent_id'] = (string)$arr[$k]['parent_id'];
                $refer[$pid]['children'][] = & $arr[$k]; //如果存在父级栏目，则添加进父级栏目的子栏目数组中
            }
        }
    }
    return $tree;
}

/*
 * 备用
 * */
function make_tree2($arr,$key_id,$parent_id)
{
    $refer = array();
    $tree = array();

    foreach($arr as $k => $v){
        $refer[$v[$key_id]] = & $arr[$k]; //创建主键的数组引用
    }

    foreach($arr as $k => $v){
        $pid = $v[$parent_id];  //获取当前分类的父级id
        if($pid == 0){
            //dump($arr[$k]);die;
            $tree[] = & $arr[$k];  //顶级栏目

        }else{
            if(isset($refer[$pid])){
                $refer[$pid]['children'][] = & $arr[$k]; //如果存在父级栏目，则添加进父级栏目的子栏目数组中
            }
        }
    }
    return $tree;
}

/**
 * UUID不重复号,指定前缀
 * @param null $prefix
 * @return string
 */
function generateReadableUUID($prefix = null)
{
    mt_srand((double)microtime() * 10000);
    $charId = strtoupper(md5(uniqid(rand() , true)));
    $hyphen = chr(45);//"-"
    $uuid = chr(123)//"{"
        .substr($charId,0,8).$hyphen
        .substr($charId,8,4).$hyphen
        .substr($charId,12,4).$hyphen
        .substr($charId,16,4).$hyphen
        .substr($charId,20,12)
        .chr(125);//"}"

    $getUUID = strtoupper(str_replace("-","",$uuid));
    $generateReadableUUID = $prefix . date("ymdHis") . sprintf('%03d' , rand(0 , 999)) . substr($getUUID , 4 , 4);
    return $generateReadableUUID;
}

/**
 * 移除数组中指定Key的元素
 * @param $arr
 * @param $key
 * @return mixed
 */
function bykey_reitem($arr,$key)
{
    if (!array_key_exists($key,$arr)){
        return $arr;
    }
    $keys = array_keys($arr);
    $index = array_search($key,$keys);
    if ($index !== false){
        array_splice($arr,$index,1);
    }
    return $arr;
}

/**
 * 将手机号码中间四位替换为 *
 * @param $tel
 * @return mixed
 */
function jmTel($tel)
{
    $xing = substr($tel,3,4);  //获取手机号中间四位
    $return = str_replace($xing,'****',$tel);  //用****进行替换
    return $return;
}

/**
 * 把指定时间段切份 - N份
 * -----------------------------------
 * @param string $start 开始时间
 * @param string $end 结束时间
 * @param int $menus 分钟数

 * @param boolean 是否格式化

 * @return array 时间段数组

 */
function timeToPart($start,$end,$menus = 15, $format=true)
{
    $start = strtotime($start);
    $end   = strtotime($end);

    $nums = $menus * 60;

    $parts = ($end - $start)/$nums;
    $last  = ($end - $start)%$nums;

    if ( $last > 0) {
        $parts = ($end - $start - $last)/$nums;
    }

    for ($i=1; $i <= $parts+1; $i++) {
        $_end  = $start + $nums * $i;
        $arr[] = array($start + $nums * ($i-1), $_end);
    }

    $len = count($arr)-1;
    $arr[$len][1] = $arr[$len][1] + $last;
    if ($format) {
        foreach ($arr as $key => $value) {
            $arr[$key]['time'] = date("H:i", $value[0]);
//                $arr[$key][0] = date("H:i", $value[0]);
//                $arr[$key][1] = date("H:i", $value[1]);
            unset($arr[$key][0]);
            unset($arr[$key][1]);
        }
    }
    return $arr;


}

/**
 * 名字字典排序
 * @param $s
 * @return bool|string
 */
function getFirstChar($s)
{
    $s0 = mb_substr($s,0,3); //获取名字的姓
    $s = iconv('UTF-8','gb2312', $s0); //将UTF-8转换成GB2312编码

    if (ord($s0)>128) { //汉字开头，汉字没有以U、V开头的
        $asc=ord($s{0})*256+ord($s{1})-65536;
        if($asc>=-20319 and $asc<=-20284)return "A";
        if($asc>=-20283 and $asc<=-19776)return "B";
        if($asc>=-19775 and $asc<=-19219)return "C";
        if($asc>=-19218 and $asc<=-18711)return "D";
        if($asc>=-18710 and $asc<=-18527)return "E";
        if($asc>=-18526 and $asc<=-18240)return "F";
        if($asc>=-18239 and $asc<=-17760)return "G";
        if($asc>=-17759 and $asc<=-17248)return "H";
        if($asc>=-17247 and $asc<=-17418)return "I";
        if($asc>=-17417 and $asc<=-16475)return "J";
        if($asc>=-16474 and $asc<=-16213)return "K";
        if($asc>=-16212 and $asc<=-15641)return "L";
        if($asc>=-15640 and $asc<=-15166)return "M";
        if($asc>=-15165 and $asc<=-14923)return "N";
        if($asc>=-14922 and $asc<=-14915)return "O";
        if($asc>=-14914 and $asc<=-14631)return "P";
        if($asc>=-14630 and $asc<=-14150)return "Q";
        if($asc>=-14149 and $asc<=-14091)return "R";
        if($asc>=-14090 and $asc<=-13319)return "S";
        if($asc>=-13318 and $asc<=-12839)return "T";
        if($asc>=-12838 and $asc<=-12557)return "W";
        if($asc>=-12556 and $asc<=-11848)return "X";
        if($asc>=-11847 and $asc<=-11056)return "Y";
        if($asc>=-11055 and $asc<=-10247)return "Z";
    }else if(ord($s)>=48 and ord($s)<=57){ //数字开头
        /*switch(iconv_substr($s,0,1,'utf-8')){
            case 1:return "Y";
            case 2:return "E";
            case 3:return "S";
            case 4:return "S";
            case 5:return "W";
            case 6:return "L";
            case 7:return "Q";
            case 8:return "B";
            case 9:return "J";
            case 0:return "L";
        }*/

        return "ZZ";
    }else if(ord($s)>=65 and ord($s)<=90){ //大写英文开头
        return substr($s,0,1);
    }else if(ord($s)>=97 and ord($s)<=122){ //小写英文开头
        return strtoupper(substr($s,0,1));
    }
    else
    {
        return iconv_substr($s0,0,1,'utf-8');
        //中英混合的词语，不适合上面的各种情况，因此直接提取首个字符即可
    }
}

/**
 * 合并二维数组
 * @param $sys_date_select
 * @param $table_reserve_date
 * @return array
 */
function mergeById(&$sys_date_select,&$table_reserve_date){
    $c=array();
    foreach($sys_date_select as $e)	$c[$e['appointment']]=isset($c[$e['appointment']])? $c[$e['appointment']]+$e : $e;
    foreach($table_reserve_date as $e)	$c[$e['appointment']]=$e;
    return $c;
}

