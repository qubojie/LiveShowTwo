<?php
/**
 * Created by 棒哥的IDE.
 * Email QuBoJie@163.com
 * QQ 3106954445
 * WeChat 17703981213
 * User: QuBoJie
 * Date: 2018/12/3
 * Time: 上午11:08
 * App: LiveShowTwo
 */

namespace app\common\controller;


use app\common\model\TableBusiness;
use app\common\model\TableRevenue;

class BusinessCommon extends BaseController
{
    /**
     * 更新开台信息
     * @param $params
     * @param $buid
     * @return bool
     */
    public function updateTableBusinessInfo($params,$buid)
    {
        $tableBusinessModel = new TableBusiness();

        $res = $tableBusinessModel
            ->where("buid",$buid)
            ->update($params);
        if ($res !== false) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 插入新的开台信息
     * @param $params
     * @return bool
     */
    public function insertNewTableBusinessInfo($params)
    {

        $tableBusinessModel = new TableBusiness();

        $res = $tableBusinessModel
            ->insert($params);
        if ($res !== false) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 转台
     * @param $now_table_info '当前台为信息'
     * @param $to_table_id  '转至台位id'
     * @param $begin_time
     * @param $end_time
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function turnTable($now_table_info,$to_table_id,$begin_time,$end_time)
    {
        /*查看台位是否已被预约 On*/
        $pending_payment = config("order.table_reserve_status")['pending_payment']['key'];//待付定金
        $reserve_success = config("order.table_reserve_status")['success']['key'];//预定成功
        $already_open    = config("order.table_reserve_status")['open']['key'];//已开台
        $status_str      = "$pending_payment,$reserve_success,$already_open";

        $tableRevenueModel = new TableRevenue();
        $revenueCount = $tableRevenueModel
            ->where('table_id',$to_table_id)
            ->where('status','IN',$status_str)
            ->whereTime("reserve_time","between",[$begin_time,$end_time])
            ->count();
        if ($revenueCount > 0) {
            return $this->com_return(false,config("params.REVENUE")['TABLE_NOT_LDLE']);
        }
        /*查看台位是否已被预约 Off*/

        /*获取转至台位的tableInfo On*/
        $tableCommonObj = new TableCommon();
        $toTableInfo = $tableCommonObj->tableIdGetInfo($to_table_id);
        /*获取转至台位的tableInfo Off*/
        $params = [
            "table_id"   => $to_table_id,
            "table_no"   => $toTableInfo['table_no'],
            "sid"        => $toTableInfo['sid'],
            "sname"      => $toTableInfo['sales_name'],
            "updated_at" => time()
        ];
        $businessCommonObj = new BusinessCommon();

        $buid = $now_table_info['buid'];
        $res = $businessCommonObj->updateTableBusinessInfo($params,"$buid");
        if ($res) {
            return $this->com_return(true,config("params.SUCCESS"));
        }else{
            return $this->com_return(false,config("params.FAIL"));
        }
    }

    /**
     * 转拼
     * @param $now_table_info
     * @param $to_table_id
     * @param $begin_time
     * @param $end_time
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function turnSpelling($now_table_info,$to_table_id,$begin_time,$end_time)
    {
        /*获取转至台位的tableInfo On*/
        $tableCommonObj = new TableCommon();
        $toTableInfo = $tableCommonObj->tableIdGetInfo($to_table_id);
        /*获取转至台位的tableInfo Off*/
        //查询当前桌台是否有开台信息
        $tableBusinessModel = new TableBusiness();
        $spelling_num = $tableBusinessModel
            ->where('status',\config('order.table_business_status')['open']['key'])
            ->where("table_id",$to_table_id)
            ->whereTime('open_time','between',[$begin_time,$end_time])
            ->count();
        $to_table_no = $toTableInfo['table_no'];
        $number   = $spelling_num;
        $table_no = $to_table_no."(拼$number)";
        $params = [
            "table_id"   => $to_table_id,
            "table_no"   => $table_no,
            "sid"        => $toTableInfo['sid'],
            "sname"      => $toTableInfo['sales_name'],
            "updated_at" => time()
        ];
        $businessCommonObj = new BusinessCommon();
        $buid = $now_table_info['buid'];
        $res = $businessCommonObj->updateTableBusinessInfo($params,"$buid");
        if ($res) {
            return $this->com_return(true,config("params.SUCCESS"));
        }else{
            return $this->com_return(false,config("params.FAIL"));
        }

    }
}