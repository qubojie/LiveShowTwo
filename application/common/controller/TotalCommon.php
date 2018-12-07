<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午2:30
 */

namespace app\common\controller;


use think\Db;
use think\Exception;

class TotalCommon extends BaseController
{
    /**
     * 充值统计
     * @param $start_time
     * @param $end_time
     * @return mixed
     */
    public function rechargeTotal($start_time,$end_time)
    {
        $res = Db::query("
           select ebs.ettlement_at as date_time,
           sum(if(ebr.pay_type='cash',ebr.amount,0)) as cash_recharge,
           sum(if(ebr.pay_type='bank',ebr.amount,0)) as bank_recharge,
           sum(if(ebr.pay_type='wxpay_c',ebr.amount,0)) as wxpay_c_recharge,
           sum(if(ebr.pay_type='alipay_c',ebr.amount,0)) as alipay_c_recharge,
           sum(if(ebr.pay_type='wxpay',ebr.amount,0)) as wxpay_pay_recharge,
           sum(ebr.amount) as recharge_sum,
           sum(ebr.cash_gift) as recharge_cash_gift_sum
           from el_bill_settlement ebs
           join el_bill_refill ebr 
           where ebs.ettlement_at BETWEEN $start_time and $end_time
           group by ebs.ettlement_at
           order by ebs.ettlement_at DESC
        ");

        return $res;
    }

    /**
     * 开卡统计
     * @param $start_time
     * @param $end_time
     * @param string $pay_type
     * @return mixed
     */
    public function vipOpenCardTotal($start_time,$end_time,$pay_type = "")
    {
        $pending_ship    = \config("order.open_card_status")['pending_ship']['key'];//待发货
        $pending_receipt = \config("order.open_card_status")['pending_receipt']['key'];//待收货
        $completed       = \config("order.open_card_status")['completed']['key'];//完成

        $sale_status = "$pending_ship,$pending_receipt,$completed";

        $cash_pay_type     = \config("order.pay_method")['cash']['key'];
        $bank_pay_type     = \config("order.pay_method")['bank']['key'];
        $wxpay_c_pay_type  = \config("order.pay_method")['wxpay_c']['key'];
        $alipay_c_pay_type = \config("order.pay_method")['alipay_c']['key'];
        $wxpay_pay_type    = \config("order.pay_method")['wxpay']['key'];

        $res = Db::query("
           select FROM_UNIXTIME(bcf.pay_time,'%Y-%m-%d') as date_time,
           sum(if(bcf.pay_type='cash',bcf.deal_price,0)) as cash_pay_money,
           sum(if(bcf.pay_type='bank',bcf.deal_price,0)) as bank_pay_money,
           sum(if(bcf.pay_type='wxpay_c',bcf.deal_price,0)) as wxpay_c_pay_money,
           sum(if(bcf.pay_type='alipay_c',bcf.deal_price,0)) as alipay_c_pay_money,
           sum(if(bcf.pay_type='wxpay',bcf.deal_price,0)) as wxpay_pay_money,
           sum(bcfd.card_cash_gift) as card_cash_gift_sum,
           sum(bcfd.card_job_cash_gif) as card_job_cash_gif_sum,
           sum(bcf.deal_price) as open_card_sum
           from el_bill_card_fees bcf 
           inner join el_bill_card_fees_detail bcfd on bcf.vid = bcfd.vid
           where bcf.pay_time BETWEEN $start_time and $end_time
           and bcf.sale_status between 1 and 3
           group by FROM_UNIXTIME(bcf.pay_time,'%Y-%m-%d')
           order by FROM_UNIXTIME(bcf.pay_time,'%Y-%m-%d') DESC
        ");

        return $res;
    }

    /**
     * 按人聚合
     * @param $start_time
     * @param $end_time
     * @param int $is_download
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function groupUserMxTotal($start_time,$end_time,$is_download = 0)
    {
        try {
            $title = date("Ymd",$start_time) . " - " . date("Ymd",$end_time);

            $res = Db::name("view_card_recharge_sum")
                ->where("date_time","between time",[$start_time,$end_time])
                ->group("uid,phone,name,card_name")
                ->field("uid,phone,name,card_name,sum(open_card_sum) open_card_sum,sum(card_cash_gift_sum) card_cash_gift_sum,sum(card_job_cash_gif_sum) card_job_cash_gif_sum,sum(recharge_money_sum) recharge_money_sum,sum(recharge_cash_gift) recharge_cash_gift")
                ->select();

            if (empty($res)){
                return $res;
            }

            $res = _unsetNull_to_o($res);

            for ($i = 0; $i < count($res); $i++) {
                $uid = $res[$i]['uid'];
                $userInfo = getUserInfo("$uid");

                $referrer_type = $userInfo['referrer_type'];
                $referrer_id   = $userInfo['referrer_id'];

                if ($referrer_type == config("salesman.salesman_type")[2]['key']) {
                    //如果是用户推荐
                    $referrer_info = Db::name("user")
                        ->where("uid",$referrer_id)
                        ->field("name")
                        ->find();

                }else{
                    //如果是销售推荐
                    $referrer_info = Db::name("manage_salesman")
                        ->where("sid",$referrer_id)
                        ->field("sales_name name")
                        ->find();

                    $referrer_info = json_decode(json_encode($referrer_info),true);
                }
                $referrer_name = "";
                if (!empty($referrer_info)) {
                    $referrer_name = $referrer_info['name'];
                }
                $res[$i]['referrer_name'] = $referrer_name;
            }


            if ($is_download) {
                @PhpExcel::exportThree($res,"会籍明细会员分组","$title");
                exit;
            }
            return $res;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 会员明细统计
     * @param $start_time
     * @param $end_time
     * @param int $is_download
     * @return array
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function memberMxTotal($start_time,$end_time,$is_download = 0)
    {
        try {
            $title = date("Ymd",$start_time) . " - " . date("Ymd",$end_time);
            $res = Db::name("view_card_recharge_sum")
                ->where("date_time","between","$start_time,$end_time")
                ->select();

            if (empty($res)) {
                return $res;
            }

            $res = _unsetNull_to_o($res);

            for ($i = 0; $i < count($res); $i++) {
                $uid = $res[$i]['uid'];
                $userInfo = getUserInfo("$uid");

                $referrer_type = $userInfo['referrer_type'];
                $referrer_id   = $userInfo['referrer_id'];

                if ($referrer_type == config("salesman.salesman_type")[2]['key']) {
                    //如果是用户推荐
                    $referrer_info = Db::name("user")
                        ->where("uid",$referrer_id)
                        ->field("name")
                        ->find();

                }else{
                    //如果是销售推荐
                    $referrer_info = Db::name("manage_salesman")
                        ->where("sid",$referrer_id)
                        ->field("sales_name name")
                        ->find();

                    $referrer_info = json_decode(json_encode($referrer_info),true);
                }
                $referrer_name = "";
                if (!empty($referrer_info)) {
                    $referrer_name = $referrer_info['name'];
                }
                $res[$i]['referrer_name'] = $referrer_name;
            }

            if ($is_download) {
                @PhpExcel::exportTwo($res,"会籍明细日期分组","$title");
                exit;
            }

            $res = array_group_by($res,"date_time");

            $res = array_values($res);

            $qbj = [];
            for ($i  = 0; $i < count($res); $i ++) {
                $qbj[$i]['date_time'] = $res[$i][0]['date_time'];
                $qbj[$i]['name'] = count($res[$i]);

                $all_card_sum     = 0;//总共开卡金额
                $all_recharge_sum = 0;//总共充值金额


                $all_card_cash_gift_sum    = 0;//开卡赠送礼金
                $all_card_job_cash_gif_sum = 0;//开卡赠送推荐人礼金
                $all_recharge_cash_gift    = 0;//储值赠送礼金
                for ($m = 0; $m < count($res[$i]); $m ++) {
                    $all_card_sum              += $res[$i][$m]['open_card_sum'];
                    $all_recharge_sum          += $res[$i][$m]['recharge_money_sum'];
                    $all_card_cash_gift_sum    += $res[$i][$m]['card_cash_gift_sum'];
                    $all_card_job_cash_gif_sum += $res[$i][$m]['card_job_cash_gif_sum'];
                    $all_recharge_cash_gift    += $res[$i][$m]['recharge_cash_gift'];
                }
                $all_give_gift    = $all_card_cash_gift_sum + $all_card_job_cash_gif_sum + $all_recharge_cash_gift;//总共赠送金额

                $qbj[$i]['open_card_sum'] = $all_card_sum;
                $qbj[$i]['recharge_money_sum'] = $all_recharge_sum;
                $qbj[$i]['all_give_gift'] = $all_give_gift;

                $qbj[$i]['children'] = $res[$i];
            }
            return $qbj;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 按会员卡统计
     * @param $start_time
     * @param $end_time
     * @param int $is_download
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function groupCardMxTotal($start_time,$end_time,$is_download = 0)
    {
        $title = date("Ymd",$start_time) . " - " . date("Ymd",$end_time);
        $res = Db::name("view_card_recharge_sum")
            ->where("date_time","between time",[$start_time,$end_time])
            ->group("card_id,card_name")
            ->field("card_id,card_name,sum(open_card_sum) open_card_sum,sum(card_cash_gift_sum) card_cash_gift_sum,sum(card_job_cash_gif_sum) card_job_cash_gif_sum,sum(recharge_money_sum) recharge_money_sum,sum(recharge_cash_gift) recharge_cash_gift")
            ->select();

        $res = _unsetNull_to_o($res);

        if ($is_download) {
            @PhpExcel::exportCard($res,"会籍明细会员分组","$title");
            exit;
        }

        return $res;
    }

    /**
     * 按推荐人统计
     * @param $start_time
     * @param $end_time
     * @param int $is_download
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function groupReferrerMxTotal($start_time,$end_time,$is_download = 0)
    {
        $title = date("Ymd",$start_time) . " - " . date("Ymd",$end_time);

//        $start_time = date("Y-m-d",$start_time);
//        $end_time   = date("Y-m-d",$end_time);

        $res = Db::name("view_card_recharge_sum")
            ->alias("vcr")
            ->where("vcr.date_time","between time",[$start_time,$end_time])
            ->group("vcr.referrer_id,vcr.referrer_type")
            ->field("vcr.referrer_type,vcr.referrer_id,sum(vcr.open_card_sum) open_card_sum,sum(vcr.card_cash_gift_sum) card_cash_gift_sum,sum(vcr.card_job_cash_gif_sum) card_job_cash_gif_sum,sum(vcr.recharge_money_sum) recharge_money_sum,sum(vcr.recharge_cash_gift) recharge_cash_gift")
            ->select();

        if (empty($res)){
            return $res;
        }

        $res = _unsetNull_to_o($res);

        for ($i = 0; $i < count($res); $i++) {
            $referrer_type = $res[$i]['referrer_type'];
            $referrer_id = $res[$i]['referrer_id'];

            if ($referrer_type == config("salesman.salesman_type")[2]['key']) {
                //如果是用户推荐
                $referrer_info = Db::name("user")
                    ->where("uid",$referrer_id)
                    ->field("name,phone")
                    ->find();

            }else{
                //如果是销售推荐
                $referrer_info = Db::name("manage_salesman")
                    ->where("sid",$referrer_id)
                    ->field("sales_name name,phone")
                    ->find();

                $referrer_info = json_decode(json_encode($referrer_info),true);
            }
            $referrer_name = "平台";
            $phone = "";
            if (!empty($referrer_info)) {
                $referrer_name = $referrer_info['name'];
                $phone = $referrer_info['phone'];
            }
            if (!empty($phone)) {
                $res[$i]['referrer_name'] = $referrer_name."($phone)";
            }else{
                $res[$i]['referrer_name'] = $referrer_name;
            }
        }

        if ($is_download) {
            @PhpExcel::exportReferrer($res,"会籍明细会员分组","$title");
            exit;
        }

        return $res;

    }
}