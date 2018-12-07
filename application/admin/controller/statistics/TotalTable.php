<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午2:24
 */
namespace app\admin\controller\statistics;


use app\common\controller\AdminAuthAction;
use app\common\controller\BaseController;
use app\common\controller\PhpExcel;
use app\common\controller\TotalCommon;
use app\common\model\BillSettlement;
use think\Exception;

//class TotalTable extends AdminAuthAction
class TotalTable extends BaseController
{
    /**
     * 会员卡明细
     * @return array
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function userCardReport()
    {
        if ($this->request->method() == "OPTIONS") {
            return $this->com_return(true,config('params.SUCCESS'));
        }

        $date_time   = $this->request->param('date_time','');//时间区间,以逗号隔开
        $is_download = $this->request->param('is_download','');

        if (empty($date_time)) {
            return $this->com_return(false,config('params.FAIL'));
        }

        try {
            $date_time_arr = explode(",",$date_time);
            $time_is_ok = $date_time_arr[1] - $date_time_arr[0];
            $sixty_date_s = 24 * 60 * 60 * 60;
            if ($time_is_ok > $sixty_date_s) {
                return $this->com_return(false,"有效区间60天,请选择有效日期区间");
            }
            $start_time = strtotime(date("Ymd",$date_time_arr[0]));
            $end_time   = strtotime(date("Ymd",$date_time_arr[1])) + 60 * 60 * 24 - 1;

            $billSettlementModel = new BillSettlement();

            $res = $billSettlementModel
                ->where("ettlement_at","between time",[$start_time,$end_time])
                ->select();

            $res = json_decode(json_encode($res),true);
            $fmt = [];
            if (!empty($res)) {
                for ($i  = 0; $i < count($res); $i ++) {
                    $open_card_sum                     = $res[$i]['card_cash'] + $res[$i]['card_bank'] + $res[$i]['card_wx_ali'] + $res[$i]['card_wx_online'];
                    $recharge_sum                      = $res[$i]['refill_cash'] + $res[$i]['refill_bank'] + $res[$i]['refill_wx_ali'] + $res[$i]['refill_wx_online'];
                    $fmt[$i]['date_time']              = $res[$i]['ettlement_at'];
                    $fmt[$i]['cash_pay_money']         = $res[$i]['card_cash'];
                    $fmt[$i]['bank_pay_money']         = $res[$i]['card_bank'];
                    $fmt[$i]['wx_alipay_open_card']    = $res[$i]['card_wx_ali'];
                    $fmt[$i]['wxpay_pay_money']        = $res[$i]['card_wx_online'];
                    $fmt[$i]['open_card_give_gift']    = $res[$i]['card_cash_gift'];
                    $fmt[$i]['open_card_sum']          = $open_card_sum;
                    $fmt[$i]['cash_recharge']          = $res[$i]['refill_cash'];
                    $fmt[$i]['bank_recharge']          = $res[$i]['refill_bank'];
                    $fmt[$i]['wx_alipay_recharge']     = $res[$i]['refill_wx_ali'];
                    $fmt[$i]['wxpay_pay_recharge']     = $res[$i]['refill_wx_online'];
                    $fmt[$i]['recharge_cash_gift_sum'] = $res[$i]['refill_cash_gift'];
                    $fmt[$i]['recharge_sum']           = $recharge_sum;
                    $fmt[$i]['all_money']              = $open_card_sum + $recharge_sum;
                    $fmt[$i]['all_give']               = $res[$i]['card_cash_gift'] + $res[$i]['refill_cash_gift'];
                    $fmt[$i]['check_user']             = $res[$i]['check_user'];
                }
            }
            $cash_pay_money         = 0;
            $bank_pay_money         = 0;
            $wx_alipay_open_card    = 0;
            $wxpay_pay_money        = 0;
            $open_card_give_gift    = 0;
            $open_card_sum          = 0;
            $cash_recharge          = 0;
            $bank_recharge          = 0;
            $wx_alipay_recharge     = 0;
            $wxpay_pay_recharge     = 0;
            $recharge_cash_gift_sum = 0;
            $recharge_sum           = 0;
            $all_money              = 0;
            $all_give               = 0;
            for ($m = 0; $m < count($fmt); $m++ ) {
                $total['date_time']     = "合计";
                $cash_pay_money         += $fmt[$m]['cash_pay_money'];
                $bank_pay_money         += $fmt[$m]['bank_pay_money'];
                $wx_alipay_open_card    += $fmt[$m]['wx_alipay_open_card'];
                $wxpay_pay_money        += $fmt[$m]['wxpay_pay_money'];
                $open_card_give_gift    += $fmt[$m]['open_card_give_gift'];
                $open_card_sum          += $fmt[$m]['open_card_sum'];
                $cash_recharge          += $fmt[$m]['cash_recharge'];
                $bank_recharge          += $fmt[$m]['bank_recharge'];
                $wx_alipay_recharge     += $fmt[$m]['wx_alipay_recharge'];
                $wxpay_pay_recharge     += $fmt[$m]['wxpay_pay_recharge'];
                $recharge_cash_gift_sum += $fmt[$m]['recharge_cash_gift_sum'];
                $recharge_sum           += $fmt[$m]['recharge_sum'];
                $all_money              += $fmt[$m]['all_money'];
                $all_give               += $fmt[$m]['all_give'];
                $fmt[$m]['date_time']   = date("Y-m-d H:i:s",$fmt[$m]['date_time']);
            }
            $total['cash_pay_money']         = $cash_pay_money;
            $total['bank_pay_money']         = $bank_pay_money;
            $total['wx_alipay_open_card']    = $wx_alipay_open_card;
            $total['wxpay_pay_money']        = $wxpay_pay_money;
            $total['open_card_give_gift']    = $open_card_give_gift;
            $total['open_card_sum']          = $open_card_sum;
            $total['cash_recharge']          = $cash_recharge;
            $total['bank_recharge']          = $bank_recharge;
            $total['wx_alipay_recharge']     = $wx_alipay_recharge;
            $total['wxpay_pay_recharge']     = $wxpay_pay_recharge;
            $total['recharge_cash_gift_sum'] = $recharge_cash_gift_sum;
            $total['recharge_sum']           = $recharge_sum;
            $total['all_money']              = $all_money;
            $total['all_give']               = $all_give;
            $total['check_user']             = "";

            array_unshift($fmt,$total);

            $title = date("Ymd",$start_time) . " - " . date("Ymd",$end_time);

            if ($is_download) {
                @PhpExcel::exportOne($fmt,"会员卡明细","$title");
            }
            return $this->com_return(true,config('params.SUCCESS'),$fmt);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }


    /**
     * 会籍明细
     * @return array
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function userCardRechargeReport()
    {
        if ($this->request->method() == "OPTIONS") {
            return $this->com_return(true,config('params.SUCCESS'));
        }

        $date_time     = $this->request->param('date_time','');//时间区间,以逗号隔开
        $group_by      = $this->request->param('group_by','');//按人聚合
        $is_download   = $this->request->param('is_download','');

        try {
            $date_time_arr = explode(",",$date_time);
            $time_is_ok = $date_time_arr[1] - $date_time_arr[0];
            $sixty_date_s = 24 * 60 * 60 * 60;
            if ($time_is_ok > $sixty_date_s) {
                return $this->com_return(false,"有效区间60天,请选择有效日期区间");
            }

            $start_time = strtotime(date("Ymd",$date_time_arr[0]));
            $end_time   = strtotime(date("Ymd",$date_time_arr[1])) + 60 * 60 * 24 - 1;

            $totalCommonObj = new TotalCommon();

            if ($group_by == "user") {
                //按会员分组
                $res = $totalCommonObj->groupUserMxTotal($start_time,$end_time,$is_download);
            }elseif($group_by == "card"){
                //按会员卡分组
                $res = $totalCommonObj->groupCardMxTotal($start_time,$end_time,$is_download);
            }elseif ($group_by == "referrer"){
                //按推荐人分组
                $res = $totalCommonObj->groupReferrerMxTotal($start_time,$end_time,$is_download);
            }else{
                //按日期分组
                $res = $totalCommonObj->memberMxTotal($start_time,$end_time,$is_download);
            }

            return $this->com_return(true,config('params.SUCCESS'),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

}