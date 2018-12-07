<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午5:36
 */
namespace app\xcx_member\controller\appointment;

use app\common\controller\MemberAuthAction;
use app\common\controller\ReservationCommon;
use app\common\controller\TableCommon;
use app\common\controller\UserCommon;
use think\Config;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class Reservation extends MemberAuthAction
{
    /**
     * 可预约吧台列表
     * @param Request $request
     * @return array
     */
    public function tableList(Request $request)
    {
        $pagesize    = $request->param("pagesize",config('xcx_page_size'));//显示个数,不传时为10
        $nowPage     = $request->param("nowPage","1");
        $location_id = $request->param("location_id","");//位置id
        $appointment = $request->param("appointment","");//预约时间
        if (empty($pagesize)) $pagesize = config('xcx_page_size');
        $rule = [
            "appointment|预约时间"  => "require",
        ];
        $request_res = [
            "appointment"   => $appointment,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }
        try {
            //查看当前用户办的卡
            $token          = $request->header("Token","");
            $uid            = $this->tokenGetUserInfo($token)['uid'];//获取uid
            $userCommonObj  = new UserCommon();
            $user_card_info = $userCommonObj->uidGetCardInfo($uid);//根据uid获取卡id
            if (!empty($user_card_info)){
                $user_card_id = $user_card_info['card_id'];
            }else{
                $user_card_id = "";
            }
            $config = [
                "page" => $nowPage,
            ];
            $reservationCommonObj = new ReservationCommon();
            $res_data = $reservationCommonObj->reservationPublic("$location_id","$appointment","$user_card_id","$pagesize",$config);
            return $this->com_return(true,config("params.SUCCESS"),$res_data);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 检测桌台是否可被预约
     * @return array
     */
    public function checkTableReservationCan()
    {
        $table_id = $this->request->param("table_id","");
        $date     = $this->request->param("date","");
        $rule = [
            "table_id|桌位" => "require",
            "date|日期"     => "require",
        ];
        $check_data = [
            "table_id" => $table_id,
            "date"     => $date,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }
        try {
            $reservationCommonObj = new ReservationCommon();
            $is_can_reserve = $reservationCommonObj->tableStatusCan($table_id,$date);
            if (!$is_can_reserve){
                //false时 该吧台当天已被其他顾客预约
                return $this->com_return(false,\config("params.TABLE_IS_RESERVE"));
            }

            return $this->com_return(true,\config("params.SUCCESS"));
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 预约确认
     * @param Request $request
     * @return array
     */
    public function reservationConfirm(Request $request)
    {
        $table_id       = $request->param('table_id','');//桌位id
        $turnover_limit = $request->param('turnover_limit',0);//最低消费  0表示无最低消费
        $subscription   = $request->param('subscription',0);//预约定金
        $date           = $request->param('date','');//日期
        $time           = $request->param('time','');//时间
        $rule = [
            "table_id|桌位"          => "require",
            "turnover_limit|最低消费" => "require",
            "subscription|预约定金"   => "require",
            "date|日期"              => "require",
            "time|时间"              => "require",
        ];
        $check_data = [
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
            $remember_token = $request->header("Token",'');
            //获取到预约人信息
            $userInfo = $this->tokenGetUserInfo($remember_token);
            $uid = $userInfo['uid'];

            $reserve_way = Config::get("order.reserve_way")['client']['key'];//预定途径  client  客户预订（小程序）    service 服务端预定（内部小程序）   manage 管理端预定（pc管理端暂保留）
            $reservationCommonObj = new ReservationCommon();
            $sales_phone = "";
            $res = $reservationCommonObj->confirmReservationPublic("$sales_phone","$table_id","$date","$time","$subscription","$turnover_limit","$reserve_way","user","$uid");
            if (isset($res['result']) && $res['result'] == true) {
                Db::commit();
            }
            return $res;
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
        $trid           = $request->param("trid","");//台位id
        if (empty($trid)){
            return $this->com_return(false,\config("params.PARAM_NOT_EMPTY"));
        }
        Db::startTrans();
        try {
            $tableCommonObj = new TableCommon();
            $tableRevenueInfo      = $tableCommonObj->tridGetTableInfo($trid);

            $reservationCommonObj = new ReservationCommon();
            $res                  = $reservationCommonObj->cancelReservationPublic($trid,$tableRevenueInfo);

            if (isset($res['result']) && $res['result'] == true) {
                Db::commit();
                /*记录日志 on*/
                $uid          = $tableRevenueInfo['uid'];
                $userInfo     = getUserInfo($uid);
                $userName     = $userInfo["name"];
                $userPhone    = $userInfo["phone"];
                $table_id     = $tableRevenueInfo['table_id'];
                $table_no     = $tableRevenueInfo['table_no'];
                $reserve_time = $tableRevenueInfo['reserve_time'];//预约时间
                $reserve_date = date("Y-m-d H:i:s",$reserve_time);
                $type         = config("order.table_action_type")['cancel_revenue']['key'];
                $desc         = " 用户 ".$userName."($userPhone)"." 手动取消 $reserve_date ".$table_no."桌的预约";
                //取消预约记录日志
                insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$userName",$desc,"","");
                /*记录日志 off*/
                return $this->com_return(true,\config("params.SUCCESS"));
            }else{
                return $res;
            }
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 用户主动取消支付,释放桌台
     * @param Request $request
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function releaseTable(Request $request)
    {
        $suid = $request->param("vid","");

        $reservationCommonObj = new ReservationCommon();

        return $reservationCommonObj->releaseTablePublic($suid);
    }
}