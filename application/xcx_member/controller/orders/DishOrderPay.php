<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/27
 * Time: 下午5:09
 */

namespace app\xcx_member\controller\orders;


use app\common\controller\MemberAuthAction;
use app\common\controller\NotifyCommon;
use app\common\controller\OrderCommon;
use app\common\controller\PointListCommon;
use app\common\controller\UserCommon;
use think\Db;
use think\Exception;
use think\Request;

class DishOrderPay extends MemberAuthAction
{
    /**
     * 钱包支付点单订单
     * @param Request $request
     * @return array
     */
    public function walletPay(Request $request)
    {
        $pid = $request->param("vid", "");//订单id
        if (empty($pid)) {
            return $this->com_return(false, config("params.ABNORMAL_ACTION"));
        }
        try {
            //获取用户信息
            $token = $request->header("Token");
            $userInfo = $this->tokenGetUserInfo($token);
            if (empty($userInfo)) {
                return $this->com_return(false, config("params.ABNORMAL_ACTION"));
            }
            $pointListCommon = new PointListCommon();
            return $pointListCommon->walletPayPublic($pid,$userInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 礼金支付
     * @param Request $request
     * @return array
     */
    public function cashGiftPay(Request $request)
    {
        $pid = $request->param("vid", "");//订单id
        if (empty($pid)) {
            return $this->com_return(false, config("params.ABNORMAL_ACTION"));
        }
        $orderCommonObj = new OrderCommon();
        $orderInfo = $orderCommonObj->pidGetBillPayInfo("$pid");
        if (empty($orderInfo)) {
            return $this->com_return(false,  config("params.ORDER")['ORDER_ABNORMAL']);
        }
        $sale_status = $orderInfo['sale_status'];
        if ($sale_status != config("order.bill_pay_sale_status")['pending_payment_return']['key']) {
            return $this->com_return(false, config("params.ORDER")['NOW_STATUS_NOT_PAY']);
        }

        //获取用户信息
        $token    = $request->header("Token");
        $userInfo = $this->tokenGetUserInfo($token);
        if (empty($userInfo)) {
            return $this->com_return(false, config("params.ABNORMAL_ACTION"));
        }
        $uid  = $userInfo['uid'];
        $trid = $orderInfo['trid'];
        $order_amount      = $orderInfo['order_amount'];//订单总金额
        $payable_amount    = $orderInfo['payable_amount'];//应付且未付金额
        $account_cash_gift = $userInfo['account_cash_gift'];//用户礼金余额

        Db::startTrans();
        try {
            /*用户余额付款操作 on*/
            $reduce_after_cash_gift = $account_cash_gift - $payable_amount;
            if ($reduce_after_cash_gift < 0) {
                return $this->com_return(false, config("params.ORDER")['GIFT_NOT_ENOUGH']);
            }
            //更改用户余额数据(先把余额扣除后,再去做回调)
            $userBalanceParams = [
                "account_cash_gift" => $reduce_after_cash_gift,
                "updated_at"      => time()
            ];

            $userCommonObj = new UserCommon();

            //更新用户礼金账户数据
            $updateUserBalanceReturn = $userCommonObj->updateUserInfo($userBalanceParams, $uid);

            if ($updateUserBalanceReturn == false) {
                return $this->com_return(false, config("params.ABNORMAL_ACTION") . "PC001");
            }

            //插入用户礼金消费明细
            //礼金明细参数
            $insertUserAccountCashGiftParams = [
                "uid"          => $uid,
                "cash_gift"    => "-" . $payable_amount,
                "last_cash_gift" => $reduce_after_cash_gift,
                "change_type"  => '2',
                "action_user"  => 'sys',
                "action_type"  => config('user.gift_cash')['consume']['key'],
                "oid"          => $pid,
                "action_desc"  => config('user.gift_cash')['consume']['name'],
                "created_at"   => time(),
                "updated_at"   => time()
            ];

            //插入用户礼金消费明细
            $insertUserAccountReturn = $userCommonObj->updateUserAccountCashGift($insertUserAccountCashGiftParams);

            if ($insertUserAccountReturn == false) {
                return $this->com_return(false, config("params.ABNORMAL_ACTION") . "PC002");
            }

            /*用户余额付款操作 off*/

            //订单支付回调
            $payCallBackParams = [
                "out_trade_no"   => $pid,
                "cash_fee"       => $payable_amount * 100,
                "total_fee"      => $order_amount * 100,
                "transaction_id" => "",
                "pay_type"       => config("order.pay_method")['cash_gift']['key']
            ];

            $notifyCommonObj   = new NotifyCommon();
            $payCallBackReturn = $notifyCommonObj->pointListNotify($payCallBackParams,"");
            $payCallBackReturn = json_decode(json_encode(simplexml_load_string($payCallBackReturn, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            if ($payCallBackReturn["return_code"] != "SUCCESS" || $payCallBackReturn["return_msg"] != "OK"){
                //回调失败
                return $this->com_return(false,$payCallBackReturn["return_msg"]);
            }
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}