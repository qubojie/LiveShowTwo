<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/27
 * Time: 下午3:31
 */

namespace app\xcx_member\controller\orders;


use app\common\controller\MemberAuthAction;
use app\common\controller\PointListCommon;
use app\common\model\MstPayMethod;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class PointList extends MemberAuthAction
{
    /**
     * 获取扫码点单对应的开台订单信息
     * @param Request $request
     * @return array
     */
    public function getTableRevenueInfo(Request $request)
    {
        $table_id = $request->param("table_id","");//桌id
        $token    = $request->header("Token");
        $userInfo = $this->tokenGetUserInfo($token);
        $pointListCommonObj = new PointListCommon();
        return $pointListCommonObj->qrCodeGetUserId($table_id,$userInfo);
    }

    /**
     * 用户点单
     * @param Request $request
     * @return array
     */
    public function createPointList(Request $request)
    {
        $buid         = $request->param("buid","");//开台id
        $order_amount = $request->param('order_amount','');//订单总额
        $dish_group   = $request->param("dish_group",'');//菜品集合
        $pay_type     = $request->param("pay_type",'');//支付方式

        $rule = [
            "buid|开台id"          => "require",
            "order_amount|订单总额" => "require",
            "dish_group|菜品集合"   => "require",
        ];
        $check_data = [
            "buid"           => $buid,
            "order_amount"   => $order_amount,
            "dish_group"     => $dish_group
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }
        Db::startTrans();
        try {
            /*获取当前用户信息 On*/
            $remember_token = $request->header("Token",'');
            $userInfo = $this->tokenGetUserInfo($remember_token);
            $uid = $userInfo['uid'];
            /*获取当前用户信息 Off*/

            $type = config("order.bill_pay_type")['consumption']['key'];
            $sid = NULL;
            $sales_name = "";

            $pointListCommonObj = new PointListCommon();

            $pointListReturn = $pointListCommonObj->pointListPublicAction("$buid","$sid","$sales_name","$order_amount","$dish_group","$pay_type","$type",$uid);
            if (isset($pointListReturn['result']) && $pointListReturn['result']){
                Db::commit();
                return $this->com_return(true,\config("params.SUCCESS"),$pointListReturn['data']);
            }else{
                return $pointListReturn;
            }
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 用户手动取消未支付订单
     * @param Request $request
     * @return array
     */
    public function cancelDishOrder(Request $request)
    {
        $pid = $request->param("pid","");//订单id
        $action_user = "user";
        try {
            $pointListCommonObj = new PointListCommon();
            $cancelReturn =  $pointListCommonObj->cancelPointListPublicAction($action_user,$pid);
            if (isset($cancelReturn['result']) && $cancelReturn['result']){
                Db::commit();
                return $this->com_return(true,\config("params.SUCCESS"),$cancelReturn['data']);
            }else{
                return $cancelReturn;
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取支付方式
     * @return array
     */
    public function getPayMethod()
    {
        try {
            $payMethodModel = new MstPayMethod();
            $res = $payMethodModel
                ->where("is_delete",0)
                ->where("user_is_enable",1)
                ->order("sort,updated_at DESC")
                ->field("pay_method_id,pay_method_key,pay_method_name,logo")
                ->select();
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

}