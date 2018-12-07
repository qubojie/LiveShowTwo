<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午5:42
 */

namespace app\common\controller;


use app\common\model\BillSubscription;
use app\common\model\MstTable;
use app\common\model\MstTableCard;
use app\common\model\MstTableImage;
use app\common\model\MstTableReserveDate;
use app\common\model\TableBusiness;
use app\common\model\TableRevenue;
use app\services\Sms;
use think\Config;
use think\Db;
use think\Exception;
use think\Request;

class ReservationCommon extends BaseController
{
    /**
     * 预约列表公共部分
     * @param $size_id
     * @param $location_id
     * @param $appointment
     * @param $user_card_id
     * @param $pagesize
     * @param $config
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function reservationPublic($location_id,$appointment,$user_card_id,$pagesize,$config)
    {
        $appointment = (int)$appointment;

        $where_card = [];
        if (!empty($user_card_id)){
            //会员用户,不可看到保留和仅非会员用户的桌子
            $where_card['t.reserve_type'] = ["neq",\config("table.reserve_type")['2']['key']];
        }else{
            //非会员用户,不可看到保留和仅会员用户的桌子
            $where_card['t.reserve_type'] = ["neq",\config("table.reserve_type")['1']['key']];
        }

        $location_where = [];
        if (!empty($location_id)){
            $location_where['ta.location_id'] = $location_id;
        }

        $tableModel = new MstTable();
        $res = $tableModel
            ->alias("t")
            ->join("mst_table_area ta","ta.area_id = t.area_id")//区域
            ->join("mst_table_location tl","tl.location_id = ta.location_id")//位置
            ->join("mst_table_size ts","ts.size_id = t.size_id")//人数
            ->join("mst_table_appearance tap","tap.appearance_id = t.appearance_id")//品项
            ->join("mst_table_card tc","tc.table_id = t.table_id","LEFT")//卡
            ->where("t.is_enable",1)
            ->where("t.is_delete",0)
            ->where($location_where)
            ->where($where_card)
            ->where("t.reserve_type","neq",\config("table.reserve_type")['3']['key'])
            ->group("t.table_id")
            ->order('t.sort,t.table_no')
            ->field("t.table_id,t.table_no,t.reserve_type,t.people_max,t.table_desc")
            ->field("ta.turnover_limit_l1,ta.turnover_limit_l2,ta.turnover_limit_l3,ta.area_id,ta.area_title,ta.area_desc,ta.sid")
            ->field("tl.location_title")
            ->field("ts.size_title")
            ->field("tap.appearance_title")
//            ->paginate($pagesize,false,$config);
            ->select();
        $res = json_decode(json_encode($res),true);
//        $data = $res['data'];
        $pending_payment = config("order.table_reserve_status")['pending_payment']['key'];//待付定金或结算
        $reserve_success = config("order.table_reserve_status")['success']['key'];//预定成功
        $already_open    = config("order.table_reserve_status")['open']['key'];//已开台

        $can_not_reserve = $pending_payment.",".$reserve_success.",".$already_open;
        $where_status['status'] = array('IN',"$can_not_reserve");//查询字段的值在此范围之内的做显示

        $start_time = strtotime(date("Y-m-d",$appointment));
        $end_time   = $start_time + 24 * 60 * 60 - 1;
        $date_time = "$start_time,$end_time";
        $reservationCommonObj = new ReservationCommon();
        $date_time_arr = $reservationCommonObj->getSysTimeLong($date_time);
        $appointment_start    = $date_time_arr['beginTime'];
        $appointment_end      = $date_time_arr['endTime'];

      /*  $appointment_end   = $appointment + 24 * 60 * 60 - 1;
        $appointment_start = date("Y-m-d H:i:s",$appointment);
        $appointment_end   = date("Y-m-d H:i:s",$appointment_end);*/


        $tableRevenueModel = new TableRevenue();
        foreach ($res as $k => $v){
            $table_id = $v['table_id'];
            $table_reserve_exist = $tableRevenueModel
                ->where('table_id',$table_id)
                ->where($where_status)
                ->whereTime("reserve_time","between",["$appointment_start","$appointment_end"])
                ->count();
            if ($table_reserve_exist){
                unset($res[$k]);
            }
        }
        $res = array_values($res);

        $mstTableCardModel = new MstTableCard();
        for ($q = 0; $q < count($res); $q ++){
            $table_id = $res[$q]['table_id'];
            $cardInfo = $mstTableCardModel
                ->alias("tc")
                ->join("mst_card_vip cv","cv.card_id = tc.card_id")
                ->where('tc.table_id',$table_id)
                ->field("cv.card_id,cv.card_name")
                ->select();
            $cardInfo = json_decode(json_encode($cardInfo),true);
            $res[$q]['card_id_group'] = $cardInfo;
        }

        if (empty($user_card_id)){
            foreach ($res as $key => $val){
                if (!empty($val['card_id_group'])){
                    unset($res[$key]);
                }
            }
        }else{
            //查找那些区域绑定了该卡
            //获取限制区域的卡信息
            $table_card_info = $mstTableCardModel
                ->where("card_id",$user_card_id)
                ->select();
            $table_card_info = json_decode(json_encode($table_card_info),true);
            if (empty($table_card_info)){
                //未有区域绑定该卡
                foreach ($res as $key => $val){
                    if (!empty($val['card_id_group'])){
                        unset($res[$key]);
                    }
                }
            }else{
                $my_card_id[] = $user_card_id;
                //有区域绑定该卡
                foreach ($res as $key => $val){
                    //获取有限制的桌台
                    if ($val['card_id_group'] != ""){
                        $card_id_group = $val['card_id_group'];
                        if (!empty($card_id_group)){
                            foreach ($card_id_group as $k => $v){
                                $card_id_group[$k] = $v['card_id'];
                            }
                            //如果有交集,则返回交集,否则返回空数组
                            $intersection = array_intersect($my_card_id,$card_id_group);
                            if (empty($intersection)){
                                //无交集
                                unset($res[$key]);
                            }
                        }
                    }
                }
            }
        }
        $res = array_values($res);

        for ($i = 0; $i < count($res); $i++){
            $table_id = $res[$i]['table_id'];
            //如果有设置,则取设置的强制定金,否则,就是桌子的定金

            /*特殊日期 匹配特殊定金 on*/
            $dateList = $this->isReserveDate($appointment);
            if (!empty($dateList)){
                //是特殊日期
//                $type          = $dateList['type'];//日期类型   0普通日  1周末假日  2节假日
                $turnover_limit = $res[$i]['turnover_limit_l3'];//特殊日期预约最低消费
                $subscription   = (int)$dateList['subscription'];//特殊日期预约定金
            }else{
                //不是特殊日期
                //查看预约日期是否是周末日期
                $today_week = getTimeWeek($appointment);
                $reserve_subscription_week = getSysSetting("reserve_subscription_week");

                $is_bh = strpos("$reserve_subscription_week","$today_week");

                if ($is_bh !== false){
                    //如果包含,则获取特殊星期的押金和低消
                    $turnover_limit = $res[$i]['turnover_limit_l2'];//周末日期预约最低消费
                    $subscription   = (int)getSysSetting("reserve_deposit_weekend");//周末订台押金

                }else{
                    //如果不包含
                    $turnover_limit = $res[$i]['turnover_limit_l1'];//平时预约最低消费
                    $subscription   = (int)getSysSetting("reserve_deposit_weekdays");//平日订台押金
                }
            }
            /*特殊日期 匹配特殊定金 off*/

            $res[$i]['turnover_limit']   = $turnover_limit;
            $res[$i]['subscription']     = $subscription;
            $res[$i]['image_group']      = [];

            $tableImageModel = new MstTableImage();
            $image_res = $tableImageModel
                ->where('table_id',$table_id)
                ->select();
            $image_res = json_decode(json_encode($image_res),true);
            for ($m = 0; $m < count($image_res); $m++){
                $res[$i]['image_group'][] = $image_res[$m]['image'];
            }
        }
        $res_data["data"] = $res;
        return $res_data;
    }

    /**
     * 判断当前是否在特殊日期内,如果是,则返回低消和定金
     * @param $appointment
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function isReserveDate($appointment)
    {
        $reserveDateModel = new MstTableReserveDate();
        //将用户约定时间 转化为当日零点的时间戳
        $appointment = strtotime(date("Ymd",$appointment));
        $dateList = $reserveDateModel
            ->where("is_enable","1")
            ->where("appointment",$appointment)
            ->find();
        $dateList = json_decode(json_encode($dateList),true);
        return $dateList;
    }

    /**
     * 查看当前台位是否可以被预定
     * @param $table_id
     * @param $date
     * @return bool
     */
    public function tableStatusCan($table_id,$date)
    {
        $pending_payment = config("order.table_reserve_status")['pending_payment']['key'];//待付定金或结算
        $reserve_success = config("order.table_reserve_status")['reserve_success']['key'];//预定成功
        $already_open    = config("order.table_reserve_status")['already_open']['key'];//已开台
        $go_to_table     = config("order.table_reserve_status")['go_to_table']['key'];//到店
        $clear_table     = config("order.table_reserve_status")['clear_table']['key'];//已清台
        $cancel          = config("order.table_reserve_status")['cancel']['key'];//取消预约

        $can_not_reserve = $pending_payment.",".$reserve_success.",".$already_open.",".$go_to_table;

        $where_status['status'] = array('IN',"$can_not_reserve");//查询字段的值在此范围之内的做显示

        //获取当天的24点的时间戳
        $date_end = $date + 24 * 60 * 60;

        $date_start = date("Y-m-d",$date);
        $date_end   = date("Y-m-d",$date_end);

        $tableRevenueModel = new TableRevenue();
        $is_exist = $tableRevenueModel
            ->where('table_id',$table_id)
            ->where($where_status)
            ->whereTime('reserve_time','between',["$date_start","$date_end"])
            ->count();

        if ($is_exist > 0){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 确认预约公共
     * @param $sales_phone
     * @param $table_id
     * @param $date
     * @param $time
     * @param $subscription
     * @param $turnover_limit
     * @param $reserve_way
     * @param $action_user
     * @param $uid
     * @param string $is_reception
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function confirmReservationPublic($sales_phone,$table_id,$date,$time,$subscription,$turnover_limit,$reserve_way,$action_user,$uid,$is_reception = "")
    {
        //如果报名营销验证营销是否存在
        if (!empty($sales_phone)){
            $saleUserCommonObj = new SalesUserCommon();
            $salesmanInfo      = $saleUserCommonObj->phoneGetSalesmanInfo($sales_phone);
            if (empty($salesmanInfo)){
                //如果未查到,提示用户核对手机号码
                return $this->com_return(false,config("params.SALESMAN_PHONE_ERROR"));
            }
            $sid   = $salesmanInfo['sid'];
            $sname = $salesmanInfo['sales_name'];
        }else{
            //如果营销电话为空,则隶属平台数据
            $sid    = "";
            $sname  = "";
        }

        $date         = date("Y-m-d",$date);
        $reserve_date = $date." ".$time;
        //预约时间
        $reserve_time = strtotime($reserve_date);
        /*判断当前桌台是否可预约 On*/
        $can_reservation = $this->tableIdCheckCanReservation($table_id,$reserve_time);
        if (!$can_reservation) {
            return $this->com_return(false,\config("params.TABLE_IS_RESERVE"));
        }
        /*判断当前桌台是否可预约 Off*/

        //获取是否是特殊日期,是否退换预约押金
        $is_refund_sub = $this->revenueDateRefundSub($reserve_time);
        //首先生成trid,预约订单使用
        $trid = generateReadableUUID("T");
        $suid = "";
        //如果没有押金
        if ($subscription <= 0){
            //预定成功状态1
            $status = Config::get("order.table_reserve_status")['success']['key'];
            $type   = Config::get("order.reserve_type")['no_sub']['key'];//无押金
            //不创建缴押金订单
        }else{
            if (!empty($is_reception)){
                //如果为前台预约,则不创建定金订单,直接预约成功
                //预定成功状态1
                $status = Config::get("order.table_reserve_status")['success']['key'];
                $type   = Config::get("order.reserve_type")['no_sub']['key'];
                //不创建缴押金订单,返回true
            }else{
                //待付定金
                $status = Config::get("order.table_reserve_status")['pending_payment']['key'];
                if($is_refund_sub){
                    //不退押金
                    $type = Config::get("order.reserve_type")['no_refund']['key'];
                }else{
                    //退押金
                    $type = Config::get("order.reserve_type")['sub']['key'];
                }
                //创建缴押金订单,返回相应数据
                $pay_method = \config("order.pay_method")['wxpay']['key'];
                //订单押金id
                $suid = generateReadableUUID("SU");
                $billSubscriptionReturn = $this->billSubscriptionCreate("$suid","$uid","$trid","$subscription","$pay_method");
                if (!$billSubscriptionReturn) {
                    return $this->com_return(false,\config("params.FAIL"));
                }
            }
        }
        $createRevenueReturn = $this->createRevenueOrder("$trid","$status","$type","$sid","$sname","$uid","$table_id","$reserve_way","$reserve_time","","$action_user","$suid","$subscription","$turnover_limit");
        //去创建预约吧台订单信息
        if (!$createRevenueReturn) {
            return $this->com_return(false,\config("params.FAIL"));
        }

        $userInfo    = getUserInfo($uid);
        $user_name   = $userInfo["name"];
        $user_phone  = $userInfo['phone'];

        $tableCommonObj = new TableCommon();
        $tableInfo = $tableCommonObj->tableIdGetInfo($table_id);
        $table_no  = $tableInfo['table_no'];
        $desc = $user_name." 预约 $reserve_date 的".$table_no."桌";

        /*插入预约信息至消息表 on*/
        $content = "客户 $user_name ($user_phone) 预定 $reserve_date $table_no 号桌成功";
        if (!empty($sid)){
            $content .= ",指定营销$sname($sales_phone)";
        }
        $tableMessageParams = [
            "type"       => "revenue",
            "content"    => $content,
            "ssid"       => $sid,
            "status"     => "0",
            "is_read"    => "0",
            "created_at" => time(),
            "updated_at" => time(),
        ];
        $tableMessageReturn = Db::name("table_message")
            ->insert($tableMessageParams);
        if ($tableMessageReturn == false){
            return $this->com_return(false,\config("params.FAIL"));
        }
        /*插入预约信息至消息表 off*/

        if ($subscription <= 0){
            $userCommonObj = new UserCommon();
            $userInfo = $userCommonObj->getUserInfo($uid);
            $phone    = $userInfo['phone'];

            $smsObj = new Sms();
            $type         = "revenue";
            $reserve_time = date("Y-m-d H:i",$reserve_time);
            $name         = "$user_name";
            $sales_name   = "$sname";
            $smsObj->sendMsg($name,$phone,$sales_name,$sales_phone,$type,$reserve_time,$table_no,$reserve_way);
        }

        /*记录预约日志 on*/
        if ($reserve_way == \config("order.reserve_way")['client']['key']){
            $type = config("order.table_action_type")['revenue_table']['key'];
            //记录日志
            insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$action_user",$desc,"","");
        }
        /*记录预约日志 off*/
        return $this->com_return(true,\config("params.SUCCESS"),$suid);
    }

    /**
     * 判断预约时 是否可退押金
     * @param $reserve_time
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function revenueDateRefundSub($reserve_time)
    {
        $begin_time = strtotime(date("Ymd",$reserve_time));
        //获取系统开关 0退  1不退
        $is_refund_sys = getSysSetting("reserve_refund_flag");

        if ($is_refund_sys == "1"){
            $is_refund_sub = 1;//系统设置,不退押金
            return $is_refund_sub;
        }

        $reserveDateModel = new MstTableReserveDate();
        $is_exist = $reserveDateModel
            ->where("appointment",$begin_time)
            ->where("is_enable","1")
            ->find();
        $is_exist = json_decode(json_encode($is_exist),true);

        if (empty($is_exist)){
            $is_refund_sub = 0;//未设置特殊日期,退换押金
            return $is_refund_sub;
        }

        $is_refund_sub = $is_exist['is_refund_sub'];//是否可退押金 0退  1不退
        if ($is_refund_sub == "1"){
            $is_refund_sub = 1;//特殊日期,设置不退押金
        }else{
            $is_refund_sub = 0;//特殊日期,设置退押金
        }
        return $is_refund_sub;
    }

    /**
     * 创建预定定金缴费单
     * @param $suid
     * @param $uid
     * @param $subscription
     * @param $pay_type
     * @return bool
     */
    public function billSubscriptionCreate($suid,$uid,$trid,$subscription,$pay_type)
    {
        $billSubscriptionModel = new BillSubscription();
        $params = [
            'suid'          => $suid,
            'uid'           => $uid,
            'trid'          => $trid,
            'status'        => config("order.reservation_subscription_status")['pending_payment']['key'],
            'subscription'  => $subscription,
            'pay_type'      => $pay_type,
            'created_at'    => time(),
            'updated_at'    => time()
        ];
        $is_ok = $billSubscriptionModel
            ->insert($params);
        if ($is_ok){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 台位预定订单生成
     * @param $trid
     * @param $status
     * @param $type
     * @param $sid
     * @param $sname
     * @param $uid
     * @param $table_id
     * @param $reserve_way
     * @param $reserve_time
     * @param $reserve_people
     * @param $action_user
     * @param $suid
     * @param $subscription
     * @param $turnover_limit
     * @return bool
     */
    public function createRevenueOrder($trid,$status,$type,$sid,$sname,$uid,$table_id,$reserve_way,$reserve_time,$reserve_people,$action_user,$suid,$subscription,$turnover_limit)
    {
        $params = [
            "trid"           => $trid,
            "status"         => $status,
            "type"           => $type,
            "uid"            => $uid,
            "sid"            => $sid,
            "sname"          => $sname,
            "table_id"       => $table_id,
            "reserve_way"    => $reserve_way,
            "reserve_time"   => $reserve_time,
            "reserve_people" => $reserve_people,
            "action_time"    => time(),
            "action_user"    => $action_user,
            "suid"           => $suid,
            "subscription"   => $subscription,
            "turnover_limit" => $turnover_limit,
            "created_at"     => time(),
            "updated_at"     => time()
        ];

        $is_ok = $this->RevenueOrderC($params);

        return $is_ok;
    }

    /**
     * 更新或插入预约订单操作
     * @param array $params
     * @param null $trid
     * @return bool
     */
    protected function RevenueOrderC($params = array(),$trid = null)
    {
        $tableRevenueModel = new TableRevenue();
        if (empty($trid)){
            $is_ok = $tableRevenueModel
                ->insert($params);
        }else{
            $is_ok = $tableRevenueModel
                ->where('trid',$trid)
                ->update($params);
        }
        if ($is_ok){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 更新或插入开台信息
     * @param $params
     * @param $buid
     * @return bool
     */
    protected function updateOrInsertTableBusinessInfo($params,$buid = "")
    {
        $tableBusinessModel = new TableBusiness();
        if (empty($buid)){
            $res = $tableBusinessModel
                ->insert($params);
        }else{
            $res = $tableBusinessModel
                ->where("buid",$buid)
                ->update($params);
        }
        if ($res !== false) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 更新预约台位信息(取消预约)
     * @param array $params
     * @param $trid
     * @return bool
     */
    public function updatedTableRevenueInfo($params = array(),$trid)
    {
        $tableRevenueModel = new TableRevenue();
        $is_ok = $tableRevenueModel
            ->where("trid",$trid)
            ->update($params);

        if ($is_ok !== false){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 更新定金信息
     * @param array $params
     * @param $trid
     * @return bool
     */
    public function updatedBillSubscription($params = array(),$trid)
    {
        $billSubscriptionModel = new BillSubscription();
        $is_ok = $billSubscriptionModel
            ->where('trid',$trid)
            ->update($params);
        if ($is_ok){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取当前定金订单信息
     * @param $trid
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBillSubscriptionInfo($trid)
    {
        $billSubscriptionModel = new BillSubscription();
        $info = $billSubscriptionModel
            ->where('trid',$trid)
            ->find();
        $info = json_decode(json_encode($info),true);
        return $info;
    }

    /**
     * trid获取预约信息
     * @param $trid
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTableRevenueInfo($trid)
    {
        $tableRevenueModel = new TableRevenue();
        $res = $tableRevenueModel
            ->where("trid",$trid)
            ->find();
        $res = json_decode(json_encode($res),true);
        return $res;
    }

    /**
     * 取消支付释放桌台公共部分
     * @param $suid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function releaseTablePublic($suid)
    {
        if (empty($suid)){
            return $this->com_return(false,config("params.ABNORMAL_ACTION"));
        }
        $billSubscriptionModel = new BillSubscription();
        $billInfo = $billSubscriptionModel
            ->where("suid",$suid)
            ->where("status",\config("order.reservation_subscription_status")['pending_payment']['key'])
            ->find();
        $billInfo = json_decode(json_encode($billInfo),true);
        if (empty($billInfo)){
            return $this->com_return(true,\config("params.SUCCESS"));
        }
        $trid = $billInfo['trid'];
        Db::startTrans();
        try{
            //更新预约订台状态为交易取消
            $table_params = [
                "status"        => \config("order.table_reserve_status")['fail']['key'],
                "action_user"   => "user",
                "action_time"   => time(),
                "action_desc" => "取消支付",
                "updated_at"    => time()
            ];
            $this->updatedTableRevenueInfo($table_params,$trid);

            $bill_params = [
                "status"        => \config("order.reservation_subscription_status")['cancel']['key'],
                "cancel_user"   => "user",
                "cancel_time"   => time(),
                "auto_cancel"   => 0,
                "cancel_reason" => "取消支付",
                "updated_at"    => time()
            ];
            $this->updatedBillSubscription($bill_params,$trid);
            Db::commit();
            return $this->com_return(true,\config("params.SUCCESS"));
        }catch (Exception $e){
            Db::rollback();
            return $this->com_return(false,$e->getMessage());
        }
    }

    /**
     * 取消预约公共部分
     * @param $trid
     * @param $tableRevenueInfo
     * @return array
     */
    public function cancelReservationPublic($trid,$tableRevenueInfo)
    {
        $time = time();
        //获取当前台位信息

        $status = $tableRevenueInfo['status'];//获取当前台位状态

        if ($status == \config("order.table_reserve_status")['open']['key']){
            return $this->com_return(false,"已开台,不可取消预约");
        }
        if ($status == \config("order.table_reserve_status")['fail']['key']){
            return $this->com_return(false,"预定未成功,不可取消预约");
        }
        if ($status == \config("order.table_reserve_status")['cancel']['key']){
            return $this->com_return(false,"已取消,不可重复操作");
        }

        try {
            $type = $tableRevenueInfo['type'];//是否收取定金1 是  0否
            if ($status == \config("order.table_reserve_status")['pending_payment']['key']){
                //如果是待付款状态,
                if ($type != 0){
                    //如果收取定金
                    $table_params = [
                        "status"        => \config("order.table_reserve_status")['cancel']['key'],
                        "action_user"   => "user",
                        "action_time"   => $time,
                        "action_desc"   => "未付款时,用户手动取消",
                        "updated_at"    => $time
                    ];
                    $tableReturn = $this->updatedTableRevenueInfo($table_params,$trid);
                    if (!$tableReturn) {
                        return $this->com_return(false,\config("params.FAIL"));
                    }

                    $bill_params = [
                        "status"        => \config("order.reservation_subscription_status")['cancel']['key'],
                        "cancel_user"   => "user",
                        "cancel_time"   => $time,
                        "auto_cancel"   => 0,
                        "cancel_reason" => "未付款时,用户手动取消",
                        "updated_at"    => $time
                    ];
                    $billReturn  = $this->updatedBillSubscription($bill_params,$trid);
                    if (!$billReturn) {
                        return $this->com_return(false,\config("params.FAIL"));
                    }
                }else{
                    $table_params = [
                        "status"        => \config("order.table_reserve_status")['cancel']['key'],
                        "action_user"   => "user",
                        "action_time"   => $time,
                        "action_desc"   => "无需缴纳定金,用户手动取消",
                        "updated_at"    => $time
                    ];
                    $tableReturn = $this->updatedTableRevenueInfo($table_params,$trid);
                    if (!$tableReturn) {
                        return $this->com_return(false,\config("params.FAIL"));
                    }
                }
            }else{
                //如果是已付款状态
                if ($type == \config("order.reserve_type")['no_sub']['key']){
                    //如果没收取定金
                    $table_params = [
                        "status"        => \config("order.table_reserve_status")['cancel']['key'],
                        "action_user"   => "user",
                        "action_time"   => $time,
                        "action_desc"   => "已预约,不用支付定金,用户手动取消",
                        "updated_at"    => $time
                    ];
                    $tableReturn = $this->updatedTableRevenueInfo($table_params,$trid);
                    if (!$tableReturn) {
                        return $this->com_return(false,\config("params.FAIL"));
                    }
                }elseif ($type == \config("order.reserve_type")['sub']['key']){
                    //收了押金,且可退
                    //获取预约日期退押金的截止时间
                    $reserve_time = $tableRevenueInfo['reserve_time'];
                    $date = strtotime(date("Ymd",$reserve_time));
                    $reserve_cancel_time = $this->getReservationDateRefundTime($date);
                    if (empty($reserve_cancel_time)) {
                        //获取系统设置的最晚取消时间
                        $reserve_cancel_time = getSysSetting("reserve_cancel_time");
                    }

                    $kc_date  = date("Y-m-d");
                    $kc_time  = $kc_date." ".$reserve_cancel_time;//开餐时间
                    $kc_time  = strtotime($kc_time);
                    $now_time = time();

                    if ($now_time > $kc_time){
                        //如果退款时,已超时,则不退定金
                        $table_params = [
                            "status"        => \config("order.table_reserve_status")['cancel']['key'],
                            "action_user"   => "user",
                            "action_time"   => $time,
                            "action_desc"   => "已付款,超出取消时间范围内,用户手动取消",
                            "updated_at"    => $time
                        ];
                        $tableReturn = $this->updatedTableRevenueInfo($table_params,$trid);
                        if (!$tableReturn) {
                            return $this->com_return(false,\config("params.FAIL"));
                        }

                        $bill_params = [
                            "cancel_user"   => "user",
                            "cancel_time"   => $time,
                            "auto_cancel"   => 0,
                            "cancel_reason" => "已付款,超出取消时间范围内,用户手动取消",
                            "is_refund"     => 0,
                            "updated_at"    => $time
                        ];
                        $billReturn  = $this->updatedBillSubscription($bill_params,$trid);
                        if (!$billReturn) {
                            return $this->com_return(false,\config("params.FAIL"));
                        }
                    }else{
                        //如果退款时,未超时,则退还定金
                        $billInfo = $this->getBillSubscriptionInfo($trid);
                        $subscription = $billInfo['subscription'];
                        $suid         = $billInfo['suid'];
                        $pay_type     = $billInfo['pay_type'];

                        $table_params = [
                            "status"        => \config("order.table_reserve_status")['cancel']['key'],
                            "action_user"   => "user",
                            "action_time"   => $time,
                            "action_desc"   => "已付款,未超出取消时间范围内,用户手动取消",
                            "refund"        => $subscription,
                            "updated_at"    => $time
                        ];
                        $tableReturn = $this->updatedTableRevenueInfo($table_params,$trid);
                        if (!$tableReturn) {
                            return $this->com_return(false,\config("params.FAIL"));
                        }

                        $bill_params = [
                            "status"        => \config("order.reservation_subscription_status")['refunded']['key'],
                            "cancel_user"   => "user",
                            "cancel_time"   => $time,
                            "auto_cancel"   => 0,
                            "cancel_reason" => "已付款,未超出取消时间范围内,用户手动取消",
                            "is_refund"     => 1,
                            "refund_amount" => $subscription,
                            "updated_at"    => $time
                        ];
                        $billReturn = $this->updatedBillSubscription($bill_params,$trid);
                        if (!$billReturn) {
                            return $this->com_return(false,\config("params.FAIL"));
                        }
                        //可以退款
                        //微信支付时,走退款接口
                        if ($pay_type == \config("order.pay_method")['wxpay']['key']){
                            $payRes = $this->callBackPay($suid,$subscription,$subscription);
                            if (!isset($payRes['result']) || !$payRes['result']) {
                                return $payRes;
                            }
                        }
                    }
                }elseif ($type == \config("order.reserve_type")['no_refund']['key']) {
                    //如果是 收取押金且取消不可退
                    $table_params = [
                        "status"        => \config("order.table_reserve_status")['cancel']['key'],
                        "action_user"   => "user",
                        "action_time"   => $time,
                        "action_desc"   => "用户手动取消,不可退还押金",
                        "updated_at"    => $time
                    ];
                    $tableReturn = $this->updatedTableRevenueInfo($table_params,$trid);
                    if (!$tableReturn) {
                        return $this->com_return(false,\config("params.FAIL"));
                    }

                    $bill_params = [
                        "cancel_user"   => "user",
                        "cancel_time"   => $time,
                        "auto_cancel"   => 0,
                        "cancel_reason" => "用户手动取消,不可退还押金",
                        "is_refund"     => 0,
                        "updated_at"    => $time
                    ];
                    $billReturn  = $this->updatedBillSubscription($bill_params,$trid);
                    if (!$billReturn) {
                        return $this->com_return(false,\config("params.FAIL"));
                    }
                }
            }
            return $this->com_return(true,\config("params.SUCCESS"));

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 统一模拟退款,组装参数
     * @param $order_id
     * @param $total_fee
     * @param $refund_fee
     * @return bool|mixed
     */
    public function callBackPay($order_id,$total_fee,$refund_fee)
    {
        $values = [
            'vid'          => $order_id,
            'total_fee'    => $total_fee,
            'refund_fee'   => $refund_fee,
            'out_refund_no' => $order_id,
        ];

        $res = $this->requestPost($values);

        return $res;

    }


    /**
     * 模拟post支付回调接口请求
     *
     * @param array $postParams
     * @return bool|mixed
     */
    protected function requestPost($postParams = array())
    {
        $request = Request::instance();

        $url = $request->domain()."/wechat/reFund";

        if (empty($url) || empty($postParams)) {
            return false;
        }

        $o = "";
        foreach ( $postParams as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }

        $postParams = substr($o,0,-1);


        $postUrl = $url;
        $curlPost = $postParams;

        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);

        $data = json_decode($data,true);

        return $data;
    }

    /**
     * 查看当前用户是否预约当前桌,并且是未开台状态
     * @param $uid
     * @param $table_id
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function userTableStatus($uid,$table_id)
    {
        $tableRevenueModel = new TableRevenue();
        $is_revenue = $tableRevenueModel
            ->where("uid",$uid)
            ->where("table_id",$table_id)
            ->where("status",config("order.table_reserve_status")['success']['key'])
            ->whereTime("reserve_time","today")
            ->field("trid,status,type,subscription,reserve_time")
            ->find();

        $is_revenue = json_decode(json_encode($is_revenue),true);

        return $is_revenue;
    }

    /**
     * 变更桌台状态
     * @param $trid
     * @param $status
     * @return bool
     */
    public function changeRevenueTableStatus($trid,$status)
    {
        $tableRevenueModel = new TableRevenue();
        if ($status == config("order.table_reserve_status")['open']['key']){
            $open_time = time();
        }else{
            $open_time = NULL;
        }
        $params = [
            "status"     => $status,
            "open_time"  => $open_time,
            "updated_at" => time()
        ];
        $is_ok = $tableRevenueModel
            ->where("trid",$trid)
            ->update($params);
        if ($is_ok !== false){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 更新预约桌台信息
     * @param array $params
     * @param $trid
     * @return bool
     */
    public function updateTableRevenueInfo($params = array(),$trid)
    {
        $tableRevenueModel = new TableRevenue();

        $is_ok = $tableRevenueModel
            ->where("trid",$trid)
            ->update($params);
        if ($is_ok !== false){
            return true;
        }else{
            return false;
        }

    }

    /**
     * 插入新的开台信息
     * @param $table_id
     * @param $uid
     * @param string $ssid
     * @param string $ssname
     * @param int $turnover_limit
     * @return array|bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function openTableNow($table_id,$uid,$ssid = "",$ssname = "",$turnover_limit = 0)
    {
        $buid = generateReadableUUID("BU");
        $tableCommonObj = new TableCommon();
        $tableInfo = $tableCommonObj->tableIdGetInfo($table_id);
        if (empty($tableInfo)) {
            return $this->com_return(false,\config("params.ABNORMAL_ACTION"));
        }
        $table_no = $tableInfo['table_no'];
        $sid      = $tableInfo['sid'];
        $sname    = $tableInfo['sales_name'];
        $params = [
            "buid"           => $buid,
            "uid"            => $uid,
            "table_id"       => $table_id,
            "table_no"       => $table_no,
            "status"         => \config('order.table_business_status')['open']['key'],
            "turnover_limit" => $turnover_limit,
            "ssid"           => $ssid,
            "ssname"         => $ssname,
            "sid"            => $sid,
            "sname"          => $sname,
            "turnover_num"   => 0,
            "turnover"       => 0,
            "open_time"      => time(),
            "created_at"     => time(),
            "updated_at"     => time(),
        ];
        $res = $this->updateOrInsertTableBusinessInfo($params);
        if ($res){
            return $buid;
        }else{
            return false;
        }
    }

    /**
     * 插入新的开拼信息
     * @param $uid
     * @param $table_id
     * @param $turnover_limit
     * @param string $ssid
     * @param string $ssname
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function insertSpellingTable($uid,$table_id,$turnover_limit,$ssid = "",$ssname = "")
    {
        $date_time = 1;//今天
        $reservationCommonObj = new ReservationCommon();
        $date_time_arr = $reservationCommonObj->getSysTimeLong($date_time);
        $begin_time    = $date_time_arr['beginTime'];
        $end_time      = $date_time_arr['endTime'];

        //查询当前桌台是否有开台信息
        $open_status  = \config('order.table_business_status')['open']['key'];
        $clean_status = \config('order.table_business_status')['clean']['key'];
        $status_str = "$open_status,$clean_status";
        $tableBusinessModel = new TableBusiness();
        $spelling_num = $tableBusinessModel
            ->where('status','IN',$status_str)
            ->where("table_id",$table_id)
            ->whereTime('open_time','between',[$begin_time,$end_time])
            ->count();

        $tableCommonObj = new TableCommon();
        $tableInfo = $tableCommonObj->tableIdGetInfo($table_id);
        $table_no = $tableInfo['table_no'];
        $sid      = $tableInfo['sid'];
        $sname    = $tableInfo['sales_name'];

        $number   = $spelling_num;
        $table_no = $table_no."(拼$number)";

        $buid = generateReadableUUID("BU");
        $params = [
            "buid"           => $buid,
            "uid"            => $uid,
            "table_id"       => $table_id,
            "table_no"       => $table_no,
            "status"         => \config('order.table_business_status')['open']['key'],
            "turnover_limit" => $turnover_limit,
            "ssid"           => $ssid,
            "ssname"         => $ssname,
            "sid"            => $sid,
            "sname"          => $sname,
            "open_time"      => time(),
            "created_at"     => time(),
            "updated_at"     => time()
        ];
        $businessCommonObj = new BusinessCommon();
        $res = $businessCommonObj->insertNewTableBusinessInfo($params);
        return $res;
    }

    /**
     * table_id判断桌台是否可被预约
     * @param $table_id
     * @param $reserve_time
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function tableIdCheckCanReservation($table_id,$reserve_time)
    {
        $start_time = strtotime(date("Y-m-d",$reserve_time));
        $end_time   = $start_time + 24 * 60 * 60 - 1;
        $date_time = "$start_time,$end_time";
        $reservationCommonObj = new ReservationCommon();
        $date_time_arr = $reservationCommonObj->getSysTimeLong($date_time);
        $begin_time    = $date_time_arr['beginTime'];
        $end_time      = $date_time_arr['endTime'];

        $pending_payment = config("order.table_reserve_status")['pending_payment']['key'];
        $re_success      = config("order.table_reserve_status")['success']['key'];
        $open            = config("order.table_reserve_status")['open']['key'];
        $status_str      = "$pending_payment,$re_success,$open";

        /*桌子预约状态筛选 On*/
        $tableRevenueModel = new TableRevenue();
        $tableStatusRes = $tableRevenueModel
            ->where('table_id',$table_id)
            ->where('status',"IN",$status_str)
            ->whereTime("reserve_time","between",["$begin_time","$end_time"])
            ->count();
        if ($tableStatusRes > 0 ){
            //已给预约
            return false;
        }else{
            /*桌子开台状态筛选 On*/
            $tableBusinessModel = new TableBusiness();
            $openTableStatusRes = $tableBusinessModel
                ->where('table_id',$table_id)
                ->where('status',config('order.table_business_status')['open']['key'])
                ->whereTime("open_time","between",["$begin_time","$end_time"])
                ->count();
            if ($openTableStatusRes > 0) {
                //已被开台
                return false;
            }else{
                return true;
            }
            /*桌子开台状态筛选 Off*/
        }
        /*桌子预约状态筛选 Off*/
    }
}