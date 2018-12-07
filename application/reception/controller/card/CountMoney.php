<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 下午1:32
 */

namespace app\reception\controller\card;


use app\common\controller\CountCommon;
use app\common\controller\ReceptionAuthAction;
use app\common\model\BillPayAssist;
use app\common\model\BillSettlement;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class CountMoney extends ReceptionAuthAction
{
    /**
     * 桌消费统计列表
     * @param Request $request
     * @return array
     */
    public function tableConsumer(Request $request)
    {
        $dateTime       = $request->param("dateTime","");//日期
        $pagesize       = $request->param("pagesize","");
        $nowPage        = $request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config("page_size");
        if (empty($nowPage)) $nowPage = 1;

        $config = [
            "page" => $nowPage,
        ];
        $rule = [
            "dateTime|日期"    => "require",
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
            $date_where['bpa.created_at'] = ["between time",["$beginTime","$endTime"]];

            $billPayAssistModel = new BillPayAssist();
            $one   = config("bill_assist.bill_status")['1']['key'];
            $seven = config("bill_assist.bill_status")['7']['key'];
            $sale_status_str = "$one,$seven";

            $list = $billPayAssistModel
                ->alias("bpa")
                ->join("user_gift_voucher ugv","ugv.gift_vou_code = bpa.gift_vou_code","LEFT")
                ->where("bpa.sale_status","IN",$sale_status_str)
                ->where($date_where)
                ->group("bpa.table_id,bpa.table_no")
                ->field("bpa.table_id,bpa.table_no")
                ->field("sum(bpa.account_balance) account_balance,sum(bpa.account_cash_gift) account_cash_gift,sum(bpa.cash) cash")
                ->field("sum(ugv.gift_vou_amount) gift_vou_amount")
                ->paginate($pagesize,false,$config);

            $list = json_decode(json_encode($list),true);

            //储值消费
            $all_balance_money = $billPayAssistModel
                ->alias("bpa")
                ->where("bpa.sale_status","IN",$sale_status_str)
                ->where($date_where)
                ->sum("bpa.account_balance");

            $list['all_balance_money'] = $all_balance_money;

            //现金消费
            $all_cash_money = $billPayAssistModel
                ->alias("bpa")
                ->where("bpa.sale_status","IN",$sale_status_str)
                ->where($date_where)
                ->sum("bpa.cash");

            $list['all_cash_money'] = $all_cash_money;

            //礼金消费
            $all_gift_money = $billPayAssistModel
                ->alias("bpa")
                ->where("bpa.sale_status","IN",$sale_status_str)
                ->where($date_where)
                ->sum("bpa.account_cash_gift");

            $list['all_gift_money'] = $all_gift_money;

            //礼券消费
            $all_voucher_money = $billPayAssistModel
                ->alias("bpa")
                ->join("user_gift_voucher ugv","ugv.gift_vou_code = bpa.gift_vou_code","LEFT")
                ->where("bpa.sale_status","IN",$sale_status_str)
                ->where($date_where)
                ->sum("ugv.gift_vou_amount");

            $list['all_voucher_money'] = $all_voucher_money;

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 结算统计
     * @param Request $request
     * @return array
     */
    public function settlementCount(Request $request)
    {
        $dateTime = $request->param("dateTime","");//截止时间

        $rule = [
            "dateTime|截止时间" => "require",
        ];

        $request_res = [
            "dateTime"    => $dateTime,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        $countCommonObj = new CountCommon();
        return $countCommonObj->settlementCountPublic($dateTime);
    }

    /**
     * 结算操作
     * @param Request $request
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function settlementAction(Request $request)
    {
        $ettlement_at         = $request->param("ettlement_at","");//结算时间
        $account_balance      = $request->param("account_balance","");//储值收款
        $account_cash_gift    = $request->param("account_cash_gift","");//礼金收款
        $re_account_balance   = $request->param("re_account_balance","");//退账户余额付款部分
        $re_account_cash_gift = $request->param("re_account_cash_gift","");//退礼金付款部分
        $card_cash            = $request->param("card_cash","");//开卡现金收款
        $card_bank            = $request->param("card_bank","");//开卡银行卡收款
        $card_wx_ali          = $request->param("card_wx_ali","");//开卡线下微信支付宝收款
        $card_wx_online       = $request->param("card_wx_online","");//开卡微信线上
        $card_cash_gift       = $request->param("card_cash_gift","");//开卡赠送礼金
        $refill_cash          = $request->param("refill_cash","");//充值现金收款
        $refill_bank          = $request->param("refill_bank","");//充值银行卡收款
        $refill_wx_ali        = $request->param("refill_wx_ali","");//充值线下微信支付宝收款
        $refill_wx_online     = $request->param("refill_wx_online","");//充值微信线上
        $refill_cash_gift     = $request->param("refill_cash_gift","");//充值赠送礼金
        $cash                 = $request->param("cash","");//现金收款
        $bank                 = $request->param("bank","");//银行收款
        $wx_ali               = $request->param("wx_ali","");//微信+支付宝收款
        $wx_online            = $request->param("wx_online","");//微信线上收款
        $check_reason         = $request->param("check_reason","");//审核原因

        $rule = [
            "ettlement_at|结算时间"      => "require",
            "account_balance|储值收款"   => "require",
            "account_cash_gift|礼金收款" => "require",
            "re_account_balance|退账户余额付款部分" => "require",
            "re_account_cash_gift|退礼金付款部分" => "require",
            "card_cash|开卡现金收款"             => "require",
            "card_bank|开卡银行卡收款"            => "require",
            "card_wx_ali|开卡线下微信支付宝收款"   => "require",
            "card_wx_online|开卡微信线上"         => "require",
            "card_cash_gift|开卡赠送礼金"         => "require",
            "refill_cash|充值现金收款"           => "require",
            "refill_bank|充值银行卡收款"          => "require",
            "refill_wx_ali|充值线下微信支付宝收款" => "require",
            "refill_wx_online|充值微信线上"      => "require",
            "refill_cash_gift|充值赠送礼金"      => "require",
            "cash|现金收款"              => "require",
            "bank|银行收款"              => "require",
            "wx_ali|微信+支付宝收款"      => "require",
            "wx_online|微信线上收款"      => "require",
        ];
        $request_res = [
            "ettlement_at"          => $ettlement_at,
            "account_balance"       => $account_balance,
            "account_cash_gift"     => $account_cash_gift,
            "re_account_balance"    => $re_account_balance,
            "re_account_cash_gift"  => $re_account_cash_gift,
            "card_cash"             => $card_cash,
            "card_bank"             => $card_bank,
            "card_wx_ali"           => $card_wx_ali,
            "card_wx_online"        => $card_wx_online,
            "card_cash_gift"        => $card_cash_gift,
            "refill_cash"           => $refill_cash,
            "refill_bank"           => $refill_bank,
            "refill_wx_ali"         => $refill_wx_ali,
            "refill_wx_online"      => $refill_wx_online,
            "refill_cash_gift"      => $refill_cash_gift,
            "cash"                  => $cash,
            "bank"                  => $bank,
            "wx_ali"                => $wx_ali,
            "wx_online"             => $wx_online,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        Db::startTrans();
        try {
            $countCommonObj = new CountCommon();
            /*检测是否有订单未处理 On*/
            $is_can_settlement = $countCommonObj->checkBillPayAssistCanSettlement($ettlement_at);
            if ($is_can_settlement !== true) {
                return $is_can_settlement;
            }
            /*检测是否有订单未处理 Off*/
            $settlement_id = generateReadableUUID("J");

            $token = $request->header("Token");
            $manageInfo = $this->receptionTokenGetManageInfo($token);
            $check_user = $manageInfo['sales_name'];

            $params = [
                "settlement_id"         => $settlement_id,
                "ettlement_at"          => $ettlement_at,
                "account_balance"       => $account_balance,
                "account_cash_gift"     => $account_cash_gift,
                "re_account_balance"    => $re_account_balance,
                "re_account_cash_gift"  => $re_account_cash_gift,
                "card_cash"             => $card_cash,
                "card_bank"             => $card_bank,
                "card_wx_ali"           => $card_wx_ali,
                "card_wx_online"        => $card_wx_online,
                "card_cash_gift"        => $card_cash_gift,
                "refill_cash"           => $refill_cash,
                "refill_bank"           => $refill_bank,
                "refill_wx_ali"         => $refill_wx_ali,
                "refill_wx_online"      => $refill_wx_online,
                "refill_cash_gift"      => $refill_cash_gift,
                "cash"                  => $cash,
                "bank"                  => $bank,
                "wx_ali"                => $wx_ali,
                "wx_online"             => $wx_online,
                "check_user"            => $check_user,
                "check_time"            => time(),
                "check_reason"          => $check_reason,
                "created_at"            => time(),
                "updated_at"            => time()
            ];

            /*创建结算单据表 On*/
            $billSettlementModel = new BillSettlement();
            $insertBillSettlementReturn = $billSettlementModel
                ->insert($params);
            if ($insertBillSettlementReturn == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION"));
            }
            /*创建结算单据表 Off*/

            /*消费结算返还礼金,佣金,积分等操作 On*/
            $returnMoneyR= $countCommonObj->returnMoneyR($ettlement_at,$check_user);
            if (!isset($returnMoneyR["result"]) || !$returnMoneyR["result"]){
                return $returnMoneyR;
            }
            /*消费结算返还礼金,佣金,积分等操作 Off*/

            /*消费结算单号回填 On*/
            $balanceMonetR = $countCommonObj->balanceMoneyR($ettlement_at,$settlement_id,$check_user);
            if (!$balanceMonetR){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 002");
            }
            /*消费结算单号回填 Off*/

            /*储值结算单号回填 On*/
            $rechargeR = $countCommonObj->rechargeR($ettlement_at,$settlement_id,$check_user);
            if (!$rechargeR){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 003");
            }
            /*储值结算单号回填 Off*/

            /*会员开卡结算单号回填 On*/
            $vipCardR = $countCommonObj->vipCardR($ettlement_at,$settlement_id,$check_user);
            if (!$vipCardR){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 003");
            }
            /*会员开卡结算单号回填 Off*/
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 结算历史筛选列表
     * @return array
     */
    public function settlementHistory()
    {
        try {
            $nowTime  = time();
            $lastWeek = $nowTime - 24 * 60 * 60 * 60;

            $beginTime   = date("Y-m-d H:i:s",$lastWeek);
            $endTime     = date("Y-m-d H:i:s",$nowTime);

            $billSettlementModel = new BillSettlement();

            $list = $billSettlementModel
                ->whereTime("created_at","between",["$beginTime","$endTime"])
                ->field("settlement_id,ettlement_at")
                ->order("created_at DESC")
                ->select();

            $list = json_decode(json_encode($list),true);

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 结算历史详情
     * @param Request $request
     * @return array
     */
    public function settlementHistoryDetails(Request $request)
    {
        $settlement_id = $request->param("settlement_id","");//结算id
        $rule = [
            "settlement_id|结算单据id" => "require",
        ];
        $request_res = [
            "settlement_id"    => $settlement_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        try {
            $countCommonObj = new CountCommon();
            $settlementInfo = $countCommonObj->settlementHistoryDetailsPublic($settlement_id);
            return $this->com_return(true,config("params.SUCCESS"),$settlementInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 插入结算详细对接接口
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function insertDetailsInfo()
    {
        /*首先获取结算列表 On*/
        $billSettlementModel = new BillSettlement();
        $res = $billSettlementModel
            ->field("settlement_id")
            ->select();
        /*首先获取结算列表 Off*/
        $res = json_decode(json_encode($res),true);

        $countCommonObj = new CountCommon();

        Db::startTrans();
        try {
            foreach ($res as $key => $val) {
                $settlement_id        = $val['settlement_id'];
                $settlementInfoRes    = $countCommonObj->settlementHistoryDetailsPublic($settlement_id);
                $re_account_balance   = $settlementInfoRes['details_info']['consumerClass']['re_account_balance'];//退账户余额付款部分
                $re_account_cash_gift = $settlementInfoRes['details_info']['consumerClass']['re_account_cash_gift'];//退礼金付款部分

                $card_cash      = $settlementInfoRes['details_info']['vipCardClass']['cash_pay'];//开卡现金收款
                $card_bank      = $settlementInfoRes['details_info']['vipCardClass']['bank_pay'];//开卡银行收款
                $card_wx_ali    = $settlementInfoRes['details_info']['vipCardClass']['wxpay_c_pay'] + $settlementInfoRes['details_info']['vipCardClass']['alipay_c_pay'];//开卡微信阿里线下收款
                $card_wx_online = $settlementInfoRes['details_info']['vipCardClass']['wxpay_pay'];//开卡线上收款
                $card_cash_gift = $settlementInfoRes['details_info']['vipCardClass']['give_gift_cash'];//开卡赠送礼金

                $refill_cash      = $settlementInfoRes['details_info']['rechargeClass']['cash_pay'];//储值现金收款
                $refill_bank      = $settlementInfoRes['details_info']['rechargeClass']['bank_pay'];//储值银行收款
                $refill_wx_ali    = $settlementInfoRes['details_info']['rechargeClass']['wxpay_c_pay'] + $settlementInfoRes['details_info']['rechargeClass']['alipay_c_pay'];//储值现在微信+阿里收款
                $refill_wx_online = $settlementInfoRes['details_info']['rechargeClass']['wxpay_pay'];//储值微信线上收款
                $refill_cash_gift =  $settlementInfoRes['details_info']['rechargeClass']['refill_cash_gift'];//储值赠送礼金

                $params = [
                    "re_account_balance"   => $re_account_balance,
                    "re_account_cash_gift" => $re_account_cash_gift,
                    "card_cash"            => $card_cash,
                    "card_bank"            => $card_bank,
                    "card_wx_ali"          => $card_wx_ali,
                    "card_wx_online"       => $card_wx_online,
                    "card_cash_gift"       => $card_cash_gift,
                    "refill_cash"          => $refill_cash,
                    "refill_bank"          => $refill_bank,
                    "refill_wx_ali"        => $refill_wx_ali,
                    "refill_wx_online"     => $refill_wx_online,
                    "refill_cash_gift"     => $refill_cash_gift,
                    "updated_at"           => time()
                ];
                $res = $billSettlementModel
                    ->where("settlement_id",$settlement_id)
                    ->update($params);
                if ($res === false) {
                    return $this->com_return(false,config('params.FAIL'));
                }
            }
            Db::commit();
            return $this->com_return(true,config('params.SUCCESS'));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}