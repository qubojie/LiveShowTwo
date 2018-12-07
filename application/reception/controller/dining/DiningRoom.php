<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 下午4:12
 */

namespace app\reception\controller\dining;


use app\common\controller\BusinessCommon;
use app\common\controller\ConsumptionCommon;
use app\common\controller\ReceptionAuthAction;
use app\common\controller\ReservationCommon;
use app\common\controller\SalesUserCommon;
use app\common\controller\TableCommon;
use app\common\controller\UserCommon;
use app\common\model\MstTable;
use app\common\model\TableBusiness;
use app\common\model\TableRevenue;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class DiningRoom extends ReceptionAuthAction
{
    /**
     * 获取今日订台列表
     * @param Request $request
     * @return array
     */
    public function todayTableInfo(Request $request)
    {
        $keyword       = $request->param("keyword","");//关键字搜索
        $location_id   = $request->param("location_id","");//位置id

        $location_where = [];
        if (!empty($location_id)){
            $location_where['ta.location_id'] = $location_id;
        }
        $where = [];
        if (!empty($keyword)){
            $where["t.table_no|ta.area_title|tl.location_title|tap.appearance_title|u.name|u.phone|u.nickname"] = ["like","%$keyword%"];
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
            $re_success = config("order.table_reserve_status")['success']['key'];
            $open_table = config("order.table_reserve_status")['open']['key'];
            $status_str = "$re_success,$open_table";

            for ($i = 0; $i <count($tableInfo); $i ++){
                $table_id = $tableInfo[$i]['table_id'];//桌号id
                /*桌子预约状态筛选 On*/
                $tableStatusRes = $tableRevenueModel
                    ->where('table_id',$table_id)
                    ->where('status',"IN",$status_str)
                    ->whereTime("reserve_time","between",["$begin_time","$end_time"])
                    ->find();
                $tableStatusRes = json_decode(json_encode($tableStatusRes),true);

                $tableInfo[$i]['is_join'] = 0;//是否拼台
                if (!empty($tableStatusRes)){
                    $reserve_time = $tableStatusRes['reserve_time'];
                    $table_status =  $tableStatusRes['status'];
                    $tableInfo[$i]['is_overtime'] = 0;//未超时

                    if ($table_status == 0){
                        $tableInfo[$i]['table_status'] = 1;//预约代付定金
                    }elseif ($table_status == 1){
                        $tableInfo[$i]['table_status'] = 1;//已被预约
                        /*超时判断 on*/
                        if ($reserve_time < time()){
                            $tableInfo[$i]['is_overtime'] = 1;//已超时
                        }
                        /*超时判断 off*/
                    }elseif ($table_status == 2){
                        $tableInfo[$i]['table_status'] = 2;//已开台
                    }else{
                        $tableInfo[$i]['table_status'] = 0;//空
                    }

                    $reserve_time = date("H:i",$reserve_time);
                    $tableInfo[$i]['reserve_time'] = $reserve_time;

                }else{
                    $tableInfo[$i]['is_overtime']  = 0;
                    $tableInfo[$i]['table_status'] = 0;
                    $tableInfo[$i]['reserve_time'] = 0;

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
                    }
                }
                /*桌子状态筛选 off*/

                /*桌子限制筛选 on*/
                if ($tableInfo[$i]['reserve_type'] == config("table.reserve_type")['0']['key']){
                    //无限制
                    $tableInfo[$i]['is_limit'] = 0;
                }else{
                    $tableInfo[$i]['is_limit'] = 1;
                }
                /*桌子限制筛选 off*/

            }
            return $this->com_return(true,config("params.SUCCESS"),$tableInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 查看桌位详情
     * @param Request $request
     * @return array
     */
    public function tableInfo(Request $request)
    {
        $table_id = $request->param("table_id","");//桌台id
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

            $date = time();
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

            $tableRevenueModel = new TableRevenue();
            $revenue_column = $tableRevenueModel->revenue_column;
            /*预约基本信息 On*/
            $mainRevenueInfo = $tableRevenueModel
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
            $mainRevenueInfo = json_decode(json_encode($mainRevenueInfo),true);
            $tableInfo['mainRevenueInfo'] = $mainRevenueInfo;
            /*此桌开台信息 On*/
            //查看当前桌位是否是开台桌,如果是,则返回开台信息
            $tableBusinessModel = new TableBusiness();
            $openTableInfo = $tableBusinessModel
                ->alias("tb")
                ->join("user u","u.uid = tb.uid","LEFT")
                ->join("user_card uc","uc.uid = tb.uid","LEFT")
                ->join("mst_user_level ul","ul.level_id = u.level_id","LEFT")
                ->join("manage_salesman ms","ms.sid = tb.ssid","LEFT")
                ->where("tb.table_id",$table_id)
                ->where("tb.status",config('order.table_business_status')['open']['key'])
                ->whereTime("tb.open_time","between",["$begin_time","$end_time"])
                ->field("u.name,u.phone user_phone,u.nickname,u.level_id,u.account_point,u.credit_point")
                ->field("ul.level_name")
                ->field("uc.card_name,uc.card_type")
                ->field("ms.phone sales_phone")
                ->field("tb.buid,tb.status table_status,tb.turnover_limit,tb.ssname,tb.sname,tb.open_time,tb.clean_time,tb.turnover,tb.turnover_num,tb.refund_num,tb.refund_amount")
                ->select();
            $openTableInfo = json_decode(json_encode($openTableInfo),true);
            if (!empty($openTableInfo)){
                $tableInfo['mainOpenInfo']  = $openTableInfo;
            }else{
                $tableInfo['mainOpenInfo'] = NULL;
            }
            /*此桌开台信息 Off*/

            /*预约基本信息 Off*/

            $now_time = strtotime(date("Ymd",time()));
            $begin_time = $now_time * 10000;
            $end_time   = ($now_time + 24 * 60 * 60) * 10000;
            $tableJournal = Db::name("table_log")
                ->alias("tl")
                ->where("tl.table_id",$table_id)
                ->where('tl.log_time',['>',$begin_time],['<',$end_time],'and')
                ->order("tl.log_time DESC")
                ->field("log_time,action_user,desc")
                ->select();

            $tableJournal = json_decode(json_encode($tableJournal),true);

            for ($i = 0; $i < count($tableJournal); $i ++){
                $log_time = $tableJournal[$i]['log_time'];
                $tableJournal[$i]['log_time'] = substr($log_time,"0","10");
            }
            $tableInfo['table_journal'] = $tableJournal;
            $tableInfo = _unsetNull($tableInfo);
            return $this->com_return(true,config("params.SUCCESS"),$tableInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 手机号码检索
     * @param Request $request
     * @return array
     */
    public function phoneRetrieval(Request $request)
    {
        $type  = $request->param("type","");//类型,user为用户;sales为员工
        $phone = $request->param("phone","");//电话号码

        try {
            $userCommonObj = new UserCommon();
            if ($type == "user"){
                //用户检索
                $res =  $userCommonObj->userPhoneRetrieval($phone);
                if (empty($res)){
                    return $this->com_return(false,config("params.USER")['USER_NOT_EXIST']);
                }
            }elseif($type == "sales"){
                //员工检索
                $res = $userCommonObj->salesPhoneRetrieval($phone);
                if (empty($res)){
                    return $this->com_return(false,config("params.USER")['USER_NOT_EXIST']);
                }
            }else{
                return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
            }
            //将数组中的 Null  转换为 "" 空字符串
            $res = _unsetNull($res);
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }

    }

    /**
     * 开台
     * @param Request $request
     * @return array|mixed
     */
    public function openTable(Request $request)
    {
        $table_id       = $request->param("table_id","");//桌id
        $user_phone     = $request->param("user_phone","");//客户电话
        $user_name      = $request->param("user_name","");//客户姓名
        $sales_phone    = $request->param("sales_phone","");//营销电话
        $turnover_limit = $request->param("turnover_limit","");//低消

        $rule = [
            "table_id|桌台"       => "require",
            "turnover_limit|低消" => "require",
            "user_phone|客户电话"  => "regex:1[0-9]{1}[0-9]{9}",
            "sales_phone|营销电话" => "regex:1[0-9]{1}[0-9]{9}",
        ];
        $request_res = [
            "table_id"       => $table_id,
            "turnover_limit" => $turnover_limit,
            "user_phone"     => $user_phone,
            "sales_phone"    => $sales_phone,
        ];
        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        Db::startTrans();
        try {
            /*登陆管理人员信息 on*/
            $token = $request->header("Token",'');
            $manageInfo = $this->receptionTokenGetManageInfo($token);
            $stype_name = $manageInfo["stype_name"];
            $sales_name = $manageInfo["sales_name"];

            $adminUser = $stype_name . " ". $sales_name;
            /*登陆管理人员信息 off*/

            $tableCommonObj = new TableCommon();
            /*获取桌台信息 on*/
            $tableInfo = $tableCommonObj->tableIdGetInfo($table_id);
            $table_no = $tableInfo['table_no'];
            /*获取桌台信息 off*/

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
                        "password"      => sha1(config("DEFAULT_PASSWORD")),
                        "register_way"  => config("user.register_way")['web']['key'],
                        "user_status"   => config("user.user_register_status")['register']['key'],
                        "referrer_type" => $referrer_type,
                        "referrer_id"   => $referrer_id,
                        "created_at"    => time(),
                        "updated_at"    => time()

                    ];
                    //插入新的用户信息
                    $insertNewUserRes = $userCommonObj->insertNewUser($user_params);
                    if ($insertNewUserRes === false) {
                        return $this->com_return(false,config("params.FAIL"));
                    }
                }else{
                    $uid = $userInfo['uid'];
                }
            }

            if (!empty($uid)){
                $reservationCommonObj = new ReservationCommon();
                //查看当前用户是否预约当前桌,并且是未开台状态
                $is_revenue = $reservationCommonObj->userTableStatus($uid,$table_id);

                if (!empty($is_revenue)){
                    //如果是当前用户当天预约
                    $status = $is_revenue['status'];
                    $trid   = $is_revenue["trid"];//预约桌台id
                    $res =  $this->isUserRevenueOpen("$trid","$status","$table_id","$turnover_limit","$sales_name","$uid","$user_name","$user_phone","$referrer_id","$referrer_name","$table_no");
                }else{
                    //不是预约用户开台
                    $res = $this->notIsUserRevenueOpen("$table_id","$uid","$referrer_id","$referrer_name","$user_name","$user_phone","$table_no","$sales_name","$turnover_limit");
                }
            }else{
                //直接开台
                $res =  $this->notRegisterUserOpen("$table_id","$table_no","$referrer_id","$referrer_name","$turnover_limit");
            }

            if (isset($res['result']) && $res['result']){
                Db::commit();
            }
            return $res;
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }


    /**
     * 是当前用户预约开台
     * @param $trid
     * @param $status
     * @param $table_id
     * @param $turnover_limit
     * @param $sales_name
     * @param $uid
     * @param $user_name
     * @param $user_phone
     * @param $referrer_id
     * @param $referrer_name
     * @param $table_no
     * @return array|mixed
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    protected function isUserRevenueOpen($trid,$status,$table_id,$turnover_limit,$sales_name,$uid,$user_name,$user_phone,$referrer_id,$referrer_name,$table_no)
    {
        if ($status != config("order.table_reserve_status")['success']['key']){
            return $this->com_return(false,config("params.REVENUE")['STATUS_NO_OPEN']);
        }
        //是当前用户当天预约桌台,更改当前桌台为 开台状态

        $reservationCommonObj = new ReservationCommon();

        /*首先创建开台信息 On*/
        $buid = $reservationCommonObj->openTableNow("$table_id","$uid","$referrer_id","$referrer_name","$turnover_limit");
        if ($buid == false){
            return $this->com_return(false,config("params.FAIL"));
        }
        /*首先创建开台信息 Off*/

        /*获取预约时押金 On*/
        $subscriptionRes = $reservationCommonObj->getTableRevenueInfo($trid);
        $subscription = $subscriptionRes['subscription'];
        /*获取预约时押金 Off*/

        /*更新预约信息信息 On*/
        $table_revenue_params = [
            "status" => config("order.table_reserve_status")['open']['key'],
            "refund" => $subscription,
            "buid"   => $buid
        ];
        $openTable = $reservationCommonObj->updatedTableRevenueInfo($table_revenue_params,"$trid");
        /*更新预约信息信息 Off*/

        if ($openTable){
            /*如果开台成功,查看当前用户是否为定金预约用户,如果是则执行退款*/
            //如果预约定金类型为定金 1
            if ($subscription > 0){
                //此时执行开台成功,定金退还操作
                $suid_info = Db::name("bill_subscription")
                    ->where("trid",$trid)
                    ->field("suid")
                    ->find();
                $suid_info = json_decode(json_encode($suid_info),true);
                $suid      = $suid_info["suid"];
                $refund_return = $this->refundDeposit($suid,$subscription);
                $res = json_decode($refund_return,true);
                if (isset($res["result"]) && $res["result"]){
                    //退款成功则变更定金状态
                    $status = config("order.reservation_subscription_status")['refunded']['key'];
                    $params = [
                        "status"        => $status,
                        "is_refund"     => 1,
                        "refund_amount" => $subscription,
                        "updated_at"    => time()
                    ];
                    $updateBillRes = Db::name("bill_subscription")
                        ->where("suid",$suid)
                        ->update($params);
                    if ($updateBillRes === false){
                        return $this->com_return(false,config("params.FAIL"));
                    }
                }else{
                    return $res;
                }
            }

            /*if ($subscription_type == config("order.subscription_type")['order']['key']){
                //如果预约方式为订单,则调起打印机,打印订单

                //获取当前预约订台 已支付的点单信息
                $pid_res = Db::name("bill_pay")
                    ->where("trid",$trid)
                    ->where("sale_status",config("order.bill_pay_sale_status")['completed']['key'])
                    ->field("pid")
                    ->find();

                $pid_res = json_decode(json_encode($pid_res),true);

                $pid = $pid_res['pid'];

                //TODO 这里调用打印机有点问题,[ error ] [10501]SQLSTATE[42000]: Syntax error or access violation: 1055 Expression #1 of SELECT list is not in GROUP BY clause and contains nonaggregated column 'elegant.bpd.pid' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by[/home/wwwroot/ls.wxapp.api.yshvip.cn/thinkphp/library/think/db/Connection.php:383]

                $is_print = $this->openTableToPrintYly($pid);
//                $is_print = json_encode($is_print);
                $dateTimeFile = APP_PATH."index/PrintOrderYly/".date("Ym")."/";
                if (!is_dir($dateTimeFile)){
                    $res = @mkdir($dateTimeFile,0777,true);
                }
                //打印结果日志
                error_log(date('Y-m-d H:i:s').var_export($is_print,true),3,$dateTimeFile.date("d").".log");
            }*/

            /*记录开台日志 on*/
            $type = config("order.table_action_type")['open_table']['key'];
            $desc = " 为用户 ".$user_name."($user_phone)"." 开 ".$table_no."桌的预约";
            insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$sales_name",$desc,"","");
            /*记录开台日志 off*/
            //预约用户开台成功
            return $this->com_return(true,config("params.SUCCESS"));
        }else{
            return $this->com_return(false,config("params.FAIL"));
        }
    }

    /**
     * 不是预约用户开台
     * @param $table_id
     * @param $uid
     * @param $referrer_id
     * @param $referrer_name
     * @param $user_name
     * @param $user_phone
     * @param $table_no
     * @param $sales_name
     * @param $turnover_limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function notIsUserRevenueOpen($table_id,$uid,$referrer_id,$referrer_name,$user_name,$user_phone,$table_no,$sales_name,$turnover_limit)
    {
        //不是当前用户的预约
        //查看当前桌,并且是可开台状态
        $tableCommonObj = new TableCommon();
        $can_open_or_revenue = $tableCommonObj->tableIdCheckCanOpenOrRevenue("$table_id",time());
        if (!$can_open_or_revenue){
            //此时不可开台
            return $this->com_return(false,config("params.REVENUE")['DO_NOT_OPEN']);
        }

        $reservationCommonObj     = new ReservationCommon();
        $insertTableRevenueReturn = $reservationCommonObj->openTableNow("$table_id","$uid","$referrer_id","$referrer_name","$turnover_limit");
        if ($insertTableRevenueReturn != false){
            /*记录开台日志 on*/
            $type = config("order.table_action_type")['open_table']['key'];
            $desc = " 为用户 ".$user_name."($user_phone)"." 开 ".$table_no."桌";
            insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$sales_name",$desc,"","");
            /*记录开台日志 off*/
            //非预约用户开台成功
            return $this->com_return(true,config("params.SUCCESS"));
        }else{
            return $this->com_return(false,config("params.FAIL"));
        }
    }

    /**
     * 直接开台
     * @param $table_id
     * @param $table_no
     * @param $referrer_id
     * @param $referrer_name
     * @param int $turnover_limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function notRegisterUserOpen($table_id,$table_no,$referrer_id,$referrer_name,$turnover_limit = 0)
    {
        $tableCommonObj = new TableCommon();
        //未注册用户开台
        //查看当前桌,并且是可开台状态
        $can_open_or_revenue = $tableCommonObj->tableIdCheckCanOpenOrRevenue($table_id,time());
        if (!$can_open_or_revenue) {
            //此时不可开台
            return $this->com_return(false,config("params.REVENUE")['DO_NOT_OPEN']);
        }

        //此时直接开台
        $reservationCommonObj = new ReservationCommon();
        $insertRevenueReturn = $reservationCommonObj->openTableNow("$table_id","","$referrer_id","$referrer_name","$turnover_limit");

        if ($insertRevenueReturn){
            /*记录开台日志 on*/
            $type = config("order.table_action_type")['open_table']['key'];
            $desc = "直接"." 开 ".$table_no."桌";
            insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$referrer_name",$desc,"","");
            /*记录开台日志 off*/
            //未录入任何信息直接开台成功
            return $this->com_return(true,config("params.SUCCESS"));
        }else{
            return $this->com_return(false,config("params.FAIL"));
        }
    }

    /**
     * 取消开台
     * @param Request $request
     * @return array
     */
    public function cancelOpenTable(Request $request)
    {
        $buid = $request->param("buid","");
        if (empty($buid)){
            return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
        }
        try {
            /*查询当前台位是否已经点单,如果已点单,则不允许取消开台 On*/
            $consumptionCommonObj = new ConsumptionCommon();
            $tableBusinessInfo    = $consumptionCommonObj->buidGetTableBusinessInfo("$buid");
            if (empty($tableBusinessInfo)) {
                return $this->com_return(false,config("params.DO_NOT_CANCEL_OPEN"));
            }
            $turnover_num = $tableBusinessInfo['turnover_num'];//订单数量
            if ($turnover_num > 0){
                //已点单,不可取消开台
                return $this->com_return(false,config("params.REVENUE")['POINT_LIST_NO_CANCEL']);
            }
            /*查询当前台位是否已经点单,如果已点单,则不允许取消开台 Off*/

            $token = $request->header("Token","");
            $adminInfo = $this->receptionTokenGetManageInfo("$token");
            $cancel_user = $adminInfo['sales_name'];

            $params = [
                "status"        => config("order.table_business_status")['cancel']['key'],
                "cancel_user"   => $cancel_user,
                "cancel_time"   => time(),
                "cancel_reason" => "已开台未点单,前台取消开台",
                "updated_at"    => time()
            ];

            $businessCommonObj = new BusinessCommon();
            $is_ok = $businessCommonObj->updateTableBusinessInfo($params,"$buid");

            if ($is_ok !== false){
                /*记录取消开台日志 on*/
                $table_id = $tableBusinessInfo['table_id'];
                $table_no = $tableBusinessInfo['table_no'];
                $type  = config("order.table_action_type")['cancel_table']['key'];
                $desc  = config("order.table_reserve_status")['cancel']['name'];
                insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$cancel_user",$desc,"","");
                /*记录取消开台日志 off*/
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 补充基本信息(修改客信)
     * @param Request $request
     * @return array
     */
    public function supplementRevenueInfo(Request $request)
    {
//        $trid        = $request->param("trid","");//台位预约id
        $buid        = $request->param("buid","");//台位营业id

        $user_phone  = $request->param("user_phone","");
        $user_name   = $request->param("user_name","");
        $sales_phone = $request->param("sales_phone","");
        $time = time();
        $rule = [
            "user_phone|客户电话"  => "require|regex:1[0-9]{1}[0-9]{9}",
            "sales_phone|营销电话" => "regex:1[0-9]{1}[0-9]{9}",
        ];
        $request_res = [
            "user_phone"  => $user_phone,
            "sales_phone" => $sales_phone
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        try {
            /*营销信息获取 on*/
            $referrer_id   = config("salesman.salesman_type")['3']['key'];
            $referrer_type = config("salesman.salesman_type")['3']['key'];
            $referrer_name = config("salesman.salesman_type")['3']['name'];
            if (!empty($sales_phone)){
                //获取营销信息
                $salesUserCommonOjb = new SalesUserCommon();
                $manageInfo = $salesUserCommonOjb->phoneGetSalesmanInfo($sales_phone);
                if (!empty($manageInfo)){
                    $referrer_id   = $manageInfo["sid"];
                    $referrer_type = $manageInfo["stype_key"];
                    $referrer_name = $manageInfo["sales_name"];
                }
            }
            /*营销信息获取 off*/

            /*客户信息获取 on*/
            if (!empty($user_phone)){
                //根据用户电话获取用户信息
                $userCommonObj = new UserCommon();
                $userInfo      = $userCommonObj->uidOrPhoneGetUserInfo($user_phone);
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
                    $res = $userCommonObj->insertNewUser($user_params);
                    if ($res === false) {
                        return $this->com_return(false,config("params.FAIL"));
                    }
                }else{
                    $uid = $userInfo['uid'];
                   /* $user_params = [
                        "phone"  => $user_phone,
                        "name"   => $user_name,
                    ];
                    $userCommonObj->updateUserInfo($user_params,"$uid");*/
                }
            }
            /*客户信息获取 off*/

            $res = false;

            /*修改开台订单信息 On*/
            if(!empty($buid)) {
                $update_params = [
                    "uid"        => $uid,
                    "ssid"       => $referrer_id,
                    "ssname"     => $referrer_name,
                    "updated_at" => $time
                ];
                $businessCommonObj = new BusinessCommon();
                $res = $businessCommonObj->updateTableBusinessInfo($update_params,"$buid");
            }
            /*修改开台订单信息 Off*/
            if ($res){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(true,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }


    /**
     * 定金退款
     * @param $suid
     * @param $subscription
     * @return bool|mixed
     */
    public function refundDeposit($suid,$subscription)
    {
        $postParams = [
            "vid"           => $suid,
            "total_fee"     => $subscription,
            "refund_fee"    => $subscription,
            "out_refund_no" => $suid
        ];
        $request = Request::instance();
        $url = $request->domain()."/wechat/reFund";
        if (empty($url)) {
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
        return $data;
    }
}