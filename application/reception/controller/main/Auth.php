<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 上午11:18
 */
namespace app\reception\controller\main;

use app\common\controller\BaseController;
use app\common\model\ManageSalesman;
use think\Exception;
use think\Request;

class Auth extends BaseController
{
    /**
     * 登陆
     * @param Request $request
     * @return array
     */
    public function login(Request $request)
    {
        $phone    = $request->param("phone","");

        $password = $request->param("password","");

        if (empty($phone) || empty($password)){
            return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
        }

        try {
            $password = jmPassword($password);
            $manageSalesmanModel = new ManageSalesman();
            $manage_column = $manageSalesmanModel->manage_column;

            $reserve = config("salesman.salesman_type")[6]['key'];
            $cashier = config("salesman.salesman_type")[7]['key'];
            $stype_key_str = "$reserve,$cashier";
            $manageInfo = $manageSalesmanModel
                ->alias("ms")
                ->join("mst_salesman_type mst","mst.stype_id = ms.stype_id")
                ->where('ms.phone',$phone)
                ->where('ms.password',$password)
                ->where('mst.stype_key','IN',$stype_key_str)
                ->field("mst.stype_key")
                ->field($manage_column)
                ->find();
            $manageInfo = json_decode(json_encode($manageInfo),true);

            if (empty($manageInfo)){
                return $this->com_return(false,config("params.ACCOUNT_PASSWORD_DIF"));
            }

            $stype_key = $manageInfo['stype_key'];
            if ($stype_key != $reserve && $stype_key != $cashier){
                return $this->com_return(false,config("params.PERMISSION_NOT_ENOUGH"));
            }

            $quitStatue = config("salesman.salesman_status")['resignation']['key'];

            $statue     = $manageInfo['statue'];
            if ($statue == $quitStatue){
                return $this->com_return(false, config("params.QUIT_NO_LOGIN"));
            }

            $reception_token = jmToken($password.time().generateReadableUUID("QBJ"));
            $time = time();
            $update_params = [
                "reception_token"           => $reception_token,
                "reception_token_lastime"   => $time,
                "updated_at"                => $time
            ];

            $is_ok = $manageSalesmanModel
                ->where('phone',$phone)
                ->where('password',$password)
                ->update($update_params);

            if ($is_ok !== false){
                $manageInfo = $manageSalesmanModel
                    ->alias("ms")
                    ->join("manage_department md","md.department_id = ms.department_id")
                    ->join("mst_salesman_type st","st.stype_id = ms.stype_id")
                    ->where('ms.phone',$phone)
                    ->where('ms.password',$password)
                    ->field("md.department_title")
                    ->field("st.stype_key,st.stype_name")
                    ->field($manage_column)
                    ->find();
                return $this->com_return(true,config("params.SUCCESS"),$manageInfo);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}