<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 下午2:59
 */
namespace app\reception\controller\dining;

use app\common\controller\ReceptionAuthAction;
use app\common\controller\UserCommon;
use app\common\model\BillPayAssist;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class BillPayAssistInfo extends ReceptionAuthAction
{
    /**
     * 消费列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $keyword        = $request->param("keyword","");
        $dateTime       = $request->param("dateTime","");//时间
        $sale_status    = $request->param("sale_status","");// 0待扣款   1 扣款完成  8 已退款    9交易取消
        $pagesize       = $request->param("page_size","");
        $nowPage        = $request->param("nowPage","1");
        $is_show_cancel = $request->param("is_show_cancel","");//是否显示取消订单
        $rule = [
            "dateTime|时间"    => "require",
            "sale_status|状态" => "require",
        ];
        $request_res = [
            "dateTime"    => $dateTime,
            "sale_status" => $sale_status,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        if (empty($pagesize))  $pagesize = config("page_size");
        if (empty($nowPage)) $nowPage = 1;
        $config = [
            "page" => $nowPage,
        ];

        try {
            $billPayAssistModel = new BillPayAssist();
            $r_column = $billPayAssistModel->r_column;
            foreach ($r_column as $key => $val){
                $r_column[$key] = "bpa.".$val;
            }
            $where = [];
            if (!empty($keyword)){
                $where['bpa.phone|bpa.verification_code|bpa.table_no'] = ['like',"%$keyword%"];
            }
            $sales_status_where = [];
            if (strlen($sale_status)){
                if ($sale_status == 100){
                    if ($is_show_cancel){
                        //是否显示取消订单
                        $sales_status_where = [];
                    }else{
                        $sales_status_where["bpa.sale_status"] = ["neq",config("bill_assist.bill_status")['9']['key']];
                    }
                }else{
                    $sales_status_where["bpa.sale_status"] = ["eq",$sale_status];
                }
            }

            $dateTimeRes = $this->getSysTimeLong($dateTime);
            $beginTime   = $dateTimeRes['beginTime'];
            $endTime     = $dateTimeRes['endTime'];

            $date_where['bpa.created_at'] = ["between time",["$beginTime","$endTime"]];
            $list = $billPayAssistModel
                ->alias("bpa")
                ->join("user u","u.uid = bpa.uid","LEFT")
                ->join("user_gift_voucher ugv","ugv.gift_vou_code = bpa.gift_vou_code","LEFT")
                ->where($where)
                ->where($sales_status_where)
                ->where($date_where)
                ->order("bpa.created_at DESC")
                ->field($r_column)
                ->field("u.name,u.account_balance as user_account_balance,u.account_cash_gift as user_account_cash_gift")
                ->field("ugv.gift_vou_id,ugv.gift_vou_type,ugv.gift_vou_name,ugv.gift_vou_desc")
                ->paginate($pagesize,false,$config);
            $list = json_decode(json_encode($list),true);
            //账户余额统计
            $account_balance_sum = $billPayAssistModel
                ->alias("bpa")
                ->where($where)
                ->where($sales_status_where)
                ->where($date_where)
                ->sum("bpa.account_balance");
            //账户礼金统计
            $account_cash_gift_sum = $billPayAssistModel
                ->alias("bpa")
                ->where($where)
                ->where($sales_status_where)
                ->where($date_where)
                ->sum("bpa.account_cash_gift");
            //账户现金统计
            $account_cash_sum = $billPayAssistModel
                ->alias("bpa")
                ->where($where)
                ->where($sales_status_where)
                ->where($date_where)
                ->sum("bpa.cash");
            $list["account_balance_sum"]   = $account_balance_sum;
            $list["account_cash_gift_sum"] = $account_cash_gift_sum;
            $list["account_cash_sum"]      = $account_cash_sum;
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 前台新增待处理订单数据
     * @param Request $request
     * @return array
     */
    public function insertWaitDoOrder(Request $request)
    {
        $table_no = $request->param("table_no","");//桌号
        $phone    = $request->param("phone","");//电话号码
        $rule = [
            "table_no|桌号" => "require",
            "phone|电话号码" => "require|regex:1[3-9]{1}[0-9]{9}",
        ];
        $request_res = [
            "table_no" => $table_no,
            "phone"    => $phone,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        Db::startTrans();
        try {
            /*检测当前号码是否是用户 On*/
            $userCommonObj = new UserCommon();
            $userInfo = $userCommonObj->uidOrPhoneGetUserInfo($phone);
            if (empty($userInfo)){
                //用户不存在
                return $this->com_return(false,config("params.PHONE_NOT_EXIST"));
            }
            $uid  = $userInfo['uid'];
            $referrer_id = $userInfo['referrer_id'];
            //获取用户办卡信息
            $userCardInfo = Db::name("user_card")
                ->alias("uc")
                ->join("mst_card_vip cv","cv.card_id = uc.card_id")
                ->field("cv.card_name")
                ->find();
            if (empty($userCardInfo)){
                $card_name = "非会员";
            }else{
                $card_name = $userCardInfo['card_name'];
            }
            /*检测当前号码是否是用户 Off*/

            /*检测桌号是否存在 On*/
            $tableInfo = Db::name("mst_table")
                ->where("table_no",$table_no)
                ->where("is_enable",1)
                ->where("is_delete",0)
                ->find();
            if (empty($tableInfo)){
                return $this->com_return(false,config("params.TABLE")['TABLE_NOT_EXIST']);
            }
            $table_id = $tableInfo['table_id'];
            /*检测桌号是否存在 Off*/

            $token      = $request->header("Token");
            $manageInfo = $this->receptionTokenGetManageInfo($token);
            $sid   = $manageInfo['sid'];
            $sname = $manageInfo['sales_name'];

            $pid = generateReadableUUID("P");

            $params = [
                "pid"               => $pid,
                "uid"               => $uid,
                "card_name"         => $card_name,
                "phone"             => $phone,
                "verification_code" => '0000',
                "table_id"          => $table_id,
                "table_no"          => $table_no,
                "sid"               => $sid,
                "sname"             => $sname,
                "type"              => config("bill_assist.bill_type")['0']['key'],
                "sale_status"       => config("bill_assist.bill_status")['0']['key'],
                "referrer_id"       => $referrer_id,
                "created_at"        => time(),
                "updated_at"        => time()
            ];
            $billAssistModel = new BillPayAssist();
            $is_ok = $billAssistModel
                ->insert($params);
            if ($is_ok){
                Db::commit();
                return $this->com_return(true,config("params.SUCCESS"),$params);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 确认消费Or取消消费
     * @param Request $request
     * @return array
     */
    public function cancelOrConfirm(Request $request)
    {
        $action          = $request->param("action","");//1:确认; 2:取消
        $balance_money   = (int)$request->param("balance_money","");//余额消费金额
        $cash_gift_money = (int)$request->param("cash_gift_money","");//礼金消费金额
        $cash_money      = (int)$request->param("cash_money","");//现金消费金额
        $pid             = $request->param("pid","");
        $token           = $request->header("Token");

        try {
            if ($action == "1"){
                //确认
                return $this->confirm($token,$pid,$balance_money,$cash_gift_money,$cash_money);
            }elseif ($action == "2"){
                //取消
                return $this->cancel($pid,$token);
            }else{
                return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 确认消费
     * @param $token
     * @param $pid
     * @param $balance_money
     * @param $cash_gift_money
     * @param $cash_money
     * @return array
     */
    protected function confirm($token,$pid,$balance_money,$cash_gift_money,$cash_money)
    {
        $rule = [
            "pid|订单id"              => "require",
            "balance_money|余额金额"   => "require|number",
            "cash_gift_money|礼金金额" => "require|number",
        ];
        $request_res = [
            "pid"             => $pid,
            "balance_money"   => $balance_money,
            "cash_gift_money" => $cash_gift_money,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if ($balance_money <= 0 && $cash_gift_money <= 0 && $cash_money <= 0){
            return $this->com_return(false,config("params.ORDER")['MONEY_NOT_ZERO']);
        }
        Db::startTrans();
        try {
            $pidInfo = $this->checkPidStatus($pid);

            if (!$pidInfo['result']){
                return $pidInfo;
            }
            $uid = $pidInfo["data"]['uid'];

            $userInfo = getUserInfo($uid);

            if (empty($userInfo)){
                return $this->com_return(false,config("params.USER")['USER_NOT_EXIST']);
            }
            $manageInfo        = $this->receptionTokenGetManageInfo($token);
            $action_user       = $manageInfo['sales_name'];
            $account_balance   = $userInfo['account_balance'];//余额账户
            $account_cash_gift = $userInfo['account_cash_gift'];//礼金账户

            if ($cash_gift_money > $account_cash_gift){
                //礼金账户余额不足
                return $this->com_return(false,config("params.ORDER")['GIFT_NOT_ENOUGH']);
            }

            /*余额消费 on*/
            if ($balance_money > $account_balance){
                //钱包余额不足
                return $this->com_return(false,config("params.ORDER")['BALANCE_NOT_ENOUGH']);
            }
            $user_new_account_balance = $account_balance - $balance_money;

            if ($balance_money > 0){
                //余额消费
                $balanceActionReturn = $this->balanceAction($uid,$pid,$balance_money,$user_new_account_balance,$action_user);
                if (!$balanceActionReturn){
                    return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 001");
                }
            }
            /*余额消费 off*/

            /*礼金消费 on*/
            //消费后礼金余额
            $new_cash_gift = $account_cash_gift - $cash_gift_money;
            if ($cash_gift_money > 0){
                //礼金消费
                $cashGiftActionReturn = $this->cashGiftAction($uid,$pid,$cash_gift_money,$new_cash_gift,$action_user);
                if (!$cashGiftActionReturn){
                    return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 002");
                }
            }
            /*礼金消费 off*/


            /*更新用户信息 On*/
            $userParams = [
                "account_balance"   => $user_new_account_balance,
                "account_cash_gift" => $new_cash_gift,
                "updated_at"        => time()
            ];
            $userCommonObj = new UserCommon();
            $updateUserInfo = $userCommonObj->updateUserInfo($userParams,$uid);
            if ($updateUserInfo === false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION"). "- 012");
            }
            /*更新用户信息 Off*/

            /*订单状态变更 On*/
            $billPayAssistParams = [
                "sale_status"           => config("bill_assist.bill_status")['1']['key'],
                "pay_time"              => time(),
                "check_user"            => $action_user,
                "check_time"            => time(),
                "check_reason"          => "确认消费",
                "account_balance"       => $balance_money,
                "account_cash_gift"     => $cash_gift_money,
                "cash"                  => $cash_money,
                "updated_at"            => time()
            ];

            $is_ok = Db::name("bill_pay_assist")
                ->where("pid",$pid)
                ->update($billPayAssistParams);
            if ($is_ok === false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION"). "- 013");
            }
            /*订单状态变更 Off*/

            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 取消消费
     * @param $pid
     * @param $token
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function cancel($pid,$token)
    {
        if (empty($pid)){
            return $this->com_return(false,config("params.PARAM_NOT_EMPTY")." - 001");
        }

        $pidInfo = $this->checkPidStatus($pid);

        if (!$pidInfo['result']){
            return $pidInfo;
        }

        $manageInfo = $this->receptionTokenGetManageInfo($token);
        $cancel_user = $manageInfo['sales_name'];
        $params = [
            "sale_status"   => config("bill_assist.bill_status")['9']['key'],
            "cancel_user"   => $cancel_user,
            "cancel_time"   => time(),
            "auto_cancel"   => 0,
            "cancel_reason" => "手动取消",
            "check_user"    => $cancel_user,
            "check_time"    => time(),
            "check_reason"  => "手动取消",
            "updated_at"    => time(),
        ];

        $billPayAssistModel = new BillPayAssist();

        $is_ok = $billPayAssistModel
            ->where("pid",$pid)
            ->update($params);
        if ($is_ok !== false){
            return $this->com_return(true,config("params.SUCCESS"));
        }else{
            return $this->com_return(false,config("params.FAIL"));
        }
    }

    /**
     * 检查订单状态
     * @param $pid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function checkPidStatus($pid)
    {
        $billPayAssistModel = new BillPayAssist();

        $info = $billPayAssistModel
            ->where("pid",$pid)
            ->find();

        $info = json_decode(json_encode($info),true);

        if (empty($info)){
            return $this->com_return(false,config("params.ORDER")['ORDER_NOT_EXIST']);
        }

        $sale_status = $info['sale_status'];

        if ($sale_status == config("bill_assist.bill_status")['9']['key']) {
            return $this->com_return(false,config("params.ORDER")['ORDER_CANCEL']);
        }

        if ($sale_status == config("bill_assist.bill_status")['1']['key']){
            return $this->com_return(false,config("params.ORDER")['completed']);
        }

        return $this->com_return(true,config("params.SUCCESS"),$info);
    }

    /**
     * 余额明细操作
     * @param $uid
     * @param $pid
     * @param $balance_money
     * @param $new_account_balance
     * @param $action_user
     * @return array|bool
     */
    protected function balanceAction($uid,$pid,$balance_money,$new_account_balance,$action_user)
    {
        /*余额消费明细 on*/
        //插入用户余额消费明细
        //余额明细参数
        $insertUserAccountParams = [
            "uid"          => $uid,
            "balance"      => "-" . $balance_money,
            "last_balance" => $new_account_balance,
            "change_type"  => '1',
            "action_user"  => $action_user,
            "action_type"  => config('user.account')['consume']['key'],
            "oid"          => $pid,
            "deal_amount"  => $balance_money,
            "action_desc"  => config('user.account')['consume']['name'],
            "created_at"   => time(),
            "updated_at"   => time()
        ];

        $userCommonObj = new UserCommon();

        //插入用户余额消费明细
        $insertUserAccountReturn = $userCommonObj->updateUserAccount($insertUserAccountParams);
        if ($insertUserAccountReturn == false) {
            return false;
        }
        /*余额消费明细 off*/

        /*用户余额信息更新 on*/
        $userParams = [
            "account_balance" => $new_account_balance,
            "updated_at"      => time()
        ];
        $updateUserInfoReturn = $userCommonObj->updateUserInfo($userParams,$uid);
        if ($updateUserInfoReturn == false){
            return false;
        }
        /*用户余额信息更新 off*/

        return true;
    }

    /**
     * 礼金消费明细操作
     * @param $uid
     * @param $pid
     * @param $cash_gift_money
     * @param $new_cash_gift
     * @param $action_user
     * @return array|bool
     */
    protected function cashGiftAction($uid,$pid,$cash_gift_money,$new_cash_gift,$action_user)
    {
        /*礼金消费明细 on*/
        $userAccountCashGiftParams = [
            'uid'            => $uid,
            'cash_gift'      => '-'.$cash_gift_money,
            'last_cash_gift' => $new_cash_gift,
            'change_type'    => '1',
            'action_user'    => $action_user,
            'action_type'    => config('user.gift_cash')['consume']['key'],
            'action_desc'    => config('user.gift_cash')['consume']['name'],
            'oid'            => $pid,
            'created_at'     => time(),
            'updated_at'     => time()
        ];

        $userCommonObj = new UserCommon();
        //给用户添加礼金明细
        $userAccountCashGiftReturn = $userCommonObj->updateUserAccountCashGift($userAccountCashGiftParams);
        if ($userAccountCashGiftReturn == false) {
            return false;
        }
        /*礼金消费明细 off*/

        /*用户礼金账户信息更新 on*/
        $userParams = [
            "account_cash_gift" => $new_cash_gift,
            "updated_at"        => time()
        ];
        $updateUserInfoReturn = $userCommonObj->updateUserInfo($userParams,$uid);
        if ($updateUserInfoReturn == false){
            return false;
        }
        /*用户礼金账户信息更新 off*/
        return true;
    }

    /**
     * 确认Or取消使用礼券
     * @param Request $request
     * @return array|mixed
     */
    public function cancelOrConfirmVoucher(Request $request)
    {
        $action = $request->param("action","");//1:确认; 2:取消
        $pid    = $request->param("pid","");//订单id
        $token  = $request->header("Token");

        try {
            if ($action == "1"){
                //确认
                return $this->confirmVoucher($pid,$token);
            }elseif ($action == "2"){
                //取消
                return $this->cancel($pid,$token);
            }else{
                return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 确认使用礼券
     * @param $pid
     * @param $token
     * @return array|mixed
     */
    protected function confirmVoucher($pid,$token)
    {
        Db::startTrans();
        try {
            $pidInfo = $this->checkPidStatus($pid);
            if (!$pidInfo['result'])  return $pidInfo;

            $pidInfo       = $pidInfo["data"];
            $uid           = $pidInfo['uid'];
            $gift_vou_code = $pidInfo['gift_vou_code'];//券码

            $userInfo = getUserInfo($uid);
            if (empty($userInfo)){
                return $this->com_return(false,config("params.USER")['USER_NOT_EXIST']);
            }
            $manageInfo = $this->receptionTokenGetManageInfo($token);

            /*更新订单 on*/
            $billPayAssistParams = [
                "sale_status"   => config("bill_assist.bill_status")['1']['key'],
                "pay_time"      => time(),
                "check_user"    => $manageInfo['sales_name'],
                "check_time"    => time(),
                "check_reason"  => "确认使用礼券",
                "updated_at"    => time()
            ];

            $billPayAssistUpdate = Db::name("bill_pay_assist")
                ->where("pid",$pid)
                ->update($billPayAssistParams);

            if ($billPayAssistUpdate == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 001");
            }
            /*更新订单 off*/

            /*更新礼券 on*/
            $voucherInfo = Db::name("user_gift_voucher")
                ->where("gift_vou_code",$gift_vou_code)
                ->find();

            $voucherInfo = json_decode(json_encode($voucherInfo),true);

            if (empty($voucherInfo)){
                return $this->com_return(false,config("params.VOUCHER")['VOUCHER_NOT_EXIST']);
            }

            if ($voucherInfo['status'] != config("voucher.status")['0']['key']){
                return $this->com_return(false,config("params.VOUCHER")['VOUCHER_NOT_EXIST']);
            }

            if ($voucherInfo['gift_vou_type'] == config("voucher.type")['0']['key']){
                //单次使用
                $status = config("voucher.status")['1']['key'];

            }else{
                $status = config("voucher.status")['0']['key'];
            }

            $voucherParams = [
                "status"      => $status,
                "use_time"    => time(),
                "review_user" => $manageInfo['sales_name'],
                "updated_at"  => time()
            ];
            $voucherUpdate = Db::name("user_gift_voucher")
                ->where("gift_vou_code",$gift_vou_code)
                ->update($voucherParams);

            if ($voucherUpdate == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 002");
            }
            /*更新礼券 off*/
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));

        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 退款
     * @param Request $request
     * @return array
     */
    public function fullRefund(Request $request)
    {
        $pid              = $request->param("pid","");
        $re_balance_money = $request->param("re_balance_money","");//余额退款部分
        $re_cash_gift     = $request->param("re_cash_gift","");//礼金退款部分
        $re_cashs         = $request->param("re_cashs","");//现金退款部分
        $check_reason     = $request->param("check_reason","");//退款原因
        $rule = [
            "pid|订单id"               => "require",
            "re_balance_money|储值余额" => "require|number",
            "re_cash_gift|礼金金额"     => "require|number",
            "re_cash|现金金额"          => "require|number",
        ];
        $request_res = [
            "pid"              => $pid,
            "re_balance_money" => $re_balance_money,
            "re_cash_gift"     => $re_cash_gift,
            "re_cash"          => $re_cashs,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }
        if ($re_balance_money <= 0 && $re_cash_gift <= 0 && $re_cashs <= 0){
            return $this->com_return(false,config("params.ORDER")['MONEY_NOT_ZERO']);
        }

        Db::startTrans();
        try {
            $billPayAssistModel = new BillPayAssist();
            $r_column = $billPayAssistModel->r_column;
            foreach ($r_column as $key => $val){
                $r_column[$key] = "bpa.".$val;
            }
            $billInfo = $billPayAssistModel
                ->alias("bpa")
                ->join("user u","u.uid = bpa.uid","LEFT")
                ->join("job_user ju","ju.uid = u.uid","LEFT")
                ->where("bpa.pid",$pid)
                ->field($r_column)
                ->field("ju.job_balance")
                ->field("u.name,u.account_point,u.level_id,u.account_balance as user_account_balance,u.account_cash_gift as user_account_cash_gift")
                ->find();
            $billInfo = json_decode(json_encode($billInfo),true);
            if (empty($billInfo)){
                return $this->com_return(false,config("params.ORDER")['ORDER_NOT_EXIST']);
            }
            $is_settlement = $billInfo['is_settlement'];
            if ($is_settlement){
                return $this->com_return(false,config("params.ORDER")['SETTLEMENTED_NOT_REFUND']);
            }
            $type = $billInfo['type'];
            if ($type == config("bill_assist.bill_type")['6']['key']){
                //礼券消费不可取消
                return $this->com_return(false,config("params.ORDER")['VOUCHER_NOT_REFUND']);
            }
            $sale_status = $billInfo['sale_status'];//订单状态
            if ($sale_status != config("bill_assist.bill_status")['1']['key'] && $sale_status != config("bill_assist.bill_status")['7']['key']){
                //如果不是已完成状态,不可进行全额退款操作
                return $this->com_return(false,config("params.ORDER")['REFUND_DISH_ABNORMAL']);
            }
            $token       = $request->header("Token","");
            $manageInfo  = $this->receptionTokenGetManageInfo($token);
            $action_user = $manageInfo['sales_name'];

            $uid                    = $billInfo['uid'];//用户id
            $account_balance        = $billInfo['account_balance'];//消费储值金额
            $account_cash_gift      = $billInfo['account_cash_gift'];//消费礼金余额
            $cash                   = $billInfo['cash'];//现金消费金额
            $re_account_balance     = $billInfo['re_account_balance'];//退还储值消费金额数
            $re_account_cash_gift   = $billInfo['re_account_cash_gift'];//退还礼金消费金额数
            $re_cash                = $billInfo['re_cash'];//退还现金消费金额数
            $user_account_balance   = $billInfo['user_account_balance'];//用户现有储值金额
            $user_account_cash_gift = $billInfo['user_account_cash_gift'];//用户现有礼金金额

            /*更新单据数据 On*/
            $new_re_account_balance   = $re_account_balance + $re_balance_money;//退还金额
            $new_re_account_cash_gift = $re_account_cash_gift + $re_cash_gift;//退还礼金
            $new_re_cash              = $re_cash + $re_cashs;//退还现金
            $new_account_balance = $account_balance - $re_balance_money;//新的储值消费数
            if ($new_account_balance < 0){
                return $this->com_return(false,config("params.ORDER")['RE_BALANCE_MONEY_D']);
            }
            $new_account_cash_gift = $account_cash_gift - $re_cash_gift;//新的礼金消费数
            if ($new_account_cash_gift < 0){
                return $this->com_return(false,config("params.ORDER")['RE_CASH_GIFT_MONEY_D']);
            }
            $new_cash = $cash - $re_cashs;//新的礼金消费数
            if ($new_cash < 0){
                return $this->com_return(false,config("params.ORDER")['RE_CASH_MONEY_D']);
            }
            $sale_status = config("bill_assist.bill_status")['7']['key'];

            if ($new_account_balance == 0 && $new_account_cash_gift == 0 && $new_cash == 0){
                //如果全退了,那么单据状态就为全退
                $sale_status = config("bill_assist.bill_status")['8']['key'];
            }

            $billPayAssistParams = [
                "sale_status"           => $sale_status,
                "account_balance"       => $new_account_balance,
                "account_cash_gift"     => $new_account_cash_gift,
                "cash"                  => $new_cash,
                "re_account_balance"    => $new_re_account_balance,
                "re_account_cash_gift"  => $new_re_account_cash_gift,
                "re_cash"               => $new_re_cash,
                "check_user"            => $action_user,
                "check_time"            => time(),
                "check_reason"          => $check_reason,
                "updated_at"            => time()
            ];

            $billPayStatusReturn = $billPayAssistModel
                ->where("pid",$pid)
                ->update($billPayAssistParams);

            if ($billPayStatusReturn == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 013");
            }
            /*更新单据数据 Off*/

            $userCommonObj = new UserCommon();
            /*储值消费有退款 On*/
            if ($re_balance_money > 0){
                $new_account_balance = $user_account_balance + $re_balance_money;

                //插入储值消费明细
                //余额明细参数
                $insertUserAccountParams = [
                    "uid"          => $uid,
                    "balance"      => $re_balance_money,
                    "last_balance" => $new_account_balance,
                    "change_type"  => 1,
                    "action_user"  => $action_user,
                    "action_type"  => config('user.account')['hand_refund']['key'],
                    "oid"          => $pid,
                    "deal_amount"  => $re_balance_money,
                    "action_desc"  => config("user.account")['hand_refund']['name'],
                    "created_at"   => time(),
                    "updated_at"   => time()
                ];

                //插入用户储值明细
                $insertUserAccountReturn = $userCommonObj->updateUserAccount($insertUserAccountParams);

                if (!$insertUserAccountReturn){
                    return $this->com_return(false,config("params.ABNORMAL_ACTION"). " - 002");
                }
            }else{
                $new_account_balance = $user_account_balance;
            }
            /*储值消费有退款 Off*/

            /*礼金消费退还操作 On*/
            if ($re_cash_gift > 0){
                //有礼金消费
                $new_account_cash_gift = $user_account_cash_gift + $re_cash_gift;
                $userAccountCashGiftParams = [
                    'uid'            => $uid,
                    'cash_gift'      => $re_cash_gift,
                    'last_cash_gift' => $new_account_cash_gift,
                    'change_type'    => 1,
                    'action_user'    => $action_user,
                    'action_type'    => config('user.gift_cash')['hand_refund']['key'],
                    'action_desc'    => config('user.gift_cash')['hand_refund']['name'],
                    'oid'            => $pid,
                    'created_at'     => time(),
                    'updated_at'     => time()
                ];
                //给用户添加礼金明细
                $userAccountCashGiftReturn = $userCommonObj->updateUserAccountCashGift($userAccountCashGiftParams);
                if (!$userAccountCashGiftReturn){
                    return $this->com_return(false,config("params.ABNORMAL_ACTION"). " - 003");
                }
            }else{
                $new_account_cash_gift = $user_account_cash_gift;
            }
            /*礼金消费退还操作 Off*/

            /*现金消费退还操作 On*/
            if ($re_cashs > 0){}
            /*现金消费退还操作 Off*/

            /*更新用户账户信息 On*/
            $userUpdateParams = [
                "account_balance"   => $new_account_balance,
                "account_cash_gift" => $new_account_cash_gift,
                "updated_at"        => time()
            ];
            $updateUserReturn = $userCommonObj->updateUserInfo($userUpdateParams,"$uid");

            if ($updateUserReturn == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 001");
            }
            /*更新用户账户信息 Off*/

            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}