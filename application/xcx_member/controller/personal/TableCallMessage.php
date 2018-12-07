<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午5:04
 */

namespace app\xcx_member\controller\personal;


use app\common\controller\MemberAuthAction;
use app\common\model\ManageSalesman;
use app\common\model\MstTable;
use app\common\model\MstTableArea;
use app\common\model\TableMessage;
use think\Exception;
use think\Request;

class TableCallMessage extends MemberAuthAction
{
    /**
     * 获取桌号
     * @return array
     */
    public function tableNumber()
    {
        try {
            $mstTable = new MstTable();
            $where['is_enable'] = 1;
            $where['is_delete'] = 0;
            $list = $mstTable
                ->where($where)
                ->field('table_id,table_no')
                ->order('table_no', 'asc')
                ->select();
            return $this->com_return(true, config("params.SUCCESS"), $list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取服务信息
     * @param Request $request
     * @return array
     */
    public function getCallMessage(Request $request)
    {
        $message  = $request->param("message", "");//消息内容
        $table_id = $request->param("table_id", "");//桌号
        try {
            $tableModel = new MstTable();
            $table_info = $tableModel
                ->where('table_id', $table_id)
                ->find();
            $table_no = $table_info['table_no'];
            $area_id  = $table_info['area_id'];

            $tableAreaModel = new MstTableArea();
            $area_info = $tableAreaModel
                ->where('area_id', $area_id)
                ->find();
            $sid = $area_info['sid'];

            $manageSalesmanModel = new ManageSalesman();
            $manageSalesman_info = $manageSalesmanModel
                ->where('sid',$sid)
                ->find();
            $manage_name  = $manageSalesman_info['sales_name'];
            $manage_phone = $manageSalesman_info['phone'];
            $manageInfo   = $manage_name.'('.$manage_phone.')';

            $content = '【桌号】' . $table_no . ' 【服务】' . $message. ' 【服务员组长】'. $manageInfo;
            $time = time();

            $data = [
                'type' => 'call',
                'content' => $content,
                'ssid' => $sid,
                'is_read' => 0,
                'created_at' => $time,
                'updated_at' => $time,
            ];

            $tableMessageModel = new TableMessage();
            $is_ok = $tableMessageModel
                ->insertGetId($data);
            if ($is_ok) {
                return $this->com_return(true, config("params.SUCCESS"));
            } else {
                return $this->com_return(false, config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}