<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午4:25
 */
namespace app\xcx_member\controller\personal;



use app\common\controller\AgeConstellation;
use app\common\controller\MemberAuthAction;
use app\common\controller\UserCommon;
use app\common\controller\VoucherCommon;
use app\common\model\BcUserInfo;
use app\common\model\BillPay;
use app\common\model\BillPayDetail;
use app\common\model\JobUser;
use app\common\model\MstMerchant;
use app\common\model\MstTableImage;
use app\common\model\MstUserLevel;
use app\common\model\TableRevenue;
use app\common\model\User;
use app\common\model\UserCard;
use app\common\model\UserGiftVoucher;
use think\Db;
use think\Exception;
use think\Request;

class MyInfo extends MemberAuthAction
{
    /**
     * 用户中心
     * @return array
     */
    public function index()
    {
        $token = $this->request->header('Token','');
        try {
            $userModel = new User();
            $column = $userModel->column;
            foreach ($column as $key => $val){
                $column[$key] = "u.".$val;
            }
            $user_info = $userModel
                ->alias("u")
                ->join("user_info ui","ui.uid = u.uid","LEFT")
                ->where('u.remember_token',$token)
                ->field('ui.birthday')
                ->field($column)
                ->find();
            /*对生日进行处理 On*/
            $birthday    = $user_info['birthday'];
            if (!empty($birthday)){
                $by = substr($birthday,0,2);
                if ($by <100 && $by >= 40){
                    $birthday = "19".$birthday;
                }else{
                    $birthday = "20".$birthday;
                }
                $user_info['birthday'] = $birthday;
            }
            /*对生日进行处理 Off*/

            $uid         = $user_info['uid'];
            $user_status = $user_info['user_status'];

            $jobUserModel = new JobUser();
            $userJobInfo = $jobUserModel
                ->where('uid',$uid)
                ->field("job_balance,job_freeze,job_cash,consume_amount,referrer_num")
                ->find();
            $userJobInfo = json_decode(json_encode($userJobInfo),true);
            if (!empty($userJobInfo)){
                foreach ($userJobInfo as $key => $val){
                    $user_info["$key"] = $val;
                }
            }else{
                $user_info["job_balance"] = 0;
                $user_info["job_freeze"] = 0;
                $user_info["job_cash"] = 0;
                $user_info["consume_amount"] = 0;
                $user_info["referrer_num"] = 0;
            }

            if ($user_status == 2){
                //获取用户的开卡信息
                $userCardModel = new UserCard();
                $cardInfo = $userCardModel
                    ->alias("uc")
                    ->join("mst_card_vip cv","cv.card_id = uc.card_id")
                    ->field("cv.card_id,cv.card_type,cv.card_name,cv.card_image,cv.card_amount,cv.card_deposit,cv.card_desc,cv.card_equities,uc.created_at open_card_time")
                    ->where('uc.uid',$uid)
                    ->find();
                $cardInfo = json_decode(json_encode($cardInfo),true);

                if (!empty($cardInfo)){
                    foreach ($cardInfo as $key => $val){
                        $user_info["$key"] = $val;
                    }
                }
            }

            $levelModel = new MstUserLevel();
            $level_info =$levelModel
                ->where('level_id', $user_info['level_id'])
                ->field('level_name,level_desc,level_img,point_min')
                ->find();
            $user_info['level_name'] = $level_info['level_name'];
            $user_info['level_desc'] = $level_info['level_desc'];
            $user_info['level_img']  = $level_info['level_img'];
            $user_info['point_min']  = $level_info['point_min'];

            //获取用户礼券数量
            $giftVoucherModel = new UserGiftVoucher();
            $gift_voucher_num = $giftVoucherModel
                ->where('uid',$uid)
                ->where("status",config("voucher.status")['0']['key'])
                ->count();
            $user_info['gift_voucher_num'] = $gift_voucher_num;

            //获取用户预约数量
            $tableRevenueModel = new TableRevenue();
            $user_revenue_num = $tableRevenueModel
                ->where("uid",$uid)
                ->where("status",config("order.table_reserve_status")['reserve_success']['key'])
                ->count();
            $user_info['revenue_num'] = $user_revenue_num;

            //获取商户数量
            $merchantModel = new MstMerchant();
            $merchant_num = $merchantModel->count();
            $user_info['merchant_num'] = $merchant_num;
            return $this->com_return(true,config("SUCCESS"),$user_info);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取充值按钮是否显示开关
     * @return array
     */
    public function getRefillSwitch()
    {
        try {
            $res = (int)$this->getSysSettingInfo("user_refill_switch");
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 修改个人信息
     * @return array
     */
    public function changeInfo()
    {
        $params = $this->request->param();
        if (empty($params)){
            return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
        }
        $token    = $this->request->header("Token");
        $userInfo = $this->tokenGetUserInfo($token);
        if ($userInfo === false){
            return $this->com_return(false,config("params.ABNORMAL_ACTION"));
        }
        $uid = $userInfo['uid'];

        Db::startTrans();
        try{
            /*根据生日处理生肖  On*/
            if (isset($params['birthday']) && !empty($params['birthday'])){
                $birthday = $params['birthday'];
                $birthday = substr($birthday,2,6);

                $constellationObj             = new AgeConstellation();
                $nxs                          = $constellationObj->getInfo($birthday);
                $astro                        = $nxs['constellation'];//星座
                $userInfoParams['birthday']   = $birthday;
                $userInfoParams['astro']      = $astro;
                $userInfoParams['updated_at'] = time();

                $userInfoModel = new BcUserInfo();

                $is_exist = $userInfoModel
                    ->where('uid',$uid)
                    ->count();
                if ($is_exist > 0){
                    $is_ok = $userInfoModel
                        ->where("uid",$uid)
                        ->update($userInfoParams);
                }else{
                    $userInfoParams['created_at'] = time();
                    $userInfoParams['uid'] = $uid;

                    $is_ok = $userInfoModel
                        ->insert($userInfoParams);
                }

                if ($is_ok === false){
                    return $this->com_return(false,config("params.ABNORMAL_ACTION"));
                }
            }
            /*根据生日处理生肖  Off*/

            if (isset($params['name']) || isset($params['sex'])){
                if (isset($params['birthday'])){
                    unset($params['birthday']);
                }
                $params['updated_at'] = time();
                $userModel = new User();
                $is_true = $userModel
                    ->where("uid",$uid)
                    ->update($params);
                if ($is_true === false){
                    return $this->com_return(false,config("params.ABNORMAL_ACTION"));
                }
            }

            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));

        }catch (Exception $e){
            Db::rollback();
            return $this->com_return(false,$e->getMessage());
        }
    }

    /**
     * 我的钱包明细
     * @param Request $request
     * @return array
     */
    public function wallet(Request $request)
    {
        try {
            $token         = $request->header('Token','');
            $userCommonObj = new UserCommon();
            $uid_res       = $this->tokenGetUserInfo($token);
            $uid           = $uid_res['uid'];
            $cash_gift     = $userCommonObj->uidGetAccountCashGift($uid);
            $account       = $userCommonObj->uidGetAccount($uid);

            $res['account']   = $account;
            $res['cash_gift'] = $cash_gift;

            return $this->com_return(true,config("SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 礼品券列表
     * @param Request $request
     * @return array
     */
    public function giftVoucher(Request $request)
    {
        $token  = $request->header('Token','');
        $status = $request->param('status','0');//礼券状态  0有效待使用  1 已使用  9已过期
        try {
            $uid_res = $this->tokenGetUserInfo($token);
            $uid = $uid_res['uid'];

            $voucherCommonObj = new VoucherCommon();
            $res = $voucherCommonObj->uidGetVoucher($uid,$status);
            return $this->com_return(true,config("SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 我的预约列表
     * @return array
     */
    public function reservationOrder()
    {
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage","1");
        $token    =  $this->request->header('Token','');
        $status   = $this->request->param("status",'');//  0待付定金或结算   1 预定成功   2已开台  3已清台   9取消预约

        if (empty($status)){
            $status = 0;
        }
        $where_status['status'] = ["eq",$status];
        $config = [
            "page" => $nowPage,
        ];

        try {
            $uid  = $this->tokenGetUserInfo($token)['uid'];//获取uid
            $tableRevenueModel = new TableRevenue();
            $list = $tableRevenueModel
                ->alias("tr")
                ->join("mst_table t","t.table_id = tr.table_id")
                ->join("mst_table_area ta","ta.area_id = t.area_id")
                ->join("mst_table_location tl","ta.location_id = tl.location_id")
                ->join("mst_table_appearance tap","tap.appearance_id = t.appearance_id")
                ->join("manage_salesman ms","ms.sid = tr.sid","LEFT")
                ->where('tr.uid',$uid)
                ->where($where_status)
                ->field("tr.trid,tr.table_id,t.table_no,tr.status,tr.reserve_time,tr.subscription,tr.turnover_limit,tr.type,tr.reserve_way,tr.sid,tr.sname")
                ->field("ms.phone")
                ->field("tl.location_title")
                ->field("ta.area_title")
                ->field("tap.appearance_title")
                ->paginate($pagesize,false,$config);

            $list = json_decode(json_encode($list),true);

            $data = $list["data"];

            $tableImageModel = new MstTableImage();
            for ($i = 0; $i <count($data); $i++){
                $table_id = $data[$i]['table_id'];
                $type     = $data[$i]['type'];

                if ($type == config("order.reserve_type")['no_refund']['key']){
                    //不退
                    $list["data"][$i]['is_refund_sub_msg'] = getSysSetting("reserve_warning_no");
                }else{
                    //退
                    $list["data"][$i]['is_refund_sub_msg'] = getSysSetting("reserve_warning");
                }
                $tableImage = $tableImageModel
                    ->where('table_id',$table_id)
                    ->field("image")
                    ->select();
                $tableImage = json_decode(json_encode($tableImage),true);
                for ($m = 0; $m < count($tableImage); $m++){
                    $list["data"][$i]['image_group'][] = $tableImage[$m]['image'];
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 我的订单列表
     * @return array
     */
    public function dishOrder()
    {
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage","1");
        $config = [
            "page" => $nowPage,
        ];
        $token =  $this->request->header('Token','');
        $uid   = $this->tokenGetUserInfo($token)['uid'];//获取uid

        try {
            $billPayModel = new BillPay();
            $column = $billPayModel->column;
            foreach ($column as $k => $v){
                $column[$k] = "bp.".$v;
            }
            $list = $billPayModel
                ->alias("bp")
                ->join("table_revenue tr","tr.trid = bp.trid")
                ->where("bp.uid",$uid)
                ->order("bp.created_at DESC")
                ->field("tr.table_no")
                ->field($column)
                ->paginate($pagesize,false,$config);

            $list = json_decode(json_encode($list),true);

            $billPayDetail = new BillPayDetail();
            $bill_pay_column = $billPayDetail->column;
            foreach ($bill_pay_column as $k => $v){
                $bill_pay_column[$k] = "bp.".$v;
            }

            for ($i = 0; $i < count($list['data']); $i ++){
                $pid = $list['data'][$i]['pid'];
                $bill_pay_detail = $billPayDetail
                    ->alias("bp")
                    ->join("dishes d","d.dis_id = bp.dis_id")
                    ->where("bp.pid",$pid)
                    ->field("d.dis_img,d.dis_desc")
                    ->field($bill_pay_column)
                    ->select();
                $bill_pay_detail = json_decode(json_encode($bill_pay_detail),true);
                $bill_pay_detail = make_tree($bill_pay_detail,"id","parent_id");
                $list['data'][$i]['bill_pay_count'] = count($bill_pay_detail);
                $list['data'][$i]['bill_pay_detail'] = $bill_pay_detail;
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 我的积分以及排行
     * @return array
     */
    public function myPointDetails()
    {
        $token = $this->request->header("Token");

        try {
            $userModel = new User();
            /*我的积分 On*/
            $userPointInfo = $userModel
                ->alias("u")
                ->join("mst_user_level ul","ul.level_id = u.level_id","LEFT")
                ->where("u.remember_token",$token)
                ->field("u.account_point")
                ->field("ul.level_name")
                ->find();
            $userPointInfo = json_decode(json_encode($userPointInfo),true);
            if (empty($userPointInfo)){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 001");
            }
            $account_point = $userPointInfo['account_point'];
            $level_name    = $userPointInfo['level_name'];
            /*我的积分 Off*/

            /*用户积分排行前10位 On*/
            $userPointInfo = $userModel
                ->where("account_point",">",0)
                ->order("account_point DESC")
                ->field("uid,name,nickname,avatar,sex,account_point")
                ->limit(20)
                ->select();
            $userPointInfo = json_decode(json_encode($userPointInfo),true);
            /*用户积分排行前10位 Off*/

            /*获取比例 On*/
            $all_num = $userModel
                ->count();
            $lt_num = $userModel
                ->where("account_point","<",$account_point)
                ->count();

            $percentage = round($lt_num / ($all_num - 1) * 100,2);
            /*获取比例 Off*/

            $res['account_point']   = $account_point;
            $res['level_name']      = $level_name;
            $res['percentage']      = $percentage;
            $res['user_point_list'] = $userPointInfo;
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}