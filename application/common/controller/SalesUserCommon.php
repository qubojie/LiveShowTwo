<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午6:42
 */

namespace app\common\controller;


use app\common\model\ManageSalesman;
use think\Controller;

class SalesUserCommon extends Controller
{
    /**
     * 根据sid获取销售人员信息
     * @param $sid
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getSalesManInfo($sid)
    {
        $salesModel = new ManageSalesman();
        $admin_column = $salesModel->admin_column;
        $salesmanInfo = $salesModel
            ->alias("ms")
            ->where('sid',$sid)
            ->field($admin_column)
            ->find();
        $salesmanInfo = json_decode(json_encode($salesmanInfo),true);
        return $salesmanInfo;
    }

    /**
     * 获取服务人员负责人列表或者非负责人列表
     * @param $is_governor
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGovernorSalesman($is_governor)
    {
        $salesModel = new ManageSalesman();
        $admin_column = $salesModel->admin_column;
        $where['ms.is_governor'] = ['eq',"$is_governor"];

        $salesmanList = $salesModel
            ->alias("ms")
            ->join("mst_salesman_type mst","mst.stype_id = ms.stype_id")
            ->where($where)
            ->where('mst.stype_key',config("salesman.salesman_type")['5']['key'])
            ->field($admin_column)
            ->select();

        $salesmanList = json_decode(json_encode($salesmanList),true);

        return $salesmanList;
    }


    /**
     * 电话号码获取人员信息
     * @param $phone
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function phoneGetSalesmanInfo($phone)
    {
        $salesModel = new ManageSalesman();
        $salesmanInfo = $salesModel
            ->alias("ms")
            ->join("el_mst_salesman_type mst","mst.stype_id = ms.stype_id")
            ->where("phone",$phone)
            ->field("ms.sid,ms.department_id,ms.stype_id,ms.sales_name,ms.statue,ms.phone,ms.nickname,ms.avatar,ms.sex")
            ->field("mst.stype_key")
            ->find();
        $salesmanInfo = json_decode(json_encode($salesmanInfo),true);
        return $salesmanInfo;
    }
}