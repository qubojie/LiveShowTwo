<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 上午10:03
 */
namespace app\xcx_manage\controller\appointment;



use app\common\controller\ManageAuthAction;
use app\common\controller\ReservationCommon;
use app\common\controller\TableCommon;
use app\common\controller\UserCommon;
use app\common\model\BillPayAssist;
use app\common\model\ManageSalesman;
use app\common\model\User;
use think\Config;
use think\Db;
use think\Exception;
use think\Request;
use think\Response;
use think\Validate;

class ManageReservation extends ManageAuthAction
{
    /**
     * 手机号码获取用户姓名
     * @param Request $request
     * @return array
     */
    public function phoneGetUserName(Request $request)
    {
        $phone = $request->param("phone","");

        $rule = [
            "phone|客户电话" => "require|regex:1[0-9]{1}[0-9]{9}",
        ];
        $request_res = [
            "phone" => $phone,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        try {
            /*查询当前号码是否在营销职位 on*/
            $manageSalesModel = new ManageSalesman();

            //TODO 没有限制必须是在职员工,只要是员工表中存在的,就不能预约
            $isManage = $manageSalesModel
                ->alias("ms")
                ->join("mst_salesman_type mst","mst.stype_id = ms.stype_id")
                ->where("ms.phone",$phone)
                ->field("stype_key")
                ->find();
            $isManage = json_decode(json_encode($isManage),true);
            if (!empty($isManage)){
                $stype_key = $isManage['stype_key'];
                if ($stype_key == \config("salesman.salesman_type")[1]['key']){
                    return $this->com_return(false,\config("params.REVENUE")['PHONE_NOT_IS_SALES']);
                }
            }
            /*查询当前号码是否在营销职位 off*/

            $userModel = new User();
            $userNameRes = $userModel
                ->alias("u")
                ->join("user_card uc","uc.uid = u.uid","LEFT")
                ->join("mst_card_vip cv","cv.card_id = uc.card_id","LEFT")
                ->where("u.phone",$phone)
                ->field("u.name,u.account_balance,u.account_cash_gift")
                ->field("cv.card_name,cv.card_type")
                ->find();
            $userNameRes = json_decode(json_encode($userNameRes),true);
            if (empty($userNameRes)){
                return $this->com_return(true,\config("params.USER_NOT_EXIST"));
            }

            /*获取用户今日消费信息 On*/
            $nowDateTime          = strtotime(date("Ymd"));
            $sys_account_day_time = getSysSetting("sys_account_day_time");
            $six_s                = 60 * 60 * $sys_account_day_time;
            $nowDateTime          = $nowDateTime + $six_s;
            $beginTime            = date("YmdHis",$nowDateTime);
            $endTime              = date("YmdHis",$nowDateTime + 24 * 60 * 60 - 1);

            $date_where['bp.created_at'] = ["between time",["$beginTime","$endTime"]];

            $one                = config("bill_assist.bill_status")['1']['key'];
            $seven              = config("bill_assist.bill_status")['7']['key'];
            $sale_status_str    = "$one,$seven";

            $billPayAssistModel = new BillPayAssist();
            $list = $billPayAssistModel
                ->alias("bp")
                ->where("bp.phone",$phone)
                ->where($date_where)
                ->where("bp.sale_status","IN",$sale_status_str)
                ->group("bp.phone")
                ->field("sum(bp.account_balance) account_balance_sum,sum(bp.account_cash_gift) account_cash_gift_sum")
                ->find();
            $list = json_decode(json_encode($list),true);
            /*获取用户今日消费信息 Off*/

            if (!empty($list)){
                $userNameRes['account_balance_sum'] = $list['account_balance_sum'];
                $userNameRes['account_cash_gift_sum'] = $list['account_cash_gift_sum'];
            }
            return $this->com_return(true,\config("params.SUCCESS"),$userNameRes);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 可预约吧台列表
     * @param Request $request
     * @return array
     */
    public function tableList(Request $request)
    {
        $token         = $request->header("Token","");
        $customerPhone = $request->param("customerPhone","");//客户电话
        $customerName  = $request->param("customerName","");//客户姓名
        $location_id   = $request->param("location_id","");//位置id
        $appointment   = $request->param("appointment","");//预约日期
        $pagesize      = $request->param("pagesize",config('xcx_page_size'));//显示个数,不传时为10
        $nowPage       = $request->param("nowPage","1");
        $rule = [
            "customerPhone|客户电话" => "require|regex:1[0-9]{1}[0-9]{9}",
            "appointment|预约时间"   => "require",
        ];
        $request_res = [
            "customerPhone" => $customerPhone,
            "appointment"   => $appointment,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if (empty($pagesize)) $pagesize = config('xcx_page_size');
        $config = [
            "page" => $nowPage,
        ];
        try {
            /*权限判断 on*/
            $manageInfo = $this->tokenGetManageInfo($token);
            $statue     = $manageInfo['statue'];

            if ($statue != \config("salesman.salesman_status")['working']['key']){
                return $this->com_return(false,\config("params.MANAGE_INFO")['UsrLMT']);
            }
            /*权限判断 off*/

            //获取客户相关信息
            $customerInfo = $this->phoneGetCustomerInfo($customerPhone,$token,$customerName);

            $uid = $customerInfo['uid'];

            //根据uid 获取 Card
            $userCommonObj = new UserCommon();
            $card_info = $userCommonObj->uidGetCardInfo($uid);
            if (!empty($card_info)){
                $user_card_id = $card_info['card_id'];
            }else{
                $user_card_id = "";
            }

            $reservationCommonObj = new ReservationCommon();

            $res_data = $reservationCommonObj->reservationPublic("$location_id","$appointment","$user_card_id","$pagesize",$config);

            return $this->com_return(true,config("params.SUCCESS"),$res_data);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 服务人员预约确认
     * @param Request $request
     * @return array
     */
    public function reservationConfirm(Request $request)
    {
        $customerPhone  = $request->param("customerPhone","");//客户电话
        $table_id       = $request->param('table_id','');//桌位id
        $turnover_limit = $request->param('turnover_limit',0);//最低消费  0表示无最低消费
        $subscription   = $request->param('subscription',0);//预约定金
        $date           = $request->param('date','');//日期
        $time           = $request->param('time','');//时间
        $rule = [
            "customerPhone|客户电话"  => "require",
            "table_id|桌位"          => "require",
            "turnover_limit|最低消费" => "require",
            "subscription|预约定金"   => "require",
            "date|日期"              => "require",
            "time|时间"              => "require",
        ];
        $check_data = [
            "customerPhone"  => $customerPhone,
            "table_id"       => $table_id,
            "turnover_limit" => $turnover_limit,
            "subscription"   => $subscription,
            "date"           => $date,
            "time"           => $time,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        Db::startTrans();
        try {
            /*权限判断 on*/
            $token      = $request->header("Token","");
            $manageInfo = $this->tokenGetManageInfo($token);
            $statue     = $manageInfo['statue'];

            if ($statue != \config("salesman.salesman_status")['working']['key']){
                return $this->com_return(false,\config("params.MANAGE_INFO")['UsrLMT']);
            }
            /*权限判断 off*/

            //根据客户电话 获取客户id
            $userInfo   = $this->phoneGetCustomerInfo($customerPhone,$token);
            //根据token获取当前服务人员电话
            $sales_phone = $manageInfo['phone'];
            $action_user = $manageInfo['sales_name'];

            $uid = $userInfo['uid'];

            $reserve_way = Config::get("order.reserve_way")['service']['key'];//预定途径  client  客户预订（小程序）    service 服务端预定（内部小程序）   manage 管理端预定（pc管理端暂保留）
            $reservationCommonObj = new ReservationCommon();
            $res = $reservationCommonObj->confirmReservationPublic("$sales_phone","$table_id","$date","$time","$subscription","$turnover_limit","$reserve_way","$action_user","$uid");
            if (isset($res['result']) && $res['result']) {
                Db::commit();
            }
            return $res;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 管理员取消支付,释放桌台
     * @param Request $request
     * @return array
     */
    public function releaseTable(Request $request)
    {
        $suid = $request->param("vid","");
        try {
            $reservationCommonObj = new ReservationCommon();
            return $reservationCommonObj->releaseTablePublic($suid);
        } catch (Exception $e) {
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
        $trid  = $request->param("trid","");//台位id
        if (empty($trid)){
            return $this->com_return(false,\config("params.PARAM_NOT_EMPTY"));
        }
        Db::startTrans();
        try {
            /*权限判断 on*/
            $token = $request->header("Token",'');
            $manageInfo = $this->tokenGetManageInfo($token);
            $statue     = $manageInfo['statue'];

            if ($statue != \config("salesman.salesman_status")['working']['key']){
                return $this->com_return(false,\config("params.MANAGE_INFO")['UsrLMT']);
            }
            /*权限判断 off*/
            $tableCommonObj = new TableCommon();
            $tableInfo      = $tableCommonObj->tridGetTableInfo($trid);

            $reservationCommonObj = new ReservationCommon();
            $res                  = $reservationCommonObj->cancelReservationPublic($trid,$tableInfo);
            if ($res['result'] == false) {
                return $res;
            }
            /*记录日志 on*/
            $sales_name   = $manageInfo['sales_name'];
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
            //取消预约记录日志
            insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$sales_name",$desc,"","");
            /*记录日志 off*/
            Db::commit();
            return $this->com_return(true,\config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 根据电话获取客户信息
     * @param $phone
     * @param $token
     * @param string $customerName
     * @return array|false|mixed|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function phoneGetCustomerInfo($phone,$token,$customerName = "")
    {
        $userModel = new User();
        $u_column = $userModel->u_column;
        $userInfo = $userModel
            ->alias("u")
            ->where('phone',$phone)
            ->field($u_column)
            ->find();
        $userInfo = json_decode(json_encode($userInfo),true);
        if (empty($userInfo)){
            //此时是新用户,将此用户作为新用户录入会员表
            $userInfo = $this->newUserInsertTable($phone,$token,$customerName);
        }
        return $userInfo;
    }

    /**
     * 新用户信息插入
     * @param $phone
     * @param $token
     * @param string $customerName
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function newUserInsertTable($phone,$token,$customerName = "")
    {
        $uid            = generateReadableUUID("U");
        $password       = jmPassword(\config("DEFAULT_PASSWORD"));
        $register_way   = "wxapp";
        $user_status    = 0;
        $manageInfo     = $this->tokenGetManageInfo($token);
        $referrer_type  = $manageInfo['stype_key'];
        $referrer_id    = $manageInfo['sid'];
        $time           = time();

        $params = [
            "uid"           => $uid,
            "phone"         => $phone,
            "password"      => $password,
            "name"          => $customerName,
            "register_way"  => $register_way,
            "user_status"   => $user_status,
            "referrer_type" => $referrer_type,
            "referrer_id"   => $referrer_id,
            "created_at"    => $time,
            "updated_at"    => $time
        ];

        $userModel = new User();
        $u_column  = $userModel->u_column;
        $userModel->insert($params);

        $userInfo = $userModel
            ->alias("u")
            ->where('phone',$phone)
            ->field($u_column)
            ->find();
        return $userInfo;
    }
}