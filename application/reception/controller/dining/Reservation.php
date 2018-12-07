<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 下午5:21
 */

namespace app\reception\controller\dining;


use app\common\controller\ReceptionAuthAction;
use app\common\controller\ReservationCommon;
use app\common\controller\TableCommon;
use app\common\controller\UserCommon;
use app\common\model\ManageSalesman;
use app\common\model\MstTable;
use app\common\model\MstTableCard;
use app\common\model\TableBusiness;
use app\common\model\TableRevenue;
use app\services\Sms;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class Reservation extends ReceptionAuthAction
{
    /**
     * 吧台列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $location_id   = $request->param("location_id","");//大区id
        $status        = $request->param("status","");//预约状态 1已预约;2可预约;8已到店;为空或0是全部
        $keyword       = $request->param("keyword","");//关键字

        try {
            $date_time = 1;//今天
            $reservationCommonObj = new ReservationCommon();
            $date_time_arr = $reservationCommonObj->getSysTimeLong($date_time);
            $begin_time    = $date_time_arr['beginTime'];
            $end_time      = $date_time_arr['endTime'];

            $re_success = config("order.table_reserve_status")['success']['key'];
            $open_table = config("order.table_reserve_status")['open']['key'];
            $re_status = "$re_success,$open_table";
            $location_where = [];
            if (!empty($location_id)){
                $location_where['ta.location_id'] = $location_id;
            }
            $where = [];
            if (!empty($keyword)){
                $where["t.table_no|ta.area_title|tl.location_title|tap.appearance_title|u.name|u.phone|u.nickname"] = ["like","%$keyword%"];
            }

            $tableModel = new MstTable();
            $tableInfo = $tableModel
                ->alias("t")
                ->join("mst_table_area ta","ta.area_id = t.area_id",'LEFT')//区域
                ->join("mst_table_location tl","tl.location_id = ta.location_id","LEFT")//位置
                ->join("mst_table_size ts","ts.size_id = t.size_id","LEFT")//人数
                ->join("mst_table_appearance tap","tap.appearance_id = t.appearance_id","LEFT")//品项
                ->join("table_revenue tr","tr.table_id = t.table_id","LEFT")
                ->join("user u","u.uid = tr.uid","LEFT")
                ->where('t.is_delete',0)
                ->where('t.is_enable',1)
                ->where('ta.is_enable',1)
                ->where($where)
                ->where($location_where)
                ->group("t.table_id")
                ->order('t.table_no,tl.location_id,ta.area_id,tap.appearance_id')
                ->field("t.table_id,t.table_no,t.reserve_type,t.people_max,t.table_desc")
                ->field("ta.area_id,ta.area_title,ta.area_desc")
                ->field("tl.location_title")
                ->field("ts.size_title")
                ->field("tap.appearance_title")
                ->select();
            $tableInfo = json_decode(json_encode($tableInfo),true);

            $tableRevenueModel  = new TableRevenue();
            $tableBusinessModel = new TableBusiness();
            for ($i = 0; $i < count($tableInfo); $i ++){
                $table_id = $tableInfo[$i]['table_id'];
                /*桌子预约状态筛选 On*/
                $tableStatusRes = $tableRevenueModel
                    ->where('table_id',$table_id)
                    ->where('status',"IN",$re_status)
                    ->whereTime("reserve_time","between",["$begin_time","$end_time"])
                    ->find();
                $tableStatusRes = json_decode(json_encode($tableStatusRes),true);
                if (!empty($tableStatusRes)){
                    $table_status = $tableStatusRes['status'];
                    $reserve_time = $tableStatusRes['reserve_time'];
                    if ($reserve_time < time()){
                        $tableInfo[$i]['is_overtime'] = 1;//已超时
                    }else{
                        $tableInfo[$i]['is_overtime'] = 0;//未超时
                    }
                    $tableInfo[$i]['reserve_time'] = date("H:i",$reserve_time);
                    if ($table_status == 0){
                        $tableInfo[$i]['table_status'] = 1;//预约代付定金
                    }elseif ($table_status == 1){
                        $tableInfo[$i]['table_status'] = 1;//已被预约
                    }elseif ($table_status == 2){
                        $tableInfo[$i]['table_status'] = 2;//已开台
                    }else{
                        $tableInfo[$i]['table_status'] = 0;//空
                    }
                }else{
                    /*桌子开台状态筛选 On*/
                    $openTableStatusRes = $tableBusinessModel
                        ->where('table_id',$table_id)
                        ->where('status',config('order.table_business_status')['open']['key'])
                        ->whereTime("open_time","between",["$begin_time","$end_time"])
                        ->find();
                    $openTableStatusRes = json_decode(json_encode($openTableStatusRes),true);
                    if (!empty($openTableStatusRes)) {
                        $tableInfo[$i]['table_status'] = 2;
                    }else{
                        $tableInfo[$i]['table_status'] = 0;
                        $tableInfo[$i]['reserve_time'] = 0;
                        $tableInfo[$i]['is_overtime'] = 0;
                    }
                    /*桌子开台状态筛选 Off*/
                }
                /*桌子预约状态筛选 Off*/

                /*桌子限制筛选 on*/
                if ($tableInfo[$i]['reserve_type'] == config("table.reserve_type")['0']['key']){
                    //无限制
                    $tableInfo[$i]['is_limit'] = 0;
                }else{
                    $tableInfo[$i]['is_limit'] = 1;
                }
                /*桌子限制筛选 off*/

            }

            if ($status == 1){
                //已预约
                foreach ($tableInfo as $key => $val){
                    if ($val['table_status'] == 0 || $val['table_status'] == 1){
                        unset($tableInfo[$key]);
                    }
                }
                $res = array_values($tableInfo);
            }elseif ($status == 2){
                //可预约
                foreach ($tableInfo as $key => $val){
                    if ($val['table_status'] == 0){
                        unset($tableInfo[$key]);
                    }
                }
                $res = array_values($tableInfo);
            }elseif ($status == 2){
                //已到店
                foreach ($tableInfo as $key => $val){
                    if ($val['table_status'] != 2){
                        unset($tableInfo[$key]);
                    }
                }
                $res = array_values($tableInfo);
            }else{
                //全部
                $res = $tableInfo;
            }
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 桌台详情
     * @param Request $request
     * @return array
     */
    public function tableDetails(Request $request)
    {
        $table_id = $request->param("table_id","");//桌号id

        if (empty($table_id)){
            return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
        }
        try {
            $date_time = 1;//今天
            $reservationCommonObj = new ReservationCommon();
            $date_time_arr = $reservationCommonObj->getSysTimeLong($date_time);
            $begin_time    = $date_time_arr['beginTime'];
            $end_time      = $date_time_arr['endTime'];

            $tableModel = new MstTable();

            $tableInfo = $tableModel
                ->alias("t")
                ->join("mst_table_area ta","ta.area_id = t.area_id","LEFT")
                ->join("el_mst_table_location tl","tl.location_id = ta.location_id","LEFT")
                ->join("el_mst_table_appearance mta","mta.appearance_id = t.appearance_id","LEFT")
                ->join("manage_salesman ms","ms.sid = ta.sid","LEFT")
                ->where("table_id",$table_id)
                ->field("ms.sales_name as service_name,ms.phone service_phone")
                ->field("tl.location_title")
                ->field("ta.area_title,ta.turnover_limit_l1,ta.turnover_limit_l2,ta.turnover_limit_l3")
                ->field("mta.appearance_title")
                ->field("t.table_id,t.table_no,t.reserve_type")
                ->find();
            $tableInfo = json_decode(json_encode($tableInfo),true);
            if (empty($tableInfo)){
                return $this->com_return(false,config("params.ABNORMAL_ACTION"));
            }
            /*会员限定 on*/
            $reserve_type = $tableInfo['reserve_type'];
            if ($reserve_type == config("table.reserve_type")['1']['key']){
                $cardInfo = Db::name("mst_table_card")
                    ->alias("tc")
                    ->join("mst_card_vip cv","cv.card_id = tc.card_id")
                    ->where('tc.table_id',$table_id)
                    ->field("cv.card_id,cv.card_name")
                    ->select();
                $cardInfo = json_decode(json_encode($cardInfo),true);
                $tableInfo['card_vip'] = $cardInfo;
            }else{
                $tableInfo['card_vip'] = [];
            }
            /*会员限定 off*/

            $date = time() + 24 * 60 * 60;
            /*特殊日期 匹配特殊定金 on*/
            $reservationCommonObj = new ReservationCommon();
            $dateList = $reservationCommonObj->isReserveDate($date);

            if (!empty($dateList)){
                //是特殊日期
                $turnover_limit = $tableInfo['turnover_limit_l3'];//特殊日期预约最低消费
                $subscription   = $dateList['subscription'];//特殊日期预约定金
            }else{
                //不是特殊日期
                //查看预约日期是否是周末日期
                $today_week = getTimeWeek($date);
                $reserve_subscription_week = getSysSetting("reserve_subscription_week");
                $is_bh = strpos("$reserve_subscription_week","$today_week");
                if ($is_bh !== false){
                    //如果包含,则获取特殊星期的押金和低消
                    $turnover_limit = $tableInfo['turnover_limit_l2'];//周末日期预约最低消费
                    $subscription   = getSysSetting('reserve_deposit_weekend');//周末日期预约定金
                }else{
                    //如果不包含
                    $turnover_limit = $tableInfo['turnover_limit_l1'];//平时预约最低消费
                    $subscription   = getSysSetting('reserve_deposit_weekdays');//平时预约定金
                }
            }
            $tableInfo['turnover_limit'] = $turnover_limit;
            $tableInfo['subscription']   = $subscription;

            //移除数组指定的key, 多个以逗号隔开
            $tableInfo = array_remove($tableInfo,"turnover_limit_l1,turnover_limit_l2,turnover_limit_l3");
            /*特殊日期 匹配特殊定金 off*/
            $pending_payment = config("order.table_reserve_status")['pending_payment']['key'];//待付定金或结算
            $reserve_success = config("order.table_reserve_status")['success']['key'];//预定成功
            $already_open    = config("order.table_reserve_status")['open']['key'];//已开台

            $status_str = $pending_payment.",".$reserve_success.",".$already_open;

            /*预约基本信息 On*/
            $tableRevenueModel = new TableRevenue();
            $revenue_column = $tableRevenueModel->revenue_column;
            $revenueInfo = $tableRevenueModel
                ->alias("tr")
                ->join("user u","u.uid = tr.uid","LEFT")
                ->join("user_card uc","uc.uid = tr.uid","LEFT")
                ->join("mst_user_level ul","ul.level_id = u.level_id","LEFT")
                ->join("manage_salesman ms","ms.sid = tr.sid","LEFT")
                ->where('tr.table_id',$table_id)
                ->whereTime("tr.reserve_time","between",["$begin_time","$end_time"])
                ->where("tr.status","IN",$status_str)
                ->field("u.name,u.phone user_phone,u.nickname,u.level_id,u.credit_point")
                ->field("ul.level_name")
                ->field("uc.card_name,uc.card_type")
                ->field("ms.phone sales_phone")
                ->field($revenue_column)
                ->find();
            $revenueInfo = json_decode(json_encode($revenueInfo),true);
            if (!empty($revenueInfo)){
                $revenueInfo["reserve_time"] = date("H:i",$revenueInfo["reserve_time"]);
            }
            $tableInfo["revenueInfo"] = $revenueInfo;
            /*预约基本信息 Off*/

            return $this->com_return(true,config("params.SUCCESS"),$tableInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 预约确认
     * @param Request $request
     * @return array
     */
    public function createReservation(Request $request)
    {
        $table_id       = $request->param("table_id","");//预定桌id
        $subscription   = $request->param("subscription","");//预约定金
        $turnover_limit = $request->param("turnover_limit","");//低消
        $user_phone     = $request->param("user_phone","");//用户电话
        $user_name      = $request->param("user_name","");//用户姓名
        $sales_phone    = $request->param("sales_phone","");//营销电话
        $date           = $request->param("date","");//到店日期
        $go_time        = $request->param("go_time","");//到店时间
        $rule = [
            "subscription|预约定金"  => "require",
            "turnover_limit|低消"   => "require",
            "user_phone|用户电话"    => "require|regex:1[0-9]{1}[0-9]{9}",
            "sales_phone|营销电话"   => "regex:1[0-9]{1}[0-9]{9}",
            "date|到店日期"          => "require",
            "table_id|桌id"         => "require",
            "go_time|到店时间"       => "require",
        ];
        $request_res = [
            "subscription"      => $subscription,
            "turnover_limit"    => $turnover_limit,
            "table_id"          => $table_id,
            "user_phone"        => $user_phone,
            "sales_phone"       => $sales_phone,
            "date"              => $date,
            "go_time"           => $go_time,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        Db::startTrans();
        try {
            /*登陆前台人员信息 on*/
            $token       = $request->header("Token",'');
            $manageInfo  = $this->receptionTokenGetManageInfo($token);
            $stype_name  = $manageInfo["stype_name"];
            $action_name = $manageInfo["sales_name"];
            //$adminUser = $stype_name . " ". $sales_name;
            /*登陆前台人员信息 off*/

            $nowTime = time();
            //根据营销电话获取营销信息
            $salesStatusStr = config("salesman.salesman_type")[0]['key'].",".config("salesman.salesman_type")[1]['key'].",".config("salesman.salesman_type")[4]['key'];

            $salesModel = new ManageSalesman();
            $salesInfo = $salesModel
                ->alias("ms")
                ->join("mst_salesman_type st","st.stype_id = ms.stype_id")
                ->where("ms.phone",$sales_phone)
                ->where("ms.statue",config("salesman.salesman_status")['working']['key'])
                ->where("st.stype_key","IN",$salesStatusStr)
                ->field("ms.sid,ms.sales_name")
                ->field("st.stype_key")
                ->find();
            $salesInfo = json_decode(json_encode($salesInfo),true);
            if (!empty($salesInfo)){
                $sid        = $salesInfo["sid"];
                $sales_name = $salesInfo["sales_name"];
                $stype_key  = $salesInfo["stype_key"];

            }else{
                $sid        = "";
                $sales_name = "";
                $stype_key  = config("salesman.salesman_type")[3]['key'];
            }

            //查询是否存在此用户

            $userCommonObj = new UserCommon();
            $userInfo = $userCommonObj->uidOrPhoneGetUserInfo("$user_phone");
            if (!empty($userInfo)){
                $uid      = $userInfo["uid"];
                $card_id  = $userInfo["card_id"];
            }else{
                //新建用户
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
                    "referrer_type" => $stype_key,
                    "referrer_id"   => $sid,
                    "created_at"    => $nowTime,
                    "updated_at"    => $nowTime
                ];

                //插入新的用户信息
                $insertUserRes  =$userCommonObj->insertNewUser($user_params);
                if ($insertUserRes === false) {
                    return $this->com_return(false,config("params.FAIL"));
                }
                $card_id  = "";
            }

            //查看当前桌子是否是限定桌
            $tableModel = new MstTable();
            $tableInfo  = $tableModel
                ->where("table_id",$table_id)
                ->field("area_id,table_no")
                ->find();
            $tableInfo = json_decode(json_encode($tableInfo),true);
            $area_id   = $tableInfo['area_id'];
            $table_no  = $tableInfo['table_no'];

            $tableCardModel    = new MstTableCard();
            $tableCardInfo = $tableCardModel
                ->where("table_id",$table_id)
                ->select();
            $tableCardInfo = json_decode(json_encode($tableCardInfo),true);

            //判断桌台是否限定
            $is_xd = false;
            if (!empty($tableCardInfo)){
                //限制预定
                if (!empty($card_id)){
                    $card_id_arr = [];
                    foreach ($tableCardInfo as $key => $val){
                        $card_id_arr[$key] = $val['card_id'];
                    }
                    $in_array_res = in_array($card_id,$card_id_arr);

                    if (!$in_array_res){
                        $is_xd = true;
                    }
                }
            }

            if ($is_xd){
                return $this->com_return(false,config("params.REVENUE")['XD_TABLE_FALL']);
            }

            $reserve_way = config("user.register_way")['web']['key'];

            $is_reception  = "reception";//前台预约参数
            $reservationCommonObj = new ReservationCommon();
            $revenueReturn = $reservationCommonObj->confirmReservationPublic("$sales_phone","$table_id","$date","$go_time","$subscription","$turnover_limit","$reserve_way","$action_name","$uid","$is_reception");
            if (!isset($revenueReturn["result"]) || !$revenueReturn["result"]) {
                return $revenueReturn;
            }
            Db::commit();
            $date = date("Y-m-d",$date);
            $reserve_date = $date." ".$go_time;
            $desc = " 为用户 ".$user_name."($user_phone)"." 预约 $reserve_date 的".$table_no."桌";
            $type = config("order.table_action_type")['revenue_table']['key'];
            //记录日志
            insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$sales_name",$desc,"","");
            /*发送短信 On*/
            $smsObj = new Sms();
            $smsObj->sendMsg("$user_name","$user_phone","$sales_name","$sales_phone","revenue","$reserve_date","$table_no","$reserve_way");
            /*发送短信 Off*/
            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 取消预约
     * @param Request $request
     * @return array
     */
    public function cancelReservation(Request $request)
    {
        $trid = $request->param("trid","");//台位id
        if (empty($trid)){
            return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
        }
        Db::startTrans();
        try {
            //获取当前台位信息
            $tableCommonObj = new TableCommon();
            $tableInfo = $tableCommonObj->tridGetTableInfo($trid);
            $reservationCommonObj = new ReservationCommon();
            $res = $reservationCommonObj->cancelReservationPublic($trid,$tableInfo);
            if (!isset($res['result']) || $res['result'] == false){
                return $this->com_return(false,config("params.FAIL"));
            }
            Db::commit();
            /*记录日志 on*/
            $uid          = $tableInfo['uid'];
            $userInfo     = getUserInfo($uid);
            $userName     = $userInfo["name"];
            $userPhone    = $userInfo["phone"];
            $table_id     = $tableInfo['table_id'];
            $table_no     = $tableInfo['table_no'];
            $reserve_time = $tableInfo['reserve_time'];//预约时间
            $reserve_date = date("Y-m-d H:i:s",$reserve_time);
            $type         = config("order.table_action_type")['cancel_revenue']['key'];
            $desc         = " 为用户 ".$userName."($userPhone)"." 取消 $reserve_date ".$table_no."桌的预约";

            /*登陆管理人员信息 on*/
            $token = $request->header("Token",'');
            $manageInfo = $this->receptionTokenGetManageInfo($token);
            $stype_name = $manageInfo["stype_name"];
            $sales_name = $manageInfo["sales_name"];
            $adminUser = $stype_name . " ". $sales_name;
            /*登陆管理人员信息 off*/
            //取消预约记录日志
            insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$sales_name",$desc,"","");
            /*记录日志 off*/
            /*发送短信 On*/
            $reserve_way     = config("user.register_way")['web']['key'];
            $sales_phone = "";
            $smsObj = new Sms();
            $smsObj->sendMsg("$userName","$userPhone","$sales_name","$sales_phone","cancel","$reserve_date","$table_no","$reserve_way");
            /*发送短信 Off*/
            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 到店
     * @param Request $request
     * @return array|mixed
     */
    public function goToShop(Request $request)
    {
        $trid = $request->param("trid","");
        if (empty($trid)){
            return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
        }
        Db::startTrans();
        try{
            $trInfo = Db::name("table_revenue")
                ->where("trid",$trid)
                ->find();
            $trInfo = json_decode(json_encode($trInfo),true);
            if (empty($trInfo)){
                return $this->com_return(false,config("params.ABNORMAL_ACTION"));
            }
            $subscription_type = $trInfo['subscription_type'];
            $subscription      = $trInfo['subscription'];
            $status_now        = config("order.table_reserve_status")['go_to_table']['key'];

            $reservationCommonObj = new ReservationCommon();
            $changeTableStatus    = $reservationCommonObj->changeRevenueTableStatus($trid,$status_now);
            if (!$changeTableStatus){
                return $this->com_return(false,config("params.ABNORMAL_ACTION"));
            }

            if ($subscription_type == config("order.subscription_type")['subscription']['key']){
                //如果预约定金类型为定金 1
                if ($subscription > 0){
                    //此时执行开台成功,定金退还操作
                    $suid_info = Db::name("bill_subscription")
                        ->where("trid",$trid)
                        ->field("suid")
                        ->find();
                    $suid_info = json_decode(json_encode($suid_info),true);
                    $suid      = $suid_info["suid"];

                    $diningRoomObj = new DiningRoom();
                    $refund_return = $diningRoomObj->refundDeposit($suid,$subscription);
                    $res           = json_decode($refund_return,true);

                    if (!isset($res["result"]) || !$res["result"]){
                        return $res;
                    }
                    //退款成功则变更定金状态
                    $status = config("order.reservation_subscription_status")['open_table_refund']['key'];
                    $params = [
                        "status"        => $status,
                        "is_refund"     => 1,
                        "refund_amount" => $subscription,
                        "updated_at"    => time()
                    ];
                    $is_bb = Db::name("bill_subscription")
                        ->where("suid",$suid)
                        ->update($params);
                    if ($is_bb === false) {
                        return $this->com_return(false,"到店操作失败,微信退款成功");
                    }
                }
            }
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        }catch (Exception $e){
            Db::rollback();
            return $this->com_return(false,$e->getMessage());
        }
    }
}