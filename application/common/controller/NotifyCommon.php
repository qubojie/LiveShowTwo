<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 上午10:31
 */

namespace app\common\controller;


use app\services\Sms;
use app\xcx_member\controller\main\Auth;
use think\Db;
use think\Exception;
use think\Log;

class NotifyCommon extends BaseController
{
    /**
     * 开卡支付回调
     * @param array $values
     * @param string $notifyType
     */
    public function openCardNotify($values = array(),$notifyType = "")
    {
        $vid = $values['out_trade_no'];

        Db::startTrans();
        try {
            $orderCommonObj = new OrderCommon();
            //根据订单号获取订单信息
            $order_info = $orderCommonObj->vidGetOrderInfo($vid);

            if (empty($order_info)){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[订单不存在!!!]]></return_msg> </xml>';
                die;
            }

            if ($order_info['sale_status'] == '1'){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[订单已支付]]></return_msg> </xml>';
                die;
            }

            $uid             = $order_info['uid'];//用户id
            $delivery_name   = $order_info['delivery_name'];//收货人
            $time            = time();

            //如果收货人为空,直接为交易完成,如果不为空,则为待发货状态
            if (empty($delivery_name)){
                $sale_status = config("order.open_card_status")['completed']['key'];
                $finish_time = $time;
            }else{
                $sale_status = config("order.open_card_status")['pending_ship']['key'];
                $finish_time = NULL;
            }

            $payable_amount = $values['cash_fee'] / 100;//订单实际需要支付金额
            $vid            = $values['out_trade_no'];//获取来订单id
            $pay_no         = $values['transaction_id'];;//微信流水号
            $pay_time       = $values['time_end'];//支付时间 格式为 201809100524
            $pay_money      = $values['total_fee'] / 100;//实付金额
            $pay_type       = config("order.pay_method")['wxpay']['key'];

            //⑥更新订单状态,
            $billCardFeesParams = [
                'sale_status'    => $sale_status,
                'pay_time'       => strtotime($pay_time),
                'payable_amount' => $payable_amount - $pay_money,
                'deal_price'     => $pay_money,
                'pay_type'       => $pay_type,
                'pay_no'         => $pay_no,
                'review_time'    => $time,
                'review_user'    => "系统自动",
                "review_desc"    => "微信系统收款",
                'updated_at'     => $time,
                'finish_time'    => $finish_time
            ];
            $billCardFeesReturn = $orderCommonObj->updateOrderStatus($billCardFeesParams,$vid);
            if ($billCardFeesReturn === false) {
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新订单状态异常]]></return_msg> </xml>';
                die;
            }

            //⑦添加开卡信息
            //获取卡的信息
            $billCardFeesDetail = $orderCommonObj->getBillCardFeesDetail($vid);
            if ($billCardFeesDetail === false) {
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[卡异常]]></return_msg> </xml>';
                die;
            }
            $card_id            = $billCardFeesDetail['card_id'];
            $card_validity_time = $billCardFeesDetail['card_validity_time'];
            $card_cash_gift     = $billCardFeesDetail['card_cash_gift'];//获取开卡赠送礼金数
            $card_point         = $billCardFeesDetail['card_point'];//获取开卡赠送积分
            $card_job_cash_gif  = $billCardFeesDetail['card_job_cash_gif'];//获取开卡赠送推荐用户礼金
            $card_job_commission= $billCardFeesDetail['card_job_commission']; //获取开卡赠送推荐用户佣金
            $card_no_prefix     = $billCardFeesDetail["card_no_prefix"]; //卡前缀
            $card_amount        = $billCardFeesDetail['card_amount'];//充值金额
            $cardInfoParams = [
                "uid"              => $uid,
                "card_no"          => generateReadableUUID("$card_no_prefix"),
                "card_id"          => $card_id,
                "card_type"        => $billCardFeesDetail['card_type'],
                "card_name"        => $billCardFeesDetail['card_name'],
                "card_image"       => $billCardFeesDetail['card_image'],
                "card_o_pay_amount"=> $billCardFeesDetail['card_pay_amount'],//应支付金额
                "card_pay_amount"  => $pay_money,//实际支付金额
                "card_amount"      => $card_amount,//充值金额
                "card_deposit"     => $billCardFeesDetail['card_deposit'],
                "card_desc"        => $billCardFeesDetail['card_desc'],
                "card_equities"    => $billCardFeesDetail['card_equities'],
                "is_valid"         => 1,
                "valid_time"       => $card_validity_time,//卡片有效期
                "created_at"       => $time,
                "updated_at"       => $time
            ];
            $cardCommonObj = new CardCommon();
            $cardInfoReturn = $cardCommonObj->updateCardInfo($cardInfoParams);
            if ($cardInfoReturn === false) {
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[添加开卡信息异常]]></return_msg> </xml>';
                die;
            }

            $userCommonObj = new UserCommon();
            $userOldMoneyInfo = $userCommonObj->uidGetUserMoney($uid);
            if ($userOldMoneyInfo === false) {
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[用户账户信息异常]]></return_msg> </xml>';
                die;
            }
            $account_balance   = $userOldMoneyInfo['account_balance'];//用户钱包可用余额
            $account_deposit   = $userOldMoneyInfo['account_deposit'];//用户钱包押金余额
            $account_cash_gift = $userOldMoneyInfo['account_cash_gift'];//用户礼金余额
            $account_point     = $userOldMoneyInfo['account_point'];//用户积分可用余额

            //⑩更新用户礼金账户以及礼金明细
            $referrer_info = $userCommonObj->getSalesmanId($uid);
            if ($referrer_info === false) {
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[推荐人信息异常]]></return_msg> </xml>';
                die;
            }
            $referrer_id   = $referrer_info['referrer_id'];
            $referrer_type = $referrer_info['referrer_type'];
            if (empty($referrer_id)){
                $referrer_id   = config("salesman.salesman_type")['3']['key'];
                $referrer_type = config("salesman.salesman_type")['3']['name'];
            }

            if ($referrer_id != config("salesman.salesman_type")['3']['key']){
                //如果不是平台推荐
                if ($referrer_type != 'user'){
                    //如果是内部人员推荐,给人员用户端账号返还礼金,佣金
                    $salesUserCommonObj = new SalesUserCommon();
                    $salesInfo = $salesUserCommonObj->getSalesManInfo($referrer_id);
                    if (empty($salesInfo)){
                        //推荐人不存在
                        echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[推荐人未注册用户端账号]]></return_msg> </xml>';
                        die;
                    }
                    $sales_phone = $salesInfo['phone'];
                    //获取销售的账户信息
                    $salesUserInfo = $userCommonObj->phoneGetUserMoney($sales_phone);
                    if ($salesUserInfo === false){
                        $referrer_id = "";
                    }else{
                        $referrer_id = $salesUserInfo['uid'];
                    }
                }

                if (!empty($referrer_id)){
                    //如果推荐人是用户或者注册用户的内部人员,给推荐人用户更新礼金信息
                    //账户可用礼金变动  正加 负减  直接取整,舍弃小数
                    $cash_gift = $card_job_cash_gif;
                    if ($cash_gift > 0){
                        //如果奖励推荐用户的礼金数 大于 0  则执行 更新

                        //首先获取推荐人的礼金余额
                        $referrer_user_gift_cash_old = $userCommonObj->getUserFieldValue("$referrer_id","account_cash_gift");
                        //变动后的礼金总余额
                        $last_cash_gift = $cash_gift + $referrer_user_gift_cash_old;
                        $userAccountCashGiftParams = [
                            'uid'            => $referrer_id,
                            'cash_gift'      => $cash_gift,
                            'last_cash_gift' => $last_cash_gift,
                            'change_type'    => '2',
                            'action_user'    => 'sys',
                            'action_type'    => config('user.gift_cash')['recommend_reward']['key'],
                            'action_desc'    => config('user.gift_cash')['recommend_reward']['name'],
                            'oid'            => $vid,
                            'created_at'     => $time,
                            'updated_at'     => $time
                        ];
                        //给用户添加礼金明细
                        $userAccountCashGiftReturn = $userCommonObj->updateUserAccountCashGift($userAccountCashGiftParams);
                        if ($userAccountCashGiftReturn === false) {
                            echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[推荐人用户更新礼金异常]]></return_msg> </xml>';
                            die;
                        }
                        //给用户添加礼金余额
                        $updatedAccountCashGiftReturn = $userCommonObj->updatedAccountCashGift("$referrer_id","$cash_gift","inc");
                        if ($updatedAccountCashGiftReturn === false) {
                            echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[推荐人添加礼金余额异常]]></return_msg> </xml>';
                            die;
                        }
                    }

                    /*给推荐用户添加佣金*/
                    if ($card_job_commission > 0){
                        $old_last_balance_res = $userCommonObj->getJobUserFieldValue("$referrer_id","job_balance");
                        if ($old_last_balance_res === false){
                            $job_balance = 0;
                        }else{
                            $job_balance = $old_last_balance_res['job_balance'];
                        }

                        $plus_card_job_commission = $card_job_commission;
                        //添加或更新推荐用户佣金表
                        $jobUserReturn = $userCommonObj->updateJobUser($referrer_id,$plus_card_job_commission);
                        if ($jobUserReturn === false){
                            echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[添加或更新推荐用户佣金异常]]></return_msg> </xml>';
                            die;
                        }

                        //添加推荐用户佣金明细表
                        $jobAccountParams = [
                            "uid"          => $referrer_id,
                            "balance"      => $plus_card_job_commission,
                            "last_balance" => $job_balance + $plus_card_job_commission,
                            "change_type"  => 2,
                            "action_user"  => 'sys',
                            "action_type"  => config('user.job_account')['recommend_reward']['key'],
                            "oid"          => $vid,
                            "deal_amount"  => $payable_amount,
                            "action_desc"  => config('user.job_account')['recommend_reward']['name'],
                            "created_at"   => $time,
                            "updated_at"   => $time
                        ];
                        $jobAccountReturn = $userCommonObj->insertJobAccount($jobAccountParams);
                        if ($jobAccountReturn === false){
                            echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[添加推荐用户佣金明细异常]]></return_msg> </xml>';
                            die;
                        }
                    }
                }
            }

            //获取当前用户旧的礼金余额
            $user_gift_cash_old = $userCommonObj->getUserFieldValue("$uid","account_cash_gift");

            if ($card_cash_gift > 0){
                //如果开卡赠送礼金数 大于 0,则变更礼金数,并增加 礼金明细
                $card_cash_gift_money = $card_cash_gift;
                $user_gift_cash_new   = $user_gift_cash_old + $card_cash_gift_money;

                //⑩更新办卡用户的返还礼金数额
                $updatedOpenCardCashGiftReturn = $userCommonObj->updatedAccountCashGift("$uid","$card_cash_gift_money","inc");
                if ($updatedOpenCardCashGiftReturn === false){
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新办卡用户的返还礼金异常]]></return_msg> </xml>';
                    die;
                }
                //⑩ - ① 更新用户礼金明细
                $updatedUserCashGiftParams = [
                    'uid'            => $uid,
                    'cash_gift'      => $card_cash_gift_money,
                    'last_cash_gift' => $user_gift_cash_new,
                    'change_type'    => '2',
                    'action_user'    => 'sys',
                    'action_type'    => config("user.gift_cash")['open_card_reward']['key'],
                    'action_desc'    => config("user.gift_cash")['open_card_reward']['name'],
                    'oid'            => $vid,
                    'created_at'     => $time,
                    'updated_at'     => $time
                ];
                //增加开卡用户礼金明细
                $openCardUserAccountCashGiftReturn = $userCommonObj->updateUserAccountCashGift($updatedUserCashGiftParams);
                if ($openCardUserAccountCashGiftReturn === false){
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新办卡用户的返还礼金异常]]></return_msg> </xml>';
                    die;
                }
            }


            /*如果充值金额大于零,则进行余额充值 On*/
            if ($card_amount > 0) {
                //⑧更新用户余额账户以及余额明细
                //获取用户旧的余额
                //用户余额参数
                $userCardParams = [
                    "uid"               => $uid,
                    "account_balance"   => $card_amount + $account_balance,
                    "user_status"       => config("user.user_status")['2']['key'],
                    "updated_at"        => $time
                ];

                $userUpdateReturn = $userCommonObj->updateUserInfo($userCardParams,$uid);
                if ($userUpdateReturn === false){
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新用户余额账户异常]]></return_msg> </xml>';
                    die;
                }

                //余额明细参数
                $userAccountParams = [
                    "uid"          => $uid,
                    "balance"      => $card_amount,
                    "last_balance" => $card_amount + $account_balance,
                    "change_type"  => '2',
                    "action_user"  => 'sys',
                    "action_type"  => config('user.account')['card_recharge']['key'],
                    "oid"          => $vid,
                    "deal_amount"  => $card_amount,
                    "action_desc"  => config('user.account')['card_recharge']['name'],
                    "created_at"   => $time,
                    "updated_at"   => $time
                ];

                $userInsertReturn = $userCommonObj->updateUserAccount($userAccountParams);
                if ($userInsertReturn === false){
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新用户余额明细异常]]></return_msg> </xml>';
                    die;
                }
            }

            /*如果充值金额大于零,则进行余额充值 Off*/

            //⑩更新用户积分账户以及积分明细,$account_point用户积分可用余额,$card_point 开卡赠送积分
            if ($card_point > 0){
                //如果赠送积分大于0 则更新
                $new_account_point = $account_point + $card_point;
                //获取用户新的等级id
                $level_id = getUserNewLevelId($new_account_point);
                //1.更新用户积分余额
                $updateUserPointParams = [
                    'level_id'      => $level_id,
                    'account_point' => $new_account_point,
                    'updated_at'    => $time
                ];
                $userUserPointReturn = $userCommonObj->updateUserInfo($updateUserPointParams,$uid);
                if ($userUserPointReturn === false){
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新用户积分异常]]></return_msg> </xml>';
                    die;
                }

                //2.更新用户积分明细
                $updateAccountPointParams = [
                    'uid'         => $uid,
                    'point'       => $card_point,
                    'last_point'  => $new_account_point,
                    'change_type' => 2,
                    'action_user' => 'sys',
                    'action_type' => config("user.point")['open_card_reward']['key'],
                    'action_desc' => config("user.point")['open_card_reward']['name'],
                    'oid'         => $vid,
                    'created_at'  => $time,
                    'updated_at'  => $time
                ];

                $userAccountPointReturn = $userCommonObj->updateUserAccountPoint($updateAccountPointParams);
                if ($userAccountPointReturn === false){
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新用户积分明细异常]]></return_msg> </xml>';
                    die;
                }
            }

            //⑩①下发赠送的券
            /*$voucherCommonObj = new VoucherCommon();
            $giftVouReturn = $voucherCommonObj->putVoucher("$card_id","$uid");
            if ($giftVouReturn === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[下发赠送的券异常]]></return_msg> </xml>';
                die;
            }*/

            if ($notifyType == 'adminCallback'){
                //如果是后台操作模拟回调
                $updateBillParams = [
                    "cancel_user" => null,
                    "cancel_time" => null,
                    "auto_cancel" => null,
                    "cancel_reason" => null,
                ];
                $adminUpdateOrderStatus = $orderCommonObj->updateOrderStatus($updateBillParams,"$vid");
                if ($adminUpdateOrderStatus === false) {
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[操作异常]]></return_msg> </xml>';
                    die;
                }

                //如果是后台操作订单支付成功,记录相关操作日志
                $action  = config("useraction.deal_pay")['key'];
                $reason  = $values['reason'];//操作原因描述
                $adminToken = $this->request->header("Authorization","");
                //获取当前登录管理员
                $action_user = $this->getLoginAdminId($adminToken)['user_name'];
                addSysAdminLog("$uid","","$vid","$action","$reason","$action_user","$time");
            }

            Db::commit();
            echo '<xml> <return_code><![CDATA[SUCCESS]]></return_code> <return_msg><![CDATA[OK]]></return_msg> </xml>';
            die;
        } catch (Exception $e) {
            Db::rollback();
            echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA['.$e->getMessage().']]></return_msg> </xml>';
            die;
        }
    }


    /**
     * 预约定金缴纳回调
     * @param array $values
     * @param string $notyfyType
     */
    public function payDeposit($values = array(),$notyfyType = "")
    {
        Log::info("押金回调入口");
        Db::startTrans();
        try {
            $suid = $values['out_trade_no'];
            $subscriptionOrderCommonObj = new SubscriptionOrderCommon();
            //订单信息
            $order_info = $subscriptionOrderCommonObj->suidGetOrderInfo("$suid");
            Log::info("订单信息  ".var_export($order_info,true));
            if ($order_info === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[订单不存在!!!]]></return_msg> </xml>';
                die;
            }
            $status = $order_info['status'];
            if ($status == '1'){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[订单已支付]]></return_msg> </xml>';
                die;
            }
            $uid      = $order_info['uid'];
            $trid     = $order_info['trid'];
            $pay_no   = $values['transaction_id'];//支付回单号
            $cash_fee = $values['cash_fee'] / 100;//支付金额
            $time     = time();

            //更新定金缴费单状态
            $updateBillSubscriptionParams = [
                "status"     => config("order.reservation_subscription_status")['Paid']['key'],
                "pay_time"   => $time,
                "pay_type"   => config("order.pay_method")['wxpay']['key'],
                "pay_no"     => $pay_no,
                "updated_at" => $time
            ];
            $changeBillSubscriptionReturn = $subscriptionOrderCommonObj->changeBillSubscriptionInfo($updateBillSubscriptionParams,$suid);
            Log::info("更新定金缴费单状态".$changeBillSubscriptionReturn);
            if ($changeBillSubscriptionReturn === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新定金缴费单状态异常]]></return_msg> </xml>';
                die;
            }

            //更新台位预定信息表中台位状态
            $updateTableRevenueParams = [
                "status"            => config("order.table_reserve_status")['success']['key'],
                "subscription"      => $cash_fee,
                "updated_at"        => $time
            ];
            $changeTableRevenueReturn = $subscriptionOrderCommonObj->changeTableRevenueInfo($updateTableRevenueParams,$trid);
            Log::info("更新台位预定信息表中台位状态".$changeTableRevenueReturn);
            if ($changeTableRevenueReturn === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新台位预定信息状态异常]]></return_msg> </xml>';
                die;
            }

            //根据订单,查看是否是服务人员预定
            $reserve_way_res = $subscriptionOrderCommonObj->tridGetReserveUser($trid);
            Log::info("查看是否是服务人员预定".var_export($reserve_way_res,true));
            if ($changeTableRevenueReturn === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[异常]]></return_msg> </xml>';
                die;
            }
            $reserve_way  = $reserve_way_res['reserve_way'];
            $table_no     = $reserve_way_res['table_no'];
            $reserve_time = $reserve_way_res['reserve_time'];
            $reserve_time = date("Y-m-d H:i",$reserve_time);

            if ($reserve_way == config("order.reserve_way")['service']['key'] ||$reserve_way ==  config("order.reserve_way")['client']['key'] || $reserve_way == config("order.reserve_way")['manage']['key']){
                //调起短信推送
                //获取用户电话
                $userCommonObj = new UserCommon();
                $userInfo = $userCommonObj->getUserInfo($uid);
                $phone = $userInfo['phone'];
                $name  = $userInfo['name'];

                $smsObj = new Sms();
                $type = "revenue";
                $sales_name  = $reserve_way_res['sales_name'];
                $sales_phone = $reserve_way_res['sales_phone'];
                $res = $smsObj->sendMsg("$name","$phone","$sales_name","$sales_phone","$type","$reserve_time","$table_no","$reserve_way");
                Log::info("短信结果".var_export($res,true));
            }

            Db::commit();
            echo '<xml> <return_code><![CDATA[SUCCESS]]></return_code> <return_msg><![CDATA[OK]]></return_msg> </xml>';
            die;
        } catch (Exception $e) {
            Db::rollback();
            echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA['.$e->getMessage().']]></return_msg> </xml>';
            die;
        }
    }

    /**
     * 充值回调
     * @param array $values
     * @param string $notyfyType
     */
    public function recharge($values = array(),$notyfyType = "")
    {
        /*'appid' => 'wxf23099114472fbe6',
          'attach' => '公众号支付',
          'bank_type' => 'COMM_CREDIT',
          'cash_fee' => '100',
          'fee_type' => 'CNY',
          'is_subscribe' => 'N',
          'mch_id' => '1507786841',
          'nonce_str' => 'jqdzarqu48pmlmfa24qpom6nn0s5oyol',
          'openid' => 'oDgH15SkR5bOqfoG2CS4iKJXndN0',
          'out_trade_no' => 'V1807161054462077A6F',
          'result_code' => 'SUCCESS',
          'return_code' => 'SUCCESS',
          'sign' => '51B15A80BDA18F37FD1C32D3D72EFE2A',
          'time_end' => '20180716105502',
          'total_fee' => '100',
          'trade_type' => 'JSAPI',
          'transaction_id' => '4200000122201807160565649815',*/

        Db::startTrans();
        try {
            $rfid = $values['out_trade_no'];
            $orderCommonObj = new OrderCommon();
            $order_info = $orderCommonObj->rfidGetBillRefillInfo("$rfid");
            if ($order_info === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[订单不存在!!!]]></return_msg> </xml>';
                die;
            }

            $status = $order_info['status'];
            if ($status == '1'){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[订单已支付]]></return_msg> </xml>';
                die;
            }
            $uid       = $order_info['uid'];
            $cash_gift = $order_info['cash_gift'];//赠送礼金数

            $userCommonObj = new UserCommon();
            $userOldMoneyInfo = $userCommonObj->uidGetUserMoney("$uid");
            if ($userOldMoneyInfo === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[用户不存在!!!]]></return_msg> </xml>';
                die;
            }
            $account_balance = $userOldMoneyInfo['account_balance'];//用户钱包可用余额
            $account_deposit = $userOldMoneyInfo['account_deposit']; //用户钱包押金余额
            $account_cash_gift = $userOldMoneyInfo['account_cash_gift'];//用户礼金余额

            $cash_fee       = $values['cash_fee'] / 100;
            $total_fee      = $values['total_fee'] / 100;
            $out_trade_no   = $values['out_trade_no'];
            $transaction_id = $values['transaction_id'];

            $time = time();

            //更新充值单据状态
            $updateBillRefillParams = [
                "pay_type"   => config("order.pay_method")['wxpay']['key'],
                "pay_time"   => $time,
                "pay_no"     => $transaction_id,
                "amount"     => $cash_fee,
                "status"     => config("order.recharge_status")['completed']['key'],
                "review_time"=> $time,
                "review_user"=> "系统自动",
                "review_desc"=>"微信系统收款",
                "updated_at" => $time
            ];
            $rechargeCommonObj = new RechargeCommon();
            //更新用户充值单据状态
            $updateRechargeReturn  = $rechargeCommonObj->updateBillRefill($updateBillRefillParams,"$out_trade_no");
            if ($updateRechargeReturn === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新用户充值单据状态异常!!!]]></return_msg> </xml>';
                die;
            }
            //更新用户余额参数
            $updateUserParams = [
                "account_balance"   => $cash_fee + $account_balance,
                "account_cash_gift" => $account_cash_gift + $cash_gift,
                "updated_at"        =>  $time
            ];
            //更新用户余额数据
            $updateUserReturn  = $userCommonObj->updateUserInfo($updateUserParams,$uid);
            if ($updateUserReturn === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[更新用户余额异常!!!]]></return_msg> </xml>';
                die;
            }

            //余额明细参数
            $insertUserAccountParams = [
                "uid"          => $uid,
                "balance"      => $cash_fee,
                "last_balance" => $cash_fee + $account_balance,
                "change_type"  => '2',
                "action_user"  => 'sys',
                "action_type"  => config('user.account')['recharge']['key'],
                "oid"          => $rfid,
                "deal_amount"  => $cash_fee,
                "action_desc"  => config("user.account")['recharge']['name'],
                "created_at"   => $time,
                "updated_at"   => $time
            ];
            //插入用户充值明细
            $insertUserAccountReturn = $userCommonObj->updateUserAccount($insertUserAccountParams);
            if ($insertUserAccountReturn === false){
                echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[插入用户充值明细异常!!!]]></return_msg> </xml>';
                die;
            }

            if ($cash_gift > 0){
                //如果礼金数额大于0 则插入用户礼金明细
                //变动后的礼金总余额
                $last_cash_gift = $cash_gift + $account_cash_gift;
                $userAccountCashGiftParams = [
                    'uid'            => $uid,
                    'cash_gift'      => $cash_gift,
                    'last_cash_gift' => $last_cash_gift,
                    'change_type'    => '2',
                    'action_user'    => 'sys',
                    'action_type'    => config('user.gift_cash')['recharge_give']['key'],
                    'action_desc'    => config('user.gift_cash')['recharge_give']['name'],
                    'oid'            => $rfid,
                    'created_at'     => $time,
                    'updated_at'     => $time
                ];
                //给用户添加礼金明细
                $userAccountCashGiftReturn = $userCommonObj->updateUserAccountCashGift($userAccountCashGiftParams);
                if ($userAccountCashGiftReturn === false){
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[插添加礼金明细异常!!!]]></return_msg> </xml>';
                    die;
                }
            }

            /*返金设置 On*/
            $userInfo = getUserInfo($uid);
            $referrer_type  = $userInfo['referrer_type'];
            $referrer_id    = $userInfo['referrer_id'];

            //获取返钱数据
            $returnMoneyRes = $this->uidGetCardReturnMoney("$uid");
            $consumption_money = $cash_fee;

            if ($returnMoneyRes === false) {
                $job_cash_gift_return_money  = 0;
                $job_commission_return_money = 0;
            }else{
                $refill_job_cash_gift      = $returnMoneyRes['refill_job_cash_gift'];     //充值推荐人返礼金
                $refill_job_commission     = $returnMoneyRes['refill_job_commission'];    //充值推荐人返佣金
                $consumptionReturnMoney = $rechargeCommonObj->rechargeReturnMoney("$uid","$referrer_type","$consumption_money","$refill_job_cash_gift","$refill_job_commission");

                $job_cash_gift_return_money  = $consumptionReturnMoney['job_cash_gift_return_money'];//返还推荐人礼金
                $job_commission_return_money = $consumptionReturnMoney['job_commission_return_money'];//返给推荐人佣金
            }

            if ($job_cash_gift_return_money > 0){
                //返还推荐人礼金
                $account_cash_gift = $userCommonObj->getUserFieldValue("$referrer_id","account_cash_gift");
                if ($account_cash_gift == false) {
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[返还推荐人礼金异常!!!]]></return_msg> </xml>';
                    die;
                }
                $new_account_cash_gift = $account_cash_gift + $job_cash_gift_return_money;
                $referrerUserParams = [
                    "account_cash_gift" => $new_account_cash_gift,
                    "updated_at"        => time()
                ];
                $referrerUserReturn = $userCommonObj->updateUserInfo($referrerUserParams,$referrer_id);
                if ($referrerUserReturn == false) {
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[返还推荐人礼金异常!!!]]></return_msg> </xml>';
                    die;
                }

                /*推荐人礼金明细 on*/
                $referrerUserDParams = [
                    'uid'            => $referrer_id,
                    'cash_gift'      => $job_cash_gift_return_money,
                    'last_cash_gift' => $new_account_cash_gift,
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
                if ($userAccountCashGiftReturn == false) {
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[推荐用户添加礼金明细异常!!!]]></return_msg> </xml>';
                    die;
                }
            }

            if ($job_commission_return_money > 0){
                //返给推荐人佣金
                $referrerUserJobInfo = $userCommonObj->getJobUserInfo($referrer_id);
                if ($referrerUserJobInfo == false) {
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[返给推荐人佣金异常!!!]]></return_msg> </xml>';
                    die;
                }
                if (empty($referrerUserJobInfo)){
                    //新增
                    $newJobParams = [
                        "uid"         => $referrer_id,
                        "job_balance" => $job_commission_return_money,
                        "created_at"  => time(),
                        "updated_at"  => time()
                    ];
                    $jobUserInsert = $userCommonObj->insertJobUser($newJobParams);
                    if ($jobUserInsert === false) {
                        echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[返给推荐人佣金异常!!!]]></return_msg> </xml>';
                        die;
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
                    if ($jobUserUpdate === false) {
                        echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[返给推荐人佣金异常!!!]]></return_msg> </xml>';
                        die;
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
                if ($jobAccountReturn === false) {
                    echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[添加推荐用户佣金明细异常!!!]]></return_msg> </xml>';
                    die;
                }
                /*佣金明细 off*/
            }
            /*返金设置 Off*/

            Db::commit();

           /* $action  = config("useraction.deal_pay")['key'];
            $reason  = $values['reason'];//操作原因描述
            $adminToken = $this->request->header("Authorization","");
            //获取当前登录管理员
            $action_user = $this->getLoginAdminId($adminToken)['user_name'];
            addSysAdminLog("$uid","","$rfid","$action","$reason","$action_user","$time");*/
            echo '<xml> <return_code><![CDATA[SUCCESS]]></return_code> <return_msg><![CDATA[OK]]></return_msg> </xml>';
            die;
        } catch (Exception $e) {
            Db::rollback();
            echo '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA['.$e->getMessage().']]></return_msg> </xml>';
            die;
        }
    }

    /**
     * 点单缴费订单回调
     * @param array $values
     * @param string $notifyType
     * @return string
     */
    public function pointListNotify($values = array(),$notifyType = "")
    {
        Db::startTrans();
        try {
            $pid = $values['out_trade_no'];
            $orderCommonObj = new OrderCommon();
            //获取订单信息
            $order_info = $orderCommonObj->pidGetBillPayInfo("$pid");
            if (empty($order_info)){
                return '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[订单不存在!!!]]></return_msg> </xml>';
            }
            $sale_status = $order_info['sale_status'];
            if ($sale_status == config("order.bill_pay_sale_status")['completed']['key']){
                return '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[订单已支付]]></return_msg> </xml>';
            }
            $uid          = $order_info['uid'];//用户id
            $trid         = $order_info['trid'];//预约订台id
            $return_point = $order_info['return_point'];//赠送积分

            $userCommonObj = new UserCommon();
            $userOldMoneyInfo = $userCommonObj->uidGetUserMoney("$uid");
            if ($userOldMoneyInfo == false){
                return '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA[账户异常]]></return_msg> </xml>';
            }
            $account_point = $userOldMoneyInfo['account_point'];//用户旧的积分账户信息

            /*更改订单状态 On */
            $cash_fee       = $values['cash_fee'] / 100;
            $total_fee      = $values['total_fee'] / 100;
            $out_trade_no   = $values['out_trade_no'];
            $transaction_id = $values['transaction_id'];
            if (isset($values['pay_type'])){
                $pay_type = $values['pay_type'];
            }else{
                $pay_type = config("order.pay_method")['wxpay']['key'];
            }

            if ($pay_type == config("order.pay_method")['balance']['key']){
                //如果是余额支付
                //更新预约点单单据状态参数
                $updateBillPayParams = [
                    "sale_status"     => config("order.bill_pay_sale_status")['completed']['key'],
                    "pay_time"        => time(),
                    "finish_time"     => time(),
                    "deal_amount"     => $cash_fee,
                    "pay_type"        => $pay_type,
                    "account_balance" => $cash_fee,
                    "payable_amount"  => $total_fee - $cash_fee,
                    "updated_at"      => time()
                ];


            }elseif ($pay_type == config("order.pay_method")['cash_gift']['key']){
                //如果是礼金支付
                //更新预约点单单据状态参数
                $updateBillPayParams = [
                    "sale_status"       => config("order.bill_pay_sale_status")['completed']['key'],
                    "pay_time"          => time(),
                    "finish_time"       => time(),
                    "deal_amount"       => $cash_fee,
                    "pay_type"          => $pay_type,
                    "account_cash_gift" => $cash_fee,
                    "payable_amount"    => $total_fee - $cash_fee,
                    "updated_at"        => time()
                ];

            }elseif ($pay_type == config("order.pay_method")['offline']['key']){
                //如果是线下支付
                //更新预约点单单据状态参数
                $updateBillPayParams = [
                    "sale_status" => config("order.bill_pay_sale_status")['wait_audit']['key'],
                    "pay_time"    => time(),
                    "pay_type"    => $pay_type,
                    "updated_at"  => time()
                ];

            }else{
                //如果是微信支付
                //更新预约点单单据状态参数
                $updateBillPayParams = [
                    "sale_status"    => config("order.bill_pay_sale_status")['completed']['key'],
                    "pay_time"       => time(),
                    "finish_time"    => time(),
                    "deal_amount"    => $cash_fee,
                    "payable_amount" => $total_fee - $cash_fee,
                    "pay_type"       => $pay_type,
                    "pay_no"         => $transaction_id,
                    "updated_at"     => time()
                ];
            }

            //更新用户预约点单单据状态为付款成功,等待落单
            $updateRechargeReturn = $orderCommonObj->updateBillPay($updateBillPayParams,"$pid");
            if ($updateRechargeReturn == false){
                return '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA['.config('params.ABNORMAL_ACTION').'PL001'.']]></return_msg> </xml>';
            }
            /*更改订单状态 Off */

            /*更新预约台位信息 On*/
            //获取当前台位点单数量
            $tableCommonObj = new TableCommon();
            $tableRevenueInfo = $tableCommonObj->tridGetTableInfo($trid);
            if (empty($tableRevenueInfo)) {
                return '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA['.config('params.ABNORMAL_ACTION').'PL001'.']]></return_msg> </xml>';
            }
            $new_turnover_num = $tableRevenueInfo['turnover_num'] + 1;
            $new_turnover     = $tableRevenueInfo['turnover'] + $cash_fee;
            $status           = $tableRevenueInfo['status'];

            if ($status == config("order.table_reserve_status")['already_open']['key']){
                //如果是已开台状态
                $updateTableRevenueParams = [
                    "turnover_num"      => $new_turnover_num,
                    "turnover"          => $new_turnover,
                    "updated_at"        => time()
                ];
            }else{
                //如果是预约
                $updateTableRevenueParams = [
                    "status"            => config("order.table_reserve_status")['reserve_success']['key'],
                    "turnover_num"      => $new_turnover_num,
                    "turnover"          => $new_turnover,
                    "subscription_time" => time(),
                    "updated_at"        => time()
                ];
            }

            $pointListCommonObj = new PointListCommon();
            //更新台位预定信息表中台位状态
            $changeTableRevenueReturn = $pointListCommonObj->changeTableRevenueInfo($updateTableRevenueParams,$trid,$uid);
            if ($changeTableRevenueReturn == false){
                return '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA['.config('params.ABNORMAL_ACTION').'PL001.1'.']]></return_msg> </xml>';
            }
            /*更新预约台位信息 Off*/

            /*用户积分操作 On*/
            if ($return_point > 0){
                $new_account_point = $return_point + $account_point;
                //获取用户新的等级id
                $level_id = getUserNewLevelId($new_account_point);
                //1.更新用户积分账户
                $userAccountPointParams = [
                    "account_point" => $new_account_point,
                    "level_id"      => $level_id,
                    "updated_at"    => time()
                ];
                $updateUserPointReturn = $userCommonObj->updateUserInfo($userAccountPointParams,$uid);
                if ($updateUserPointReturn == false){
                    return '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA['.config('params.ABNORMAL_ACTION').'PL002'.']]></return_msg> </xml>';
                }

                //2.更新用户积分明细
                $updateAccountPointParams = [
                    'uid'         => $uid,
                    'point'       => $return_point,
                    'last_point'  => $new_account_point,
                    'change_type' => 2,
                    'action_user' => 'sys',
                    'action_type' => config("user.point")['consume_reward']['key'],
                    'action_desc' => config("user.point")['consume_reward']['name'],
                    'oid'         => $pid,
                    'created_at'  => time(),
                    'updated_at'  => time()
                ];

                $userAccountPointReturn = $userCommonObj->updateUserAccountPoint($updateAccountPointParams);
                if ($userAccountPointReturn == false){
                    return '<xml> <return_code><![CDATA[FAIL]]></return_code> <return_msg><![CDATA['.config('params.ABNORMAL_ACTION').'PL003'.']]></return_msg> </xml>';
                }
            }
            /*用户积分操作 Off*/

            /*如果不是线下支付,且是已开台状态 On*/
            //调起打印机打印菜品信息 落单
            if ($status == config("order.table_reserve_status")['already_open']['key'] && $pay_type != config("order.pay_method")['offline']['key']){
                $is_print = $this->openTableToPrintYly($pid);
                $dateTimeFile = APP_PATH."index/PrintOrderYly/".date("Ym")."/";
                if (!is_dir($dateTimeFile)){
                    @mkdir($dateTimeFile,0777,true);
                }
                //打印结果日志
                error_log(date('Y-m-d H:i:s').var_export($is_print,true),3,$dateTimeFile.date("d").".log");
            }
            /*如果不是线下支付,且是已开台状态 Off*/

            Db::commit();
            return '<xml> <return_code><![CDATA[SUCCESS]]></return_code> <return_msg><![CDATA[OK]]></return_msg> </xml>';
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}