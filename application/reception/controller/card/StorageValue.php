<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 上午11:23
 */
namespace app\reception\controller\card;

use app\common\controller\ReceptionAuthAction;
use app\common\controller\RechargeCommon;
use app\common\controller\UserCommon;
use app\common\controller\WechatCallCommon;
use app\common\model\BillRefill;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class StorageValue extends ReceptionAuthAction
{
    /**
     * 储值列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $dateTime = $request->param("dateTime","");//时间
        $payType  = $request->param("payType","");//付款方式
        $pagesize = $request->param("pagesize","");
        $nowPage  = $request->param("nowPage","1");

        if (empty($pagesize))  $pagesize = config("page_size");
        if (empty($nowPage)) $nowPage = 1;
        $config = [
            "page" => $nowPage,
        ];
        $rule = [
            "dateTime|时间"    => "require",
        ];
        $request_res = [
            "dateTime"    => $dateTime,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $dateTimeRes = $this->getSysTimeLong($dateTime);
            $beginTime = $dateTimeRes['beginTime'];
            $endTime   = $dateTimeRes['endTime'];
            $date_where['br.created_at'] = ["between time",["$beginTime","$endTime"]];
            $pay_type_where = [];
            if (!empty($payType)){
                if ($payType == "all"){
                    $pay_type_where = [];
                }else{
                    $pay_type_where["pay_type"] = ["eq",$payType];
                }
            }

            $billRefillModel = new BillRefill();
            $column = $billRefillModel->admin_column;
            foreach ($column as $key => $val){
                $column[$key] = "br.".$val;
            }
            $list = $billRefillModel
                ->alias("br")
                ->join("user u","u.uid = br.uid","LEFT")
                ->where("br.status",config("order.recharge_status")['completed']['key'])
                ->where($date_where)
                ->where($pay_type_where)
                ->field("u.name,u.phone")
                ->field($column)
                ->order("br.created_at DESC")
                ->paginate($pagesize,false,$config);
            $list = json_decode(json_encode($list),true);

            /*现金储值统计 on*/
            $cash_sum = $billRefillModel
                ->alias("br")
                ->where("br.status",config("order.recharge_status")['completed']['key'])
                ->where($date_where)
                ->where($pay_type_where)
                ->sum("br.amount");
            $list['cash_sum'] = $cash_sum;
            /*现金储值统计 off*/

            /*礼金储值统计 on*/
            $cash_gift_sum = $billRefillModel
                ->alias("br")
                ->where("br.status",config("order.recharge_status")['completed']['key'])
                ->where($date_where)
                ->where($pay_type_where)
                ->sum("br.cash_gift");
            $list['cash_gift_sum'] = $cash_gift_sum;
            /*礼金储值统计 off*/

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 确认收款
     */
    public function confirmMoney()
    {
        $rfid        = $this->request->param("rfid","");//储值订单id
        $pay_type    = $this->request->param("pay_type","");//支付方式
        $review_desc = $this->request->param("review_desc","");//审核备注
        $rule = [
            "rfid|订单"        => "require",
            "pay_type|支付方式" => "require",
        ];
        $request_res = [
            "rfid"     => $rfid,
            "pay_type" => $pay_type,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        Db::startTrans();
        try {
            /*获取当前储值订单信息 On*/
            $rechargeCommonObj = new RechargeCommon();
            $rechargeInfo = $rechargeCommonObj->rfidGetRechargeInfo("$rfid");
            if (empty($rechargeInfo)) {
                return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
            }
            $amount        = $rechargeInfo['amount'];//储值金额
            $notifyType = "adminCallback";
            $Authorization = "";
            $wechatCallCommonObj = new WechatCallCommon();
            $res = $wechatCallCommonObj->callBackPay("$Authorization","$notifyType","$rfid","$amount","$amount","$review_desc","");
            $res = json_decode(json_encode(simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            if ($res['return_code'] != "SUCCESS"){
                return $this->com_return(false,$res['return_msg']);
            }

            $token       = $this->request->header("Token");
            $manageInfo  = $this->receptionTokenGetManageInfo($token);
            $action_user = $manageInfo['sales_name'];

            //如果支付成功
            //更改订单支付信息
            $orderParams = [
                'pay_type'         => $pay_type,
                'review_time'      => time(),
                'review_user'      => $action_user,
                'review_desc'      => $review_desc,
                'updated_at'       => time()
            ];

            $billRefillModel = new BillRefill();
            $is_ok = $billRefillModel
                ->where('rfid',$rfid)
                ->update($orderParams);

            if ($is_ok !== false){
                Db::commit();
                $uid           = $rechargeInfo['uid'];//储值金额
                addSysAdminLog("$uid","","$rfid",config("useraction.recharge")["key"],config("useraction.recharge")["name"],"$action_user",time());
                return $this->com_return(true,$res['return_msg']);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
            /*获取当前储值订单信息 Off*/
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 确认充值
     * @param Request $request
     * @return array
     */
    public function rechargeConfirm(Request $request)
    {
        $phone           = $request->param("phone","");//用户电话
        $sales_phone     = $request->param("sales_phone","");//营销电话
        $recharge_amount = $request->param("recharge_amount","");//储值金额
        $cash_amount     = $request->param("cash_amount","");//赠送礼金
        $review_desc     = $request->param("review_desc","");//备注
        $pay_type        = $request->param("pay_type","");//支付方式
        $rule = [
            "phone|电话"              => "require|regex:1[0-9]{1}[0-9]{9}",
            "sales_phone|营销电话"     => "regex:1[0-9]{1}[0-9]{9}",
            "recharge_amount|储值金额" => "require|number|egt:0",
            "cash_amount|赠送礼金"     => "require|number|egt:0",
            "pay_type|支付方式"        => "require",
        ];
        $request_res = [
            "phone"           => $phone,
            "sales_phone"     => $sales_phone,
            "recharge_amount" => $recharge_amount,
            "cash_amount"     => $cash_amount,
            "pay_type"        => $pay_type,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        try {
            //获取用户信息
            $userCommonObj = new UserCommon();
            $userInfo = $userCommonObj->uidOrPhoneGetUserInfo("$phone");
            if (empty($userInfo)){
                return $this->com_return(false,config("params.USER")['USER_NOT_EXIST']);
            }

            $token       = $request->header("Token");
            $manageInfo  = $this->receptionTokenGetManageInfo($token);
            $action_user = $manageInfo['sales_name'];

            $rechargeCommonObj = new RechargeCommon();
            $uid = $userInfo['uid'];
            $pay_line_type = 1;
            $res = $rechargeCommonObj->rechargePublicAction("$uid","$recharge_amount","$cash_amount","$pay_type","$pay_line_type","$action_user","$review_desc","$sales_phone");
            if (!isset($res['result']) || !$res['result']) {
                return $this->com_return(false,config("params.FAIL"));
            }
            $res_data  = $res['data'];
            $rfid      = $res_data['rfid'];
            $amount    = $res_data['amount'] * 100;
            $cash_gift = $res_data['cash_gift'];
            $notifyType = "adminCallback";
            $Authorization = "";
            $wechatCallCommonObj = new WechatCallCommon();
            $res = $wechatCallCommonObj->callBackPay("$Authorization","$notifyType","$rfid","$amount","$amount","$review_desc","");
            $res = json_decode(json_encode(simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            if ($res['return_code'] != "SUCCESS"){
                return $this->com_return(false,$res['return_msg']);
            }
            //如果支付成功
            //更改订单支付信息
            $orderParams = [
                'pay_type'         => $pay_type,
                'review_time'      => time(),
                'review_user'      => $action_user,
                'review_desc'      => $review_desc,
                'updated_at'       => time()
            ];

            $billRefillModel = new BillRefill();
            $is_ok = $billRefillModel
                ->where('rfid',$rfid)
                ->update($orderParams);

            if ($is_ok !== false){
                addSysAdminLog("$uid","","$rfid",config("useraction.recharge")["key"],config("useraction.recharge")["name"],"$action_user",time());
                return $this->com_return(true,$res['return_msg']);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * @todo  弃用确认充值
     * @todo  弃用确认充值
     * @todo  弃用确认充值
     * @todo  弃用确认充值
     * @todo  弃用确认充值
     * @todo  弃用确认充值
     * 确认充值
     * @param Request $request
     * @return array
     */
    public function rechargeConfirm2(Request $request)
    {
        $phone           = $request->param("phone","");//用户电话
        $recharge_amount = $request->param("recharge_amount","");//储值金额
        $cash_amount     = $request->param("cash_amount","");//赠送礼金
        $review_desc     = $request->param("review_desc","");//备注
        $pay_type        = $request->param("pay_type","");//支付方式
        $rule = [
            "phone|电话"              => "require|regex:1[0-9]{1}[0-9]{9}",
            "recharge_amount|储值金额" => "require|number|egt:0",
            "cash_amount|赠送礼金"     => "require|number|egt:0",
            "pay_type|支付方式"        => "require",
        ];
        $request_res = [
            "phone"           => $phone,
            "recharge_amount" => $recharge_amount,
            "cash_amount"     => $cash_amount,
            "pay_type"        => $pay_type,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        Db::startTrans();
        try {
            $token = $request->header("Token");
            $manageInfo = $this->receptionTokenGetManageInfo($token);
            $review_user = $manageInfo['sales_name'];

            //获取用户信息
            $userCommonObj = new UserCommon();
            $userInfo = $userCommonObj->uidOrPhoneGetUserInfo("$phone");
            if (!empty($userInfo)){
                return $this->com_return(false,config("params.USER")['USER_NOT_EXIST']);
            }

            $rfid = generateReadableUUID("RF");

            $uid                        = $userInfo['uid'];
            $referrer_type              = $userInfo['referrer_type'];//推荐类型
            $referrer_id                = $userInfo['referrer_id'];//推荐人 id
            $account_balance            = $userInfo['account_balance'];//账户储值余额
            $account_cash_gift          = $userInfo['account_cash_gift'];//账户礼金余额
            $user_new_account_balance   = $account_balance + $recharge_amount;//用户新的储值金额
            $user_new_account_cash_gift = $account_cash_gift + $cash_amount;//用户新的礼金余额
            $last_cash_gift             = $user_new_account_cash_gift;
            $last_account_balance       = $user_new_account_balance;

            $updatedUserParams = [
                "account_balance"   => $user_new_account_balance,
                "account_cash_gift" => $user_new_account_cash_gift,
                "updated_at"        => time()
            ];
            $updateUserReturn = $userCommonObj->updateUserInfo($updatedUserParams,"$uid");
            if ($updateUserReturn === false){
                return $this->com_return(false,config("params.FAIL")." - 001");
            }

            if ($referrer_type == config("salesman.salesman_type")['2']['key']){
                //如果是用户推荐
                $returnMoneyRes = $this->uidGetCardReturnMoney("$uid");
                $consumption_money = $recharge_amount;
                if (!empty($returnMoneyRes)){
                    $refill_job_cash_gift      = $returnMoneyRes['refill_job_cash_gift'];     //充值推荐人返礼金
                    $refill_job_commission     = $returnMoneyRes['refill_job_commission'];    //充值推荐人返佣金

                    $rechargeCommonObj      = new RechargeCommon();
                    $consumptionReturnMoney = $rechargeCommonObj->rechargeReturnMoney("$uid","$referrer_type","$consumption_money","$refill_job_cash_gift","$refill_job_commission");

                    $job_cash_gift_return_money  = $consumptionReturnMoney['job_cash_gift_return_money'];//返还推荐人礼金
                    $job_commission_return_money = $consumptionReturnMoney['job_commission_return_money'];//返给推荐人佣金
                }else{
                    $job_cash_gift_return_money  = 0;//返还推荐人礼金
                    $job_commission_return_money = 0;//返给推荐人佣金
                }

                if ($job_cash_gift_return_money > 0){
                    //返还推荐人礼金
                    //获取推荐人账户信息
                    $referrerInfo  = $userCommonObj->getUserInfo("$referrer_id");
                    if (!empty($referrerInfo)){
                        //如果推荐人存在
                        $referrer_cash_gift     = $referrerInfo['account_cash_gift'];
                        $referrer_new_cash_gift = $referrer_cash_gift + $job_cash_gift_return_money;
                        $updateReferrerParams   = [
                            "account_cash_gift" => $referrer_new_cash_gift,
                            "updated_at"        => time()
                        ];
                        $updateReferrerReturn = $userCommonObj->updateUserInfo($updateReferrerParams,"$referrer_id");
                        //更新推荐人礼金账户信息
                        if ($updateReferrerReturn === false){
                            return $this->com_return(false,config("params.FAIL")." - 003");
                        }

                        /*推荐人礼金明细 on*/
                        $referrerUserDParams = [
                            'uid'            => $referrer_id,
                            'cash_gift'      => $job_cash_gift_return_money,
                            'last_cash_gift' => $referrer_new_cash_gift,
                            'change_type'    => '2',
                            'action_user'    => "sys",
                            'action_type'    => config('user.gift_cash')['recharge_give']['key'],
                            'action_desc'    => config('user.gift_cash')['recharge_give']['name'],
                            'oid'            => $rfid,
                            'created_at'     => time(),
                            'updated_at'     => time()
                        ];
                        //给推荐用户添加礼金明细
                        $userAccountCashGiftReturn = $userCommonObj->updateUserAccountCashGift($referrerUserDParams);
                        if ($userAccountCashGiftReturn == false){
                            return $this->com_return(false,config("params.FAIL")." - 004");
                        }
                        /*推荐人礼金明细 off*/
                    }
                }

                if ($job_commission_return_money > 0){
                    //返还兼职推荐人佣金
                    //返给推荐人佣金
                    $referrerUserJobInfo = $userCommonObj->getJobUserInfo("$referrer_id");

                    if (empty($referrerUserJobInfo)){
                        //新增
                        $newJobParams = [
                            "uid"         => $referrer_id,
                            "job_balance" => $job_commission_return_money,
                            "created_at"  => time(),
                            "updated_at"  => time()
                        ];
                        $jobUserInsert = $userCommonObj->insertJobUser($newJobParams);
                        if ($jobUserInsert === false){
                            return $this->com_return(false,config("params.FAIL")." - 006");
                        }
                        $referrer_last_balance = $job_commission_return_money;
                    }else{
                        $referrer_new_job_balance = $referrerUserJobInfo['job_balance'] + $job_commission_return_money;
                        //更新
                        $newJobParams = [
                            "job_balance" => $referrer_new_job_balance,
                            "updated_at"  => time()
                        ];

                        $jobUserUpdate = $userCommonObj->updateJobUserInfo($newJobParams,$referrer_id);

                        if ($jobUserUpdate === false){
                            return $this->com_return(false,config("params.FAIL")." - 007");
                        }
                        $referrer_last_balance = $referrer_new_job_balance;
                    }
                    /*佣金明细 on*/
                    //添加推荐用户佣金明细表
                    $jobAccountParams = [
                        "uid"          => $referrer_id,
                        "balance"      => $job_commission_return_money,
                        "last_balance" => $referrer_last_balance,
                        "change_type"  => 2,
                        "action_user"  => 'sys',
                        "action_type"  => config('user.job_account')['recharge']['key'],
                        "oid"          => $rfid,
                        "deal_amount"  => $consumption_money,
                        "action_desc"  => config('user.job_account')['recharge']['name'],
                        "created_at"   => time(),
                        "updated_at"   => time()
                    ];
                    $jobAccountReturn = $userCommonObj->insertJobAccount($jobAccountParams);

                    if ($jobAccountReturn == false){
                        return $this->com_return(false,config("params.FAIL")." - 008");
                    }
                    /*佣金明细 off*/
                }
            }

            /*更新用户储值账户明细 on*/
            if ($recharge_amount > 0){
                //余额明细参数
                $insertUserAccountParams = [
                    "uid"          => $uid,
                    "balance"      => $recharge_amount,
                    "last_balance" => $last_account_balance,
                    "change_type"  => 1,
                    "action_user"  => $review_user,
                    "action_type"  => config('user.account')['recharge']['key'],
                    "oid"          => $rfid,
                    "deal_amount"  => $recharge_amount,
                    "action_desc"  => config("user.account")['recharge']['name'],
                    "created_at"   => time(),
                    "updated_at"   => time()
                ];
                //插入用户充值明细
                $insertUserAccountReturn = $userCommonObj->updateUserAccount($insertUserAccountParams);
                if ($insertUserAccountReturn == false){
                    return $this->com_return(false,config("params.FAIL")." - 009");
                }
            }
            /*更新用户储值账户明细 off*/

            /*更新用户礼金账户明细 on*/
            if ($cash_amount > 0){
                //如果礼金数额大于0 则插入用户礼金明细
                //变动后的礼金总余额
                $userAccountCashGiftParams = [
                    'uid'            => $uid,
                    'cash_gift'      => $cash_amount,
                    'last_cash_gift' => $last_cash_gift,
                    'change_type'    => 1,
                    'action_user'    => $review_user,
                    'action_type'    => config('user.gift_cash')['recharge_give']['key'],
                    'action_desc'    => config('user.gift_cash')['recharge_give']['name'],
                    'oid'            => $rfid,
                    'created_at'     => time(),
                    'updated_at'     => time()
                ];
                //给用户添加礼金明细
                $userAccountCashGiftReturn = $userCommonObj->updateUserAccountCashGift($userAccountCashGiftParams);
                if ($userAccountCashGiftReturn == false){
                    return $this->com_return(false,config("params.FAIL")." - 006");
                }
            }
            /*更新用户礼金账户 off*/

            /*插入新的充值信息 on*/
            //插入用户充值单据表
            $billRefillParams = [
                "rfid"          => $rfid,
                "referrer_type" => config("salesman.salesman_type")['3']['name'],
                "referrer_id"   => config("salesman.salesman_type")['3']['key'],
                "uid"           => $uid,
                "pay_type"      => $pay_type,
                "pay_time"      => time(),
                "amount"        => $recharge_amount,
                "cash_gift"     => $cash_amount,
                "status"        => config("order.recharge_status")['completed']['key'],
                "review_time"   => time(),
                "review_user"   => $review_user,
                "review_desc"   => $review_desc,
                "created_at"    => time(),
                "updated_at"    => time()
            ];

            $billRefillModel  = new BillRefill();
            $billRefillReturn = $billRefillModel
                ->insert($billRefillParams);

            if ($billRefillReturn == false){
                return $this->com_return(false,config("params.FAIL")." - 005");
            }
            /*插入新的充值信息 off*/
            //记录充值日志
            $action = config("useraction.recharge")['key'];
            $reason = config("useraction.recharge")['name'];
            addSysAdminLog("$uid","","$rfid","$action","$reason","$review_user",time());
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));

        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}