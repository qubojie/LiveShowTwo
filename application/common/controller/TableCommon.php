<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午6:08
 */

namespace app\common\controller;


use app\common\model\MstTable;
use app\common\model\TableBusiness;
use app\common\model\TableRevenue;
use app\common\model\MstTableArea;
use app\common\model\MstTableLocation;

class TableCommon extends BaseController
{
    /**
     * 根据桌id获取桌信息
     * @param $table_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function tableIdGetInfo($table_id)
    {
        $tableModel = new MstTable();
        $column = $tableModel->column;
        for ($i = 0; $i < count($column); $i++){
            $column[$i] = "t.".$column[$i];
        }
        $tableInfo = $tableModel
            ->alias('t')
            ->join('mst_table_area ta','ta.area_id = t.area_id','LEFT')
            ->join('manage_salesman s','s.sid = ta.sid','LEFT')
            ->where('t.table_id',$table_id)
            ->where('t.is_enable',1)
            ->where('t.is_delete',0)
            ->field($column)
            ->field('ta.sid')
            ->field('s.sales_name')
            ->find();
        $tableInfo = json_decode(json_encode($tableInfo),true);
        return $tableInfo;
    }

    /**
     * trid获取桌位信息
     * @param $trid
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function tridGetTableInfo($trid)
    {
        $tableRevenueModel = new TableRevenue();
        $column     = $tableRevenueModel->column;
        foreach ($column as $key => $val) {
            $column[$key] = "tr.".$val;
        }
        $tableRevenueInfo = $tableRevenueModel
            ->alias("tr")
            ->join("mst_table mt","mt.table_id = tr.table_id")
            ->where('tr.trid',$trid)
            ->field("mt.table_no")
            ->field($column)
            ->find();
        $tableRevenueInfo = json_decode(json_encode($tableRevenueInfo),true);
        return $tableRevenueInfo;
    }

    /**
     * 获取桌台列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTableList()
    {
        $tableLocationModel = new MstTableLocation();
        $table_location = $tableLocationModel
            ->alias("tl")
            ->join("mst_table_area ta","ta.location_id = tl.location_id")
            ->where("tl.is_delete","0")
            ->order("tl.sort")
            ->group("tl.location_id")
            ->field("tl.location_id,tl.location_title")
            ->select();

        $info = json_decode(json_encode($table_location),true);

        $tableAreaModel = new MstTableArea();
        $tableModel     = new MstTable();
        for ($i = 0; $i < count($info); $i ++){
            $location_id = $info[$i]['location_id'];
            $table_area = $tableAreaModel
                ->alias("ta")
                ->where("ta.location_id",$location_id)
                ->where("ta.is_delete","0")
                ->order("ta.sort")
                ->group("ta.area_id")
                ->field("ta.area_id,ta.area_title")
                ->select();
            $area_info = json_decode(json_encode($table_area),true);
            $info[$i]['area_info'] = $area_info;
            for ($n = 0; $n < count($area_info); $n ++){
                $area_id = $area_info[$n]['area_id'];
                $table_info = $tableModel
                    ->alias("t")
                    ->where("t.area_id",$area_id)
                    ->where("t.is_delete","0")
                    ->where("t.is_enable","1")
                    ->order("t.table_no")
                    ->select();
                $table_info = json_decode(json_encode($table_info),true);
                $info[$i]['area_info'][$n]['table_info'] = $table_info;
            }
        }
        foreach ($info as $key => $val){
            $area_info = $info[$key]['area_info'];
            foreach ($area_info as $k => $v){
                $table_info = $area_info[$k]['table_info'];
                if (empty($table_info)){
                    unset($info[$key]['area_info'][$k]);
                }
            }
            sort($info[$key]['area_info']);
        }

        sort($info);
        foreach ($info as $z => $s){
            if (empty($info[$z]['area_info'])){
                unset($info[$z]);
            }
        }
        sort($info);
        return $info;
    }

    /**
     * 小程序筛选桌号
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTableAllList()
    {
        $tableModel         = new MstTable();
        $tableLocationModel = new MstTableLocation();
        $table_location = $tableLocationModel
            ->alias("tl")
            ->join("mst_table_area ta","ta.location_id = tl.location_id")
            ->where("tl.is_delete","0")
            ->order("tl.sort")
            ->group("tl.location_id")
            ->field("tl.location_id,tl.location_title")
            ->select();
        $info = json_decode(json_encode($table_location),true);
        for ($n = 0; $n < count($table_location); $n ++){
            $location_id = $table_location[$n]['location_id'];
            $table_info = $tableModel
                ->alias("t")
                ->join("mst_table_area ta","ta.area_id = t.area_id","LEFT")
                ->join("mst_table_location tl","tl.location_id = ta.location_id","LEFT")
                ->where("ta.location_id",$location_id)
                ->where("t.is_delete","0")
                ->where("t.is_enable","1")
                ->order("t.table_no")
                ->select();
            $table_info = json_decode(json_encode($table_info),true);
            $info[$n]['table_info'] = $table_info;
        }
        return $info;
    }

    /**
     * table_id检测是否已被预约或者是否已被开台
     * @param $table_id
     * @param $date_time
     * @return bool
     */
    public function tableIdCheckCanOpenOrRevenue($table_id,$date_time)
    {
        $star_time = strtotime(date("Ymd",$date_time));
        $end_time  = $star_time + 24 * 60 * 60;
        /*首先查看是否被预约  On*/
        $pending_payment = config("order.table_reserve_status")['pending_payment']['key'];
        $success         = config("order.table_reserve_status")['success']['key'];
        $open            = config("order.table_reserve_status")['open']['key'];
        $status_str      = "$pending_payment,$success,$open";
        $tableRevenueModel = new TableRevenue();
        $can_open = $tableRevenueModel
            ->where("table_id",$table_id)
            ->where("status","IN",$status_str)
            ->whereTime('reserve_time','between',[$star_time,$end_time])
            ->count();
        /*首先查看是否被预约  Off*/

        /*查看是否被开台 On*/
        $tableBusinessModel = new TableBusiness();
        $is_open = $tableBusinessModel
            ->where('table_id',$table_id)
            ->where('status',config('order.table_business_status')['open']['key'])
            ->whereTime('open_time','between',[$star_time,$end_time])
            ->count();
        if ($is_open > 0 || $can_open > 0) {
            return false;
        }else{
            return true;
        }
        /*查看是否被开台 Off*/
    }

    /**
     * 清台
     * @param $buid
     * @param $action_user
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cleanTablePublic($buid,$action_user)
    {
        /*首先查看当前订台是否有未支付订单 On*/
        //TODO '检测是否有未支付订单 待完善'
        //TODO '检测是否有未支付订单 待完善'
        //TODO '检测是否有未支付订单 待完善'
        //TODO '检测是否有未支付订单 待完善'
        //TODO '检测是否有未支付订单 待完善'
        //TODO '检测是否有未支付订单 待完善'
        //TODO '检测是否有未支付订单 待完善'
        //TODO '检测是否有未支付订单 待完善'
        /*首先查看当前订台是否有未支付订单 Off*/

        $tableBusinessModel = new TableBusiness();

        $params = [
            "status"     => config('order.table_business_status')['clean']['key'],
            "clean_time" => time(),
            "updated_at" => time()
        ];

        $res = $tableBusinessModel
            ->where('buid',$buid)
            ->update($params);
        if ($res == false) {
            return $this->com_return(false,config('params.FAIL'));
        }

        $consumptionCommonObj = new ConsumptionCommon();
        $tableBusinessInfo    = $consumptionCommonObj->buidGetTableBusinessInfo("$buid");
        $table_id = $tableBusinessInfo['table_id'];
        $table_no = $tableBusinessInfo['table_no'];
        $desc     = $action_user. " 清台操作( $table_no )";
        $type     = config("order.table_action_type")['clean_table']['key'];

        insertTableActionLog(microtime(true) * 10000,"$type","$table_id","$table_no","$action_user","$desc","","");
        return $this->com_return(true,config("params.SUCCESS"));
    }
}