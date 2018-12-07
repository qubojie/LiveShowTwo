<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 上午9:56
 */

namespace app\common\controller;


use app\common\model\ManageSalesman;

class ManageAuthAction extends BaseController
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
        $method = \think\Request::instance()->method();

        if ( $method != "OPTIONS"){
            $Token = \think\Request::instance()->header("Token","");
            if (empty($Token)){
                return json($this->com_return(false, '登陆失效' , null, 403))->send();
            }
            $salesmanModel = new ManageSalesman();
            $is_exist = $salesmanModel
                ->where("remember_token",$Token)
                ->field('token_lastime,statue')
                ->find();
            if (empty($is_exist)){
                return json($this->com_return(false, '登陆失效' , null, 403))->send();
            }
            $time = time();//当前时间
            $token_lastime = $is_exist['token_lastime'];//上次刷新token时间
            $over_time = $token_lastime + 604800;   //过期时间
            if ($time > $over_time){
//                exit(json_encode($this->com_return(false, '登陆失效' , null, 520403),JSON_UNESCAPED_UNICODE));
                return json($this->com_return(false, '登陆失效' , null, 403))->send();
            }

            $statue = $is_exist['statue'];
            if ($statue != config("salesman.salesman_status")['working']['key']) {
                return json($this->com_return(false,config("login.status_no_login"),null,config("code.LOGIN_ERROR")))->send();
            }
        }
    }

    /**
     * 根据token获取服务人员信息
     * @param $token
     * @return array|false|PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function tokenGetManageInfo($token)
    {
        $manageSalesmanModel = new ManageSalesman();

        $manage_column = $manageSalesmanModel->manage_column;

        $manageInfo = $manageSalesmanModel
            ->alias("ms")
            ->join("manage_department md","md.department_id = ms.department_id")
            ->join("mst_salesman_type st","st.stype_id = ms.stype_id")
            ->where('ms.remember_token',$token)
            ->field("md.department_title")
            ->field("st.stype_key,st.stype_name")
            ->field($manage_column)
            ->find();

        $manageInfo = json_decode(json_encode($manageInfo),true);

        return $manageInfo;
    }
}