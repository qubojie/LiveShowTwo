<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午2:47
 */

namespace app\common\controller;


use app\common\model\BillRefill;
use app\common\model\ManageSalesman;
use app\common\model\MstRefillAmount;
use think\Db;
use think\Exception;

class RechargeCommon extends BaseController
{
    /**
     * 充值公共部分
     * @param $uid
     * @param $amount
     * @param $cash_gift
     * @param $pay_type
     * @param $pay_line_type
     * @param $check_user
     * @param $check_reason
     * @param string $referrer_phone
     * @return array
     */
    public function rechargePublicAction($uid,$amount,$cash_gift,$pay_type,$pay_line_type,$check_user,$check_reason,$referrer_phone = '')
    {
        try {
            $manageSalesmanModel = new ManageSalesman();

            $referrer_id   = config("salesman.salesman_type")[3]['key'];
            $referrer_type = config("salesman.salesman_type")[3]['name'];

            if (!empty($referrer_phone)){
                //根据电话号码获取推荐营销信息
                $manageInfo = $manageSalesmanModel
                    ->alias('ms')
                    ->join('mst_salesman_type mst','mst.stype_id = ms.stype_id')
                    ->where('ms.phone',$referrer_phone)
                    ->where('ms.statue',config("salesman.salesman_status")['working']['key'])
                    ->field('ms.sid,mst.stype_key')
                    ->find();

                $manageInfo = json_decode(json_encode($manageInfo),true);

                if (!empty($manageInfo)){

                    //只给营销记录,其他都算平台
                    if ($manageInfo['stype_key'] == config("salesman.salesman_type")[0]['key'] ||$manageInfo['stype_key'] == config("salesman.salesman_type")[0]['key'] ) {
                        $referrer_id   = $manageInfo['sid'];
                        $referrer_type = $manageInfo['stype_key'];
                    }
                }else{
                    return $this->com_return(false,\config("params.SALESMAN_NOT_EXIST"));
                }
            }

            //插入用户充值单据表
            $rfid = generateReadableUUID("RF");

            $billRefillParams = [
                "rfid"          => $rfid,
                "referrer_type" => $referrer_type,
                "referrer_id"   => $referrer_id,
                "uid"           => $uid,
                "pay_type"      => $pay_type,
                "pay_line_type" => $pay_line_type,
                "amount"        => $amount,
                "cash_gift"     => $cash_gift,
                "status"        => config("order.recharge_status")['pending_payment']['key'],
                "check_user"    => $check_user,
                "check_time"    => time(),
                "check_reason"  => $check_reason,
                "created_at"    => time(),
                "updated_at"    => time()
            ];

            $billRefillModel = new BillRefill();

            $res = $billRefillModel
                ->insert($billRefillParams);

            $return_data = [
                "rfid"      => $rfid,
                "amount"    => $amount,
                "cash_gift" => $cash_gift,
            ];

            if ($res){
                return $this->com_return(true,config("params.SUCCESS"),$return_data);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 更新支付单据状态
     * @param array $params
     * @param $rfid
     * @return bool
     */
    public function updateBillRefill($params = array(),$rfid)
    {
        try {
            $billRefillModel = new BillRefill();

            $is_ok = $billRefillModel
                ->where("rfid",$rfid)
                ->update($params);

            if ($is_ok !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 充值返还数据
     * @param $uid
     * @param $referrer_type
     * @param $consumption_money
     * @param $refill_job_cash_gift
     * @param $refill_job_commission
     * @return array|null
     */
    public function rechargeReturnMoney($uid,$referrer_type,$consumption_money,$refill_job_cash_gift,$refill_job_commission)
    {
        if ($consumption_money <= 0){
            //如果余额消费和现金消费金额为0 则不返还
            return NULL;
        }

        if ($referrer_type == config("salesman.salesman_type")['2']['key']){
            //如果是用户推荐
            $job_cash_gift_return_money  = intval($consumption_money * ($refill_job_cash_gift/100));//充值推荐人返礼金
            $job_commission_return_money = intval($consumption_money * ($refill_job_commission/100));//充值推荐人返佣金
        }else{
            $job_cash_gift_return_money  = 0;
            $job_commission_return_money = 0;

        }

        $params = [
            "job_cash_gift_return_money" => $job_cash_gift_return_money,
            "job_commission_return_money"=> $job_commission_return_money
        ];

        return $params;
    }

    /**
     * 获取充值金额列表
     * @param $pagesize
     * @param $nowPage
     * @return \think\Paginator
     * @throws \think\exception\DbException
     */
    public function rechargeList($pagesize,$nowPage)
    {
        if (empty($pagesize)) $pagesize = config('page_size');
        $config = [
            "page" => $nowPage,
        ];
        $refillAmountModel = new MstRefillAmount();
        $xcx_column = $refillAmountModel->xcx_column;
        $list = $refillAmountModel
            ->order("sort")
            ->field($xcx_column)
            ->where("is_enable",1)
            ->paginate($pagesize,false,$config);
        return $list;
    }

    /**
     * 储值订单id获取储值订单信息
     * @param $rfid
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rfidGetRechargeInfo($rfid)
    {
        $billRefillModel = new BillRefill();
        $res = $billRefillModel
            ->where('rfid',$rfid)
            ->find();
        $res = json_decode(json_encode($res),true);
        return $res;
    }

}