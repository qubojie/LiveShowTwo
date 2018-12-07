<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 上午11:58
 */

namespace app\common\controller;


use app\common\model\BillSubscription;
use app\common\model\TableRevenue;
use think\Db;
use think\Exception;

class SubscriptionOrderCommon extends BaseController
{
    /**
     * 预约订单id获取订单信息
     */
    public function suidGetOrderInfo($suid)
    {
        try {
            $billSubscriptionModel = new BillSubscription();
            $order_info =$billSubscriptionModel
                ->where('suid',$suid)
                ->find();
            $order_info = json_decode(json_encode($order_info),true);
            if (!empty($order_info)){
                return $order_info;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 台位预定定金缴费单更新
     * @param array $params
     * @param $suid
     * @return bool
     */
    public function changeBillSubscriptionInfo($params = array(),$suid)
    {
        try {
            $billSubscriptionModel = new BillSubscription();
            $is_ok = $billSubscriptionModel
                ->where('suid',$suid)
                ->update($params);
            if ($is_ok !== false) {
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 台位预定及营收状况信息表更新
     * @param array $params
     * @param $trid
     * @return bool
     */
    public function changeTableRevenueInfo($params = array(),$trid)
    {
        try {
            $tableRevenueModel = new TableRevenue();
            $is_ok = $tableRevenueModel
                ->where("trid",$trid)
                ->update($params);
            if ($is_ok !== false) {
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 根据trid查看是否是服务人员预定
     * @param $trid
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function tridGetReserveUser($trid)
    {
        try {
            $tableRevenueModel = new TableRevenue();
            $reserve_way_res = $tableRevenueModel
                ->alias("tr")
                ->join("manage_salesman ms","ms.sid = tr.sid")
                ->join("mst_table mt","mt.table_id = tr.table_id")
                ->where("tr.trid",$trid)
                ->field("mt.table_no")
                ->field("tr.table_id,tr.reserve_way,tr.reserve_time")
                ->field("ms.phone sales_phone,ms.sales_name")
                ->find();
            $reserve_way_res = json_decode(json_encode($reserve_way_res),true);
            if (!empty($reserve_way_res)){
                return $reserve_way_res;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

}