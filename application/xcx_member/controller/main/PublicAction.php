<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午4:58
 */

namespace app\xcx_member\controller\main;


use app\common\controller\BaseController;
use app\common\model\ManageSalesman;
use app\common\model\MstTableAppearance;
use app\common\model\MstTableArea;
use app\common\model\MstTableLocation;
use app\common\model\MstTableReserveDate;
use app\common\model\MstTableSize;
use app\common\model\User;
use think\Db;
use think\Exception;
use think\Validate;

class PublicAction extends BaseController
{
    /**
     * 获取预约时,温馨提示信息
     * @return array
     */
    public function getReserveWaringInfo()
    {
        $date = $this->request->param("date","");
        if (empty($date)){
            return $this->com_return(false,\config("params.PARAM_NOT_EMPTY"));
        }

        try {
            $tsDate = Db::name("mst_table_reserve_date")
                ->where("appointment",$date)
                ->find();
            if (!empty($tsDate)){
                $is_refund_sub = $tsDate['is_refund_sub'];
                //0退1不退
                if ($is_refund_sub){
                    $info = getSysSetting("reserve_warning_no");
                }else{
                    $info = getSysSetting("reserve_warning");
                }
                return $this->com_return(true,\config("params.SUCCESS"),$info);
            }
            //获取当前退款和不退款的设置信息
            $reserve_refund_flag = getSysSetting("reserve_refund_flag");

            if ($reserve_refund_flag){
                $info = getSysSetting("reserve_warning_no");
            }else{
                $info = getSysSetting("reserve_warning");
            }
            return $this->com_return(true,\config("params.SUCCESS"),$info);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 检测手机号码是否存在
     * @return array
     */
    public function checkPhoneExist()
    {
        $type  = $this->request->param("type","");
        $phone = $this->request->param("phone","");

        $rule = [
            "type|类型"      => "require",
            "phone|电话号码"  => "regex:1[0-9]{1}[0-9]{9}|number",
        ];
        $request_res = [
            "type"  => $type,
            "phone" => $phone,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $info = "";
            if (empty($phone)){
                return $this->com_return(true,\config("params.SUCCESS"));
            }
            if ($type == "salesman"){
                $manageModel = new ManageSalesman();
                $info = $manageModel
                    ->where("phone",$phone)
                    ->where("statue",\config("salesman.salesman_status")['working']['key'])
                    ->field("sales_name name")
                    ->find();
                $info = json_decode(json_encode($info),true);
            }
            if ($type == "user"){
                $userModel = new User();
                $info = $userModel
                    ->where("phone",$phone)
                    ->field("name")
                    ->find();
                $info = json_decode(json_encode($info),true);
            }
            if (!empty($info)){
                $name = $info['name'];
                return $this->com_return(true,\config("params.SUCCESS"),$name);
            }else{
                return $this->com_return(false,\config("params.SALESMAN_NOT_EXIST"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 筛选条件获取
     * @return array
     */
    public function reserveCondition()
    {
        $phone = $this->request->param("phone","");//manage,client
        $rule = [
            "phone|电话号码" => "regex:1[0-9]{1}[0-9]{9}",
        ];
        $request_res = [
            "phone" => $phone,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            /*获取用户是否是会员 on*/
            $is_vip = 0;
            if (!empty($phone)){
                $userModel = new User();
                $userInfo = $userModel
                    ->where("phone",$phone)
                    ->field("user_status")
                    ->find();
                $userInfo = json_decode(json_encode($userInfo),true);
                if (!empty($userInfo)){
                    $user_status = $userInfo['user_status'];
                    if ($user_status == \config("user.user_status")['2']['key']){
                        //如果是已开卡
                        $is_vip = 1;
                    }
                }
            }
            /*获取用户是否是会员 off*/

            /*获取位置和小区选项 on*/
            $tableLocationModel = new MstTableLocation();
            $table_location = $tableLocationModel
                ->where("is_delete",0)
                ->order("sort")
                ->field('location_id,location_title,location_desc')
                ->select();
            $table_location = json_decode(json_encode($table_location),true);

            $tableAreaModel = new MstTableArea();
            for ($i = 0; $i <count($table_location); $i++){
                $location_id = $table_location[$i]['location_id'];
                $table_area_info = $tableAreaModel
                    ->where("location_id",$location_id)
                    ->where("is_enable",1)
                    ->where("is_delete",0)
                    ->field("area_id,area_title,area_desc")
                    ->order("sort")
                    ->select();
                $table_area_info = json_decode(json_encode($table_area_info),true);
                $table_location[$i]["area_group"] = $table_area_info;
            }
            /*获取位置和小区选项 off*/


            $tableSizeModel = new MstTableSize();
            //获取容量选项
            $table_size = $tableSizeModel
                ->where('is_delete',0)
                ->order("sort")
                ->field('size_id,size_title,size_desc')
                ->select();
            $table_size = json_decode(json_encode($table_size),true);

            //获取品项选项
            $tableAppearanceModel = new MstTableAppearance();
            $table_appearance = $tableAppearanceModel
                ->where("is_delete",0)
                ->order("sort")
                ->field('appearance_id,appearance_title,appearance_desc')
                ->select();
            $table_appearance = json_decode(json_encode($table_appearance),true);

            //获取日期选项
            $reserve_before_day = getSysSetting("reserve_before_day");
            $sys_date_select = [];
            if ($reserve_before_day){
                $sys_date_select = $this->dateTimeGetWeek($reserve_before_day,$sys_date_select);
                $sys_date_select = $this->dateTimeGetStr($sys_date_select);
            }

            $todayStartTime   = strtotime(date("Ymd",time())) - 1;
            $reserveDateModel = new MstTableReserveDate();
            $table_reserve_date = $reserveDateModel
                ->where("is_enable","1")
                ->where("is_revenue","1")
                ->where("appointment","> time",$todayStartTime)
                ->field("appointment,type,desc")
                ->select();
            $table_reserve_date = json_decode(json_encode($table_reserve_date),true);

           /* if (!empty($table_reserve_date)){
                $count = count($table_reserve_date);
                $table_reserve_date = $this->dateTimeGetWeek2($count,$table_reserve_date);
            }*/

            $date_select = mergeById($sys_date_select,$table_reserve_date);

            $date_select = array_values($date_select);

            //会员获取可预约时间选项
            $reserve_time_frame_vip     = getSysSetting("reserve_time_frame");
            $reserve_time_frame_vip_arr = explode("|",$reserve_time_frame_vip);
            $vip_time_arr               = timeToPart($reserve_time_frame_vip_arr[0],$reserve_time_frame_vip_arr[1]);
            $reserve_time_frame_nor     = getSysSetting("reserve_time_frame_normal");
            $reserve_time_frame_nor_arr = explode("|",$reserve_time_frame_nor);
            $nor_time_arr               = timeToPart($reserve_time_frame_nor_arr[0],$reserve_time_frame_nor_arr[1]);

            if ($is_vip){
                for ($i = 0; $i < count($vip_time_arr); $i++){
                    $vip_time_arr[$i]['is_show_color'] = 0;
                }
            }else{
                $vip_length = count($vip_time_arr);
                $nor_length = count($nor_time_arr);
                for ($m = 0; $m < $nor_length; $m ++){
                    $vip_time_arr[$m]['is_show_color'] = 0;
                }
                for ($n = $nor_length;$n < $vip_length; $n++){
                    $vip_time_arr[$n]['is_show_color'] = 1;
                }
            }

            $time_arr = $vip_time_arr;

            $res['table_location']   = $table_location;
            $res['table_size']       = $table_size;
            $res['table_appearance'] = $table_appearance;
            $res['date_select']      = $date_select;
            $res['time_select']      = $time_arr;

            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    public function dateTimeGetWeek($count,$date_res)
    {
        $now_time = time();
        //计算出指定日期的天数
        $today  = strtotime(date("Ymd",$now_time));
        $date_s = 24 * 60 * 60;
        for ($i = 0; $i < ($count); $i++){
            $date_time = $today + $date_s * $i;
            $weekday   = ["星期日","星期一","星期二","星期三","星期四","星期五","星期六",];
            $week      = $weekday[date("w",$date_time)];
            $date_res[$i]["appointment"] = $date_time;
            $date_res[$i]["desc"] = $week;
        }
        return $date_res;
    }

    public function dateTimeGetWeek2($count,$date_res)
    {
        //计算出指定日期的天数
        for ($i = 0; $i < ($count); $i++){
            $date_time = $date_res[$i]['appointment'];
            $weekday   = ["星期日","星期一","星期二","星期三","星期四","星期五","星期六",];
            $week      = $weekday[date("w",$date_time)];
            $date_res[$i]["week"] = $week;
        }
        return $date_res;
    }

    public function dateTimeGetStr($dateArr)
    {
        $todayStart = strtotime(date("Ymd",time()));
        $todayEnd   = $todayStart + (24 * 60 * 60 - 1);
        $tomorrowStart = $todayEnd + 1;
        $tomorrowEnd = $todayEnd + 1 + (24 * 60 * 60 - 1);
        for ($i = 0; $i < count($dateArr); $i ++) {
            if ($dateArr[$i]['appointment'] >= $todayStart && $dateArr[$i]['appointment'] <= $todayEnd) {
                $dateArr[$i]['desc'] = "今天";
            }elseif ($dateArr[$i]['appointment'] >= $tomorrowStart && $dateArr[$i]['appointment'] <= $tomorrowEnd){
                $dateArr[$i]['desc'] = "明天";
            }
            $dateArr[$i]['type'] = 0;
        }

        return $dateArr;
    }
}