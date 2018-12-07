<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 上午11:12
 */

namespace app\common\controller;


use app\common\model\ManageSalesman;
use think\Request;

class ReceptionAuthAction extends BaseController
{
    /**
     * 初始化方法,其余控制器继承此方法，进行判断登录
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _initialize()
    {
        parent::_initialize();
        $method = Request::instance()->method();

        if ($method != "OPTIONS"){
            $Token = Request::instance()->header("Token","");
            if (empty($Token)){
                return json($this->com_return(false, '登陆失效' , null, 403))->send();
            }

            $manageSalesModel = new ManageSalesman();

            $reserve = config("salesman.salesman_type")[6]['key'];
            $cashier = config("salesman.salesman_type")[7]['key'];

            $stype_key_str = "$reserve,$cashier";

            $is_exist = $manageSalesModel
                ->alias("ms")
                ->join("mst_salesman_type mst","mst.stype_id = ms.stype_id")
                ->where("ms.reception_token",$Token)
                ->where('mst.stype_key','IN',$stype_key_str)
                ->field('ms.reception_token_lastime')
                ->find();
            if (empty($is_exist)){
                return json($this->com_return(false, '登陆失效' , null, 403))->send();
            }

            $time = time();//当前时间
            $reception_token_lastime = $is_exist['reception_token_lastime'];//上次刷新token时间
            $over_time = $reception_token_lastime + 24 * 60 * 60;   //过期时间
            if ($time > $over_time){
                return json($this->com_return(false, '登陆失效' , null, 403))->send();
            }
        }
    }

    /**
     * 根据前台登陆token获取服务人员信息
     * @param $reception_token
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function receptionTokenGetManageInfo($reception_token)
    {
        $manageSalesmanModel = new ManageSalesman();
        $manage_column = $manageSalesmanModel->manage_column;

        $manageInfo = $manageSalesmanModel
            ->alias("ms")
            ->join("manage_department md","md.department_id = ms.department_id")
            ->join("mst_salesman_type st","st.stype_id = ms.stype_id")
            ->where('ms.reception_token',$reception_token)
            ->field("md.department_title")
            ->field("st.stype_key,st.stype_name")
            ->field($manage_column)
            ->find();
        $manageInfo = json_decode(json_encode($manageInfo),true);
        return $manageInfo;
    }
}