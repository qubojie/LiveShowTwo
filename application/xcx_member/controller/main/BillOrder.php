<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 下午2:42
 */

namespace app\xcx_member\controller\main;


use app\common\controller\BaseController;
use app\common\controller\OrderCommon;
use app\common\controller\UserCommon;
use app\common\model\BillCardFees;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class BillOrder extends BaseController
{
    /**
     * 取消订单
     * @param Request $request
     * @return array
     */
    public function cancelOrder(Request $request)
    {
        $uid = $request->param('uid','');
        $vid = $request->param('vid','');
        $rule = [
            "uid|用户id"  => "require",
            "vid|订单id"  => "require",
        ];
        $request_res = [
            "uid" => $uid,
            "vid" => $vid,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        Db::startTrans();
        try {
            $billCardsFeesModel = new BillCardFees();
            $bill_info = $billCardsFeesModel
                ->where('uid',$uid)
                ->where('vid',$vid)
                ->where('sale_status','0')
                ->count();
            if ($bill_info == 0){
                return $this->com_return(false,"订单不存在");
            }
            //更改订单状态
            $params = [
                'sale_status'      => 9,
                'cancel_user'      => 'self',
                'cancel_time'      => time(),
                'auto_cancel'      => 0,
                'auto_cancel_time' => time(),
                'cancel_reason'    => '用户手动取消',
                'updated_at'       => time()
            ];
            $orderCommonObj = new OrderCommon();
            $is_ok = $orderCommonObj->updateOrderStatus($params,"$vid");

            if ($is_ok === false){
                return $this->com_return(false,"取消失败");
            }

            //更改用户状态

            $user_params = [
                'user_status' => 0,
                'updated_at'  => time()
            ];

            $userCommonObj = new UserCommon();
            $is_true = $userCommonObj->updateUserInfo($user_params,"$uid");

            if ($is_true === false) {
                return $this->com_return(false, "取消失败");
            }
            Db::commit();
            return $this->com_return(true,"取消成功");
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}