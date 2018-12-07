<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 上午11:11
 */
namespace app\reception\controller\personal;



use app\common\controller\ReceptionAuthAction;
use app\common\model\ManageSalesman;
use think\Exception;
use think\Request;
use think\Validate;

class ManageInfo extends ReceptionAuthAction
{
    /**
     * 变更密码
     * @param Request $request
     * @return array
     */
    public function changePass(Request $request)
    {
        $old_password = $request->param("old_password","");
        $password     = $request->param("password","");

        $rule = [
            "old_password|旧密码" => "require|alphaNum|length:6,16",
            "password|新密码"     => "require|alphaNum|length:6,16",
        ];
        $request_res = [
            "old_password" => $old_password,
            "password"     => $password,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        try {
            /*权限判断 on*/
            $reception_token = $request->header("Token","");

            $manageInfo = $this->receptionTokenGetManageInfo($reception_token);
            $statue     = $manageInfo['statue'];
            if ($statue != \config("salesman.salesman_status")['working']['key']){
                return $this->com_return(false,\config("params.MANAGE_INFO")['UsrLMT']);
            }
            /*权限判断 off*/

            $old_password = jmPassword($old_password);

            $manageModel = new ManageSalesman();
            $is_true = $manageModel
                ->where("reception_token",$reception_token)
                ->where('password',$old_password)
                ->count();
            if(!$is_true){
                return $this->com_return(false,config("params.PASSWORD_PP"));
            }

            $new_token = jmToken($password.time().$old_password);
            $reception_token_lastime = time() + 24 * 60 * 60;
            $params = [
                "password"                => jmPassword($password),
                "reception_token"         => $new_token,
                "reception_token_lastime" => $reception_token_lastime,
                "updated_at"              => time()
            ];

            $is_ok = $manageModel
                ->where('reception_token',$reception_token)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"),$new_token);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}