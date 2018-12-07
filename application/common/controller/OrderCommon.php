<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午4:14
 */

namespace app\common\controller;


use app\common\model\BillCardFees;
use app\common\model\BillCardFeesDetail;
use app\common\model\BillPay;
use app\common\model\BillRefill;
use app\common\model\BillSubscription;
use think\Exception;

class OrderCommon extends BaseController
{
    /**
     * 开卡订单id获取订单信息
     * @param $vid
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function vidGetOrderInfo($vid)
    {
        //获取当前订单状态
        $billCardFeesModel = new BillCardFees();
        $res = $billCardFeesModel
            ->where('vid',$vid)
            ->find();

        $res = json_decode(json_encode($res),true);

        return $res;
    }

    /**
     * 点单pid获取订单支付金额
     * @param $pid
     * @return bool|mixed
     */
    public function getBillPayAmount($pid)
    {
        try {
            $billPayModel = new BillPay();

            $info = $billPayModel
                ->where("pid",$pid)
                ->where("sale_status",'1')
                ->field("order_amount")
                ->find();

            if (!empty($info)){
                return $info['order_amount'];
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 点单pid获取订单支付金额
     * @param $pid
     * @return bool|mixed
     */
    public function pidGetBillPayInfo($pid)
    {
        try {
            $billPayModel = new BillPay();
            $info = $billPayModel
                ->where("pid",$pid)
                ->find();
            $info = json_decode(json_encode($info),true);
            return $info;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取订单实际支付金额
     * @param $vid
     * @return bool|mixed
     */
    public function getOrderPayableAmount($vid)
    {
        try {
            $billCardFeesModel = new BillCardFees();

            $bill_info = $billCardFeesModel
                ->where('vid',$vid)
                ->where('sale_status','0')
                ->field('payable_amount')
                ->find();
            if (!empty($bill_info)) {
                return  $bill_info['payable_amount'];
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取订台押金支付金额
     * @param $suid
     * @return bool|mixed
     */
    public function getSubscriptionPayableAmount($suid)
    {
        try {
            $billSubscriptionModel = new BillSubscription();

            $info = $billSubscriptionModel
                ->where('suid',$suid)
                ->where('status','0')
                ->field('subscription')
                ->find();

            if (!empty($info)) {
                return  $info['subscription'];
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取充值金额
     * @param $rfid
     * @return bool|mixed
     */
    public function getBillRefillAmount($rfid)
    {
        try {
            $billRefillModel = new BillRefill();

            $info = $billRefillModel
                ->where('rfid',$rfid)
                ->where('status','0')
                ->field("amount")
                ->find();

            if (!empty($info)) {
                return  $info['amount'];
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 更新订单
     * @param array $params
     * @param $vid
     * @return bool
     */
    public function updateOrderStatus($params = array(),$vid)
    {
        try {
            $billCardFeesModel = new BillCardFees();
            $is_ok = $billCardFeesModel
                ->where('vid',$vid)
                ->update($params);
            if ($is_ok){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * vid获取开卡订单详情
     * @param $vid
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getBillCardFeesDetail($vid)
    {
        try {
            $billCardFeesDetailModel = new BillCardFeesDetail();
            $info = $billCardFeesDetailModel
                ->where('vid',$vid)
                ->find();
            $info = json_decode($info,true);
            if (!empty($info)) {
                return $info;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * rfid获取充值订单信息
     * @param $rfid
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function rfidGetBillRefillInfo($rfid)
    {
        try {
            $billRefillModel = new BillRefill();
            //获取订单信息
            $order_info = $billRefillModel
                ->where('rfid',$rfid)
                ->find();
            $order_info = json_decode(json_encode($order_info),true);
            if (!empty($order_info)) {
                return $order_info;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 插入开卡订单信息
     * @param $params
     * @return bool
     */
    public function insertBillCardFees($params)
    {
        $billCardFeesModel = new BillCardFees();

        $is_ok = $billCardFeesModel->insert($params);
        if ($is_ok){
            return $params['vid'];
        }else{
            return false;
        }
    }

    /**
     * 插入缴费订单礼品礼券快照
     * @param array $params
     * @return bool
     */
    public function billCardFeesDetail($params = array())
    {
        //return $params;
        $billCardFeesDetailModel = new BillCardFeesDetail();

        $is_ok = $billCardFeesDetailModel->insert($params);

        return $is_ok;

    }

    /**
     * 更新预约点单信息
     * @param $updateBillPayParams
     * @param $pid
     * @return bool
     */
    public function updateBillPay($updateBillPayParams,$pid)
    {
        $billPayModel = new BillPay();

        $is_ok = $billPayModel
            ->where("pid",$pid)
            ->update($updateBillPayParams);

        if ($is_ok !== false){
            return true;
        }else{
            return false;
        }
    }

}