<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 下午5:00
 */

namespace app\reception\controller\dining;


use app\common\controller\BusinessCommon;
use app\common\controller\ReceptionAuthAction;
use app\common\controller\ReservationCommon;
use app\common\controller\SalesUserCommon;
use app\common\controller\TableCommon;
use app\common\controller\UserCommon;
use app\common\model\MstTable;
use app\common\model\MstTableCard;
use app\common\model\TableBusiness;
use app\common\model\TableRevenue;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class DiningRoomTurnSpelling extends ReceptionAuthAction
{
    /**
     * 开拼
     * @param Request $request
     * @return array
     */
    public function openSpelling(Request $request)
    {
        $table_id    = $request->param("table_id","");//当前桌id
        $user_phone  = $request->param("user_phone","");//用户电话
        $user_name   = $request->param("user_name","");//用户姓名
        $sales_phone = $request->param("sales_phone","");//营销电话
        $rule = [
            "table_id|桌台"       => "require",
            "user_phone|客户电话"  => "regex:1[0-9]{1}[0-9]{9}",
            "sales_phone|营销电话" => "regex:1[0-9]{1}[0-9]{9}",
        ];
        $request_res = [
            "table_id"    => $table_id,
            "user_phone"  => $user_phone,
            "sales_phone" => $sales_phone,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $time = time();
            $reservationCommonObj = new ReservationCommon();
            $date_time_arr = $reservationCommonObj->getSysTimeLong(1);//今天
            $begin_time    = $date_time_arr['beginTime'];
            $end_time      = $date_time_arr['endTime'];
            $tableBusinessModel = new TableBusiness();
            //查看当前桌台是否是开台状态,只有开台状态的桌子才能被开拼
            $openTableInfo = $tableBusinessModel
                ->where('table_id',$table_id)
                ->where("status",config("order.table_business_status")['open']['key'])
                ->whereTime("open_time","between",[$begin_time,$end_time])
                ->find();
            $openTableInfo = json_decode(json_encode($openTableInfo),true);

            if (empty($openTableInfo)){
                //未开台,不可进行开拼操作
                return $this->com_return(false,config("params.REVENUE")['NO_OPEN_SPELLING']);
            }

            $turnover_limit = $openTableInfo['turnover_limit'];//低消

            /*营销信息 on*/
            $referrer_id   = config("salesman.salesman_type")['3']['key'];
            $referrer_type = config("salesman.salesman_type")['3']['key'];
            $referrer_name = config("salesman.salesman_type")['3']['name'];
            if (!empty($sales_phone)){
                $salesUserCommonObj = new SalesUserCommon();
                //获取营销信息
                $manageInfo = $salesUserCommonObj->phoneGetSalesmanInfo($sales_phone);
                if (!empty($manageInfo)){
                    $referrer_id   = $manageInfo["sid"];
                    $referrer_type = $manageInfo["stype_key"];
                    $referrer_name = $manageInfo["sales_name"];
                }
            }
            /*营销信息 off*/

            /*用户信息 on*/
            $uid = "";
            if (!empty($user_phone)){
                $userCommonObj = new UserCommon();
                //根据用户电话获取用户信息
                $userInfo = $userCommonObj->uidOrPhoneGetUserInfo($user_phone);
                if (empty($userInfo)){
                    //如果没有当前用户信息,则创建新用户
                    $uid = generateReadableUUID("U");
                    $user_params = [
                        "uid"           => $uid,
                        "phone"         => $user_phone,
                        "name"          => $user_name,
                        "avatar"        => getSysSetting("sys_default_avatar"),
                        "sex"           => config("user.default_sex"),
                        "password"      => jmPassword(config("DEFAULT_PASSWORD")),
                        "register_way"  => config("user.register_way")['web']['key'],
                        "user_status"   => config("user.user_register_status")['register']['key'],
                        "referrer_type" => $referrer_type,
                        "referrer_id"   => $referrer_id,
                        "created_at"    => $time,
                        "updated_at"    => $time

                    ];
                    //插入新的用户信息
                    $insertUserRes = $userCommonObj->insertNewUser($user_params);
                    if ($insertUserRes === false) {
                        return $this->com_return(false,config("params.SUCCESS"));
                    }
                }else{
                    $uid = $userInfo['uid'];
                }
            }
            /*用户信息 off*/

            //查看当前用户是否有已开台
            //查看当前桌台是否是开台状态,只有开台状态的桌子才能被开拼
            if (!empty($uid)){
                $userHaveOpenTable = $tableBusinessModel
                    ->where('uid',$uid)
                    ->where("status",config("order.table_business_status")['open']['key'])
                    ->whereTime("open_time","between",[$begin_time,$end_time])
                    ->count();
                if ($userHaveOpenTable > 0){
                    //当前用户已开台,不可进行开拼操作
                    return $this->com_return(false,config("params.REVENUE")['USER_HAVE_TABLE']);
                }
            }

            $reservationCommonObj     = new ReservationCommon();
            $insertTableRevenueReturn = $reservationCommonObj->insertSpellingTable("$uid","$table_id","$turnover_limit","$referrer_id","$referrer_name");

            if ($insertTableRevenueReturn){
                /*登陆管理人员信息 on*/
                $token = $request->header("Token",'');
                $manageInfo = $this->receptionTokenGetManageInfo($token);
                $stype_name = $manageInfo["stype_name"];
                $sales_name = $manageInfo["sales_name"];
                $adminUser = $stype_name . " ". $sales_name;
                /*登陆管理人员信息 off*/

                /*获取桌台信息 on*/
                $tableCommonObj = new TableCommon();
                $tableInfo      = $tableCommonObj->tableIdGetInfo($table_id);
                $table_no       = $tableInfo['table_no'];
                /*获取桌台信息 off*/

                /*记录开台日志 on*/
                $spelling_to  = config("order.table_action_type")['spelling_to']['key'];//拼去
                $spelling_com = config("order.table_action_type")['spelling_com']['key'];//拼来
                $spelling_to_desc  = "拼去".$table_no."桌";
                $spelling_com_desc = "拼来".$table_no."桌";
                insertTableActionLog(microtime(true) * 10000,"$spelling_to","","","$sales_name",$spelling_to_desc,"$table_id","$table_no");
                insertTableActionLog(microtime(true) * 10000,"$spelling_com","$table_id","$table_no","$sales_name",$spelling_com_desc,"","");
                /*记录开台日志 off*/
                //开拼成功
                Db::commit();
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取今日已开台或者空台的酒桌列表
     * @param Request $request
     * @return array
     */
    public function openOrEmptyTable(Request $request)
    {
        $location_id   = $request->param("location_id","");//位置id
        $now_table_id  = $request->param("now_table_id","");//当前点击台位
        $rule = [
            "location_id|位置"  => "require",
            "now_table_id|桌台" => "require",
        ];
        $request_res = [
            "location_id"  => $location_id,
            "now_table_id" => $now_table_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        $location_where = [];
        if (!empty($location_id)){
            $location_where['ta.location_id'] = $location_id;
        }

        try {
            $tableModel = new MstTable();
            $tableInfo = $tableModel
                ->alias("t")
                ->join("mst_table_area ta","ta.area_id = t.area_id")//区域
                ->join("mst_table_location tl","tl.location_id = ta.location_id")//位置
                ->join("mst_table_size ts","ts.size_id = t.size_id")//人数
                ->join("mst_table_appearance tap","tap.appearance_id = t.appearance_id")//品项
                ->join("table_revenue tr","tr.table_id = t.table_id","LEFT")
                ->join("user u","u.uid = tr.uid","LEFT")
                ->where('t.is_delete',0)
                ->where('t.is_enable',1)
                ->where('ta.is_enable',1)
                ->where('tl.is_delete',0)
                ->where('ta.is_delete',0)
                ->where($location_where)
                ->group("t.table_id")
                ->order('t.table_no,tl.location_id,ta.area_id,tap.appearance_id')
                ->field("t.table_id,t.table_no,t.reserve_type,t.people_max,t.table_desc")
                ->field("ta.area_id,ta.area_title,ta.area_desc")
                ->field("tl.location_id,tl.location_title")
                ->field("ts.size_title")
                ->field("tap.appearance_title")
                ->select();
            $tableInfo = json_decode(json_encode($tableInfo),true);

            $tableRevenueModel  = new TableRevenue();

            $date_time = 1;//今天
            $reservationCommonObj = new ReservationCommon();
            $date_time_arr = $reservationCommonObj->getSysTimeLong($date_time);
            $begin_time    = $date_time_arr['beginTime'];
            $end_time      = $date_time_arr['endTime'];

            $pending_payment = config("order.table_reserve_status")['pending_payment']['key'];
            $re_success      = config("order.table_reserve_status")['success']['key'];
            $status_str      = "$pending_payment,$re_success";

            foreach ($tableInfo as $key => $val) {
                $table_id = $tableInfo[$key]['table_id'];//桌号id
                /*桌子预约状态筛选 On*/
                $tableStatusRes = $tableRevenueModel
                    ->where('table_id',$table_id)
                    ->where('status',"IN",$status_str)
                    ->whereTime("reserve_time","between",["$begin_time","$end_time"])
                    ->find();
                $tableStatusRes = json_decode(json_encode($tableStatusRes),true);
                if (!empty($tableStatusRes)){
                    //如果已被预约
                    unset($tableInfo[$key]);
                }
                /*桌子状态筛选 off*/
            }
            $tableInfo = array_values($tableInfo);

            $tableBusinessModel = new TableBusiness();
            for ($i = 0; $i < count($tableInfo); $i ++) {
                $table_id = $tableInfo[$i]['table_id'];
                /*桌子开台状态筛选 On*/
                $openTableStatusRes = $tableBusinessModel
                    ->where('table_id',$table_id)
                    ->where('status',config('order.table_business_status')['open']['key'])
                    ->whereTime("open_time","between",["$begin_time","$end_time"])
                    ->count();
                if ($openTableStatusRes > 0) {
                    $tableInfo[$i]['table_status'] = 2;//已开台
                    if ($openTableStatusRes > 1) {
                        $tableInfo[$i]['is_join'] = 1;
                    }
                }else{
                    $tableInfo[$i]['table_status'] = 0;//已开台
                    $tableInfo[$i]['is_join'] = 0;//已开台
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$tableInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * TODO '暂时弃用 不要删除,低消和会员限定信息资料需使用'
     * 获取今日已开台或者空台的桌
     * @param Request $request
     * @return array
     */
    public function alreadyOpenTable(Request $request)
    {
        $status = $request->param("status","");
        $res = [];
        try {
            $tableRevenueModel = new TableRevenue();
            if (empty($status)){
                /*查看台位是否已被预约 On*/
                $pending_payment = config("order.table_reserve_status")['pending_payment']['key'];//待付定金
                $reserve_success = config("order.table_reserve_status")['success']['key'];//预定成功
                $already_open    = config("order.table_reserve_status")['open']['key'];//已开台
                $status_str      = "$pending_payment,$reserve_success,$already_open";

                $tableModel = new MstTable();
                $tableInfo = $tableModel
                    ->alias("t")
                    ->join("mst_table_area ta","ta.area_id = t.area_id")
                    ->join("mst_table_location tl","tl.location_id = ta.location_id")
                    ->where("t.is_delete",0)
                    ->order('t.sort')
                    ->field("t.table_id,t.area_id,t.table_no,t.table_desc,t.is_enable,t.turnover_limit_l1,t.turnover_limit_l2,t.turnover_limit_l3,t.subscription_l1,t.subscription_l2,t.subscription_l3")
                    ->field("ta.area_title")
                    ->field("tl.location_title")
                    ->select();
                $tableInfo = json_decode(json_encode($tableInfo),true);

                foreach ($tableInfo as $key => $val){
                    $table_id = $val['table_id'];
                    $table_is_re = $tableRevenueModel
                        ->whereTime("reserve_time","today")
                        ->where('status',"IN",$status_str)
                        ->where("table_id",$table_id)
                        ->count();
                    if ($table_is_re > 0){
                        //移除当前桌位
                        unset($tableInfo[$key]);
                    }
                }
                $res = array_values($tableInfo);

                $reservationCommonObj = new ReservationCommon();
                for ($m = 0; $m <count($res); $m ++){
                    /*特殊日期 匹配特殊定金 on*/
                    $appointment = time();
                    /*特殊日期 匹配特殊定金 on*/
                    $dateList = $reservationCommonObj->isReserveDate($appointment);
                    if (!empty($dateList)){
                        //是特殊日期
                        $turnover_limit = $res[$m]['turnover_limit_l3'];//特殊日期预约最低消费
                        $subscription   = $res[$m]['subscription_l3'];//特殊日期预约定金
                    }else{
                        //不是特殊日期
                        //查看预约日期是否是周末日期
                        $today_week = getTimeWeek($appointment);

                        $reserve_subscription_week = getSysSetting("reserve_subscription_week");

                        $is_bh = strpos("$reserve_subscription_week","$today_week");

                        if ($is_bh !== false){
                            //如果包含,则获取特殊星期的押金和低消
                            $turnover_limit = $res[$m]['turnover_limit_l2'];//周末日期预约最低消费
                            $subscription   = $res[$m]['subscription_l2'];//周末日期预约定金

                        }else{
                            //如果不包含
                            $turnover_limit = $res[$m]['turnover_limit_l1'];//平时预约最低消费
                            $subscription   = $res[$m]['subscription_l1'];//平时预约定金
                        }
                    }
                    $res[$m]['turnover_limit'] = $turnover_limit;
                    $res[$m]['subscription']   = $subscription;
                    /*特殊日期 匹配特殊定金 off*/

                    //移除数组指定的key, 多个以逗号隔开
                    $res[$m] = array_remove($res[$m],"turnover_limit_l1,turnover_limit_l2,turnover_limit_l3,subscription_l1,subscription_l2,subscription_l3");
                }
            }

            if ($status == '1'){
                $status = config("order.table_reserve_status")['already_open']['key'];
                $res = $tableRevenueModel
                    ->alias("tr")
                    ->join("user u","u.uid = tr.uid","LEFT")
                    ->join("mst_table_area ta","ta.area_id = tr.area_id")
                    ->join("mst_table_location tl","tl.location_id = ta.location_id")
                    ->where("tr.status",$status)
                    ->where(function ($query){
                        $query->where('tr.parent_trid',Null);
                        $query->whereOr('tr.parent_trid','');
                    })
                    ->field("tr.trid,tr.area_id,tr.is_join,tr.table_id,tr.table_no")
                    ->field("ta.area_title")
                    ->field("tl.location_title")
                    ->field("u.name parent_name,u.phone parent_phone")
                    ->select();
            }
            $res = json_decode(json_encode($res),true);

            $tableCardModel = new MstTableCard();
            for ($i = 0; $i < count($res); $i++){
                $table_id = $res[$i]['table_id'];
                $cardInfo = $tableCardModel
                    ->alias("tc")
                    ->join("mst_card_vip cv","cv.card_id = tc.card_id")
                    ->where("tc.table_id",$table_id)
                    ->field("cv.card_id,cv.card_name,cv.card_type")
                    ->select();
                $cardInfo = json_decode(json_encode($cardInfo),true);
                $res[$i]["card_info"] = $cardInfo;
            }
            $res =  _unsetNull($res);
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 转台或转拼
     * @param Request $request
     * @return array
     */
    public function turnTableOrSpelling(Request $request)
    {
        $now_buid    =  $request->param("now_buid","");//当前台位信息
        $to_table_id = $request->param("to_table_id","");//转至桌号id

        $rule = [
            "now_buid|当前台位订单" => "require",
            "to_table_id|转至空闲台位" => "require",
        ];
        $request_res = [
            "now_buid"    => $now_buid,
            "to_table_id" => $to_table_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        Db::startTrans();
        try {
            $tableBusinessModel = new TableBusiness();

            /*获取当前台位信息  On*/
            $nowTableInfo  = $tableBusinessModel
                ->where("buid",$now_buid)
                ->find();
            $nowTableInfo = json_decode(json_encode($nowTableInfo),true);
            if (empty($nowTableInfo)) {
                return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
            }
            /*获取当前台位信息  Off*/

            if ($nowTableInfo['status'] != config('order.table_business_status')['open']['key']) {
                //如果状态不是已开台,则不可转台
                return $this->com_return(false,config("params.REVENUE")['NOT_OPEN_NO_TURN']);
            }

            $reservationCommonObj = new ReservationCommon();
            $date_time_arr = $reservationCommonObj->getSysTimeLong(1);//今天
            $begin_time    = $date_time_arr['beginTime'];
            $end_time      = $date_time_arr['endTime'];

            /*获取转至桌号当前开台状态 On*/
            $toTableOpenCount = $tableBusinessModel
                ->where("table_id",$to_table_id)
                ->whereTime("open_time","between",[$begin_time,$end_time])
                ->count();
            /*获取转至桌号当前开台状态 Off*/
            $businessCommonObj = new BusinessCommon();

            if ($toTableOpenCount > 0) {
                /*转拼 On*/
                $res = $businessCommonObj->turnSpelling($nowTableInfo,"$to_table_id","$begin_time","$end_time");
                /*转拼 Off*/
            }else{
                /*转台 On*/
                $res = $businessCommonObj->turnTable($nowTableInfo,"$to_table_id","$begin_time","$end_time");
                /*转台 Off*/
            }

           if (isset($res['result']) && $res['result']) {
               /*登陆管理人员信息 on*/
               $token      = $request->header("Token",'');
               $manageInfo = $this->receptionTokenGetManageInfo($token);
               $stype_name = $manageInfo["stype_name"];
               $sales_name = $manageInfo["sales_name"];
               $adminUser  = $stype_name ." ". $sales_name;
               /*登陆管理人员信息 off*/

               /*换去桌台信息 On*/
               $tableCommonObj = new TableCommon();

               $toTableInfo = $tableCommonObj->tableIdGetInfo($to_table_id);
               $to_table_no = $toTableInfo['table_no'];
               /*换去桌台信息 Off*/

               /*获取桌台信息 on*/
               $now_table_id = $nowTableInfo['table_id'];
               $now_table_no = $nowTableInfo['table_no'];
               /*获取桌台信息 off*/

               /*记录开台日志 on*/
               $turn_to   = config("order.table_action_type")['turn_to']['key'];//换去
               $turn_come = config("order.table_action_type")['turn_come']['key'];//换来
               $turn_to_desc  = "拼去".$to_table_no."桌";
               $turn_come_desc = "由".$now_table_no."桌,拼来";

               insertTableActionLog(microtime(true) * 10000,"$turn_to","$now_table_id","$now_table_no","$sales_name",$turn_to_desc,"$to_table_id","$to_table_no");
               insertTableActionLog(microtime(true) * 10000,"$turn_come","$to_table_id","$to_table_no","$sales_name",$turn_come_desc,"$now_table_id","$now_table_no");
               /*记录开台日志 off*/
                Db::commit();
           }else{
                return $this->com_return(false,config("params.FAIL"));
           }
            return $this->com_return(false,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}