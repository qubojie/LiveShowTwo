<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/27
 * Time: 下午3:36
 */

namespace app\common\controller;


use app\common\model\BillPay;
use app\common\model\BillPayDetail;
use app\common\model\BillSubscription;
use app\common\model\TableBusiness;
use app\common\model\TableRevenue;
use app\xcx_member\controller\main\WechatPay;
use think\Exception;
use think\Validate;

class PointListCommon extends BaseController
{
    /**
     * 桌子id获取已开台信息
     * @param $table_id
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function tableIdGetTableBusinessInfo($table_id)
    {
        $tableBusinessModel = new TableBusiness();
        $column = $tableBusinessModel->column;
        foreach ($column as $key => $val) {
            $column[$key] = "tb.".$val;
        }
        $res = $tableBusinessModel
            ->alias("tb")
            ->join('user u','u.uid = tb.uid','LEFT')
            ->where('tb.status',config('order.table_business_status')['open']['key'])
            ->where('tb.table_id',$table_id)
            ->field($column)
            ->field('u.name,u.phone')
            ->select();
        $res = json_decode(json_encode($res),true);
        return $res;
    }

    /**
     * 点单公共部分
     * @param $buid
     * @param $sid
     * @param $sales_name
     * @param $order_amount
     * @param $dish_group
     * @param $pay_type
     * @param $type
     * @param $uid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pointListPublicAction($buid,$sid,$sales_name,$order_amount,$dish_group,$pay_type,$type = 0,$uid = NULL)
    {
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        $tableBusinessModel = new TableBusiness();
        $column = $tableBusinessModel->column;
        $tableBusinessInfo  = $tableBusinessModel
            ->where("buid",$buid)
            ->field($column)
            ->find();
        $tableRevenueInfo = json_decode(json_encode($tableBusinessInfo),true);
        if (empty($tableRevenueInfo)){
            return $this->com_return(false,config("params.ORDER")['NOW_STATUS_NOT_PAY']);
        }

        $turnover_num   = $tableRevenueInfo['turnover_num'];//订单数量
        $turnover_limit = $tableRevenueInfo['turnover_limit'];//低消,0无
        /*首单判断低消是否足够 on*/
        if ($pay_type != \config("order.pay_method")['offline']['key']){
            //如果不是线下支付,则需要验证低消
            if ($turnover_num <= 0){
                //首单需要过低消
                if ($turnover_limit > 0){
                    //有低消
                    if ($order_amount < $turnover_limit){
                        //低消不足
                        return $this->com_return(false,config("params.ORDER")['TURNOVER_LIMIT_SHORT']);
                    }
                }
            }
        }
        /*首单判断低消是否足够 off*/

        if ($tableBusinessInfo['status'] != config("order.table_business_status")['open']['key']){
            //如果不是开台状态,是不能点单
            return $this->com_return(false,config("params.ORDER")['NOW_STATUS_ERROR']."(HOME-DD002)");
        }
        /*创建消费单缴费单 On*/
        $pay_offline_type = "";
        $pid = $this->createBillPay("$buid","$uid","$sid","$sales_name","$type","$order_amount","$order_amount","$pay_type","$pay_offline_type");
        if ($pid == false){
            return $this->com_return(false,\config("params.ABNORMAL_ACTION")."(HOME-DD003)");
        }
        /*创建消费单缴费单 Off*/

        /*创建菜品订单付款详情 On*/
        $createBillPayReturn = $this->createBillPayDetailAction($dish_group,$pid,$buid);
        if (isset($createBillPayReturn['result']) && $createBillPayReturn['result']){
            $orderInfo = $this->pidGetOrderInfo($pid);
            return $this->com_return(true,\config("params.SUCCESS"),$orderInfo);
        }else{
            return $createBillPayReturn;
        }
        /*创建菜品订单付款详情 Off*/
    }

    /**
     * 取消未支付点单公共部分
     * @param $pid
     * @param $acton_user
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cancelPointListPublicAction($acton_user,$pid)
    {
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        if (empty($pid)){
            return $this->com_return(false, config("params.ABNORMAL_ACTION")."QXDD001");
        }
        $billPayModel = new BillPay();
        $orderInfo = $billPayModel
            ->where("pid",$pid)
            ->find();
        $orderInfo = json_decode(json_encode($orderInfo),true);
        if (empty($orderInfo)){
            return $this->com_return(false,config("params.ORDER")['ORDER_ABNORMAL']."(HOME-QXDD002)");
        }
        $sale_status = $orderInfo['sale_status'];
        if ($sale_status == config("order.bill_pay_sale_status")['completed']['key']){
            //订单已支付不可取消
            return $this->com_return(false,config("params.ORDER")['STATUS_NO_CANCEL']."(HOME-QXDD003)");
        }
        //将订单改为交易取消状态
        $params= [
            "sale_status"   => config("order.bill_pay_sale_status")['cancel']['key'],
            "cancel_user"   => "$acton_user",
            "cancel_time"   => time(),
            "auto_cancel"   => 0,
            "cancel_reason" => "未支付,".$acton_user."手动取消",
            "updated_at"    => time()
        ];
        $is_ok = $billPayModel
            ->where("pid",$pid)
            ->update($params);
        if ($is_ok !== false){
            return $this->com_return(true,config("params.SUCCESS"));
        }else{
            return $this->com_return(false,config("params.FAIL")."(HOME-QXDD004)");
        }
    }


    /**
     * 创建消费单缴费单
     * @param $buid
     * @param $uid
     * @param $sid
     * @param $sales_name
     * @param $type
     * @param $order_amount
     * @param $payable_amount
     * @param $pay_type
     * @param $pay_offline_type
     * @return bool|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function createBillPay($buid,$uid,$sid,$sales_name,$type,$order_amount,$payable_amount,$pay_type,$pay_offline_type)
    {
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        $pid   = generateReadableUUID("P");

        if ($type == \config("order.bill_pay_type")['give']['key']){
            //如果是赠品 -> 待审核
            $sale_status  = \config("order.bill_pay_sale_status")['wait_audit']['key'];
        }else{
            $sale_status  = \config("order.bill_pay_sale_status")['pending_payment_return']['key'];
        }

        if ($pay_type == \config("order.pay_method")['offline']['key']){
            $sale_status = \config("order.bill_pay_sale_status")['wait_audit']['key'];
        }

        $nowTime      = time();
        $deal_time    = $nowTime;
        $card_consume_point_ratio = getSysSetting("card_consume_point_ratio") / 100;
        $return_point = intval($order_amount * $card_consume_point_ratio);
        $params = [
            "pid"               => $pid,
            "trid"              => $trid,
            "uid"               => $uid,
            "sid"               => $sid,
            "sname"             => $sales_name,
            "type"              => $type,
            "sale_status"       => $sale_status,
            "deal_time"         => $deal_time,
            "order_amount"      => $order_amount,
            "payable_amount"    => $payable_amount,
            "return_point"      => $return_point,
            "pay_type"          => $pay_type,
            "pay_offline_type"  => $pay_offline_type,
            "created_at"        => $nowTime,
            "updated_at"        => $nowTime
        ];
        $billPayModel = new BillPay();
        $is_ok = $billPayModel
            ->insert($params);
        if ($is_ok !== false){
            return $pid;
        }else{
            return false;
        }
    }

    /**
     * 创建菜品订单详情
     * @param $dish_group
     * @param $pid
     * @param $trid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function createBillPayDetailAction($dish_group,$pid,$trid)
    {
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        //TODO '点单接口完善完善待完善'
        /*创建菜品订单付款详情 On*/
        $dish_group = json_decode($dish_group,true);

        $billPayDetailModel = new BillPayDetail();
        for ($i = 0; $i < count($dish_group); $i ++){
            $dis_id   = $dish_group[$i]['dis_id'];

            $dis_type = $dish_group[$i]['dis_type'];

            $price    = $dish_group[$i]['price'];

            $quantity = $dish_group[$i]['quantity'];

            $dishesCommonObj = new DishesCommon();
            $dishInfo = $dishesCommonObj->disIdGetDisInfo($dis_id);
            if (empty($dishInfo)){
                return $this->com_return(false,\config("params.ABNORMAL_ACTION")."(HOME-DD004)");
            }

            $dis_name  = $dishInfo['dis_name'];
            $dis_sn    = $dishInfo['dis_sn'];
            $dis_desc  = $dishInfo['dis_desc'];
            $is_give   = $dishInfo['is_give'];
            $parent_id = 0;

            $z_params = [
                "parent_id" => $parent_id,
                "pid"       => $pid,
                "trid"      => $trid,
                "is_give"   => $is_give,
                "dis_id"    => $dis_id,
                "dis_type"  => $dis_type,
                "dis_sn"    => $dis_sn,
                "dis_name"  => $dis_name,
                "dis_desc"  => $dis_desc,
                "quantity"  => $quantity,
                "price"     => $price
            ];

            //创建主信息
            $billPayDetailReturn = $billPayDetailModel
                ->insertGetId($z_params);
            if ($billPayDetailReturn == false){
                return $this->com_return(false,\config("params.ABNORMAL_ACTION")."(HOME-DD005)");
            }

            if ($dis_type){
                //如果是套餐
                $dishes_combo = $dish_group[$i]['dishes_combo'];
                if (empty($dishes_combo)){
                    return $this->com_return(false,\config("params.DISHES")['COMBO_DIST_EMPTY']."(HOME-DD006)");
                }

                for ($m = 0; $m <count($dishes_combo); $m ++){
                    $sc_dis_id   = $dishes_combo[$m]['dis_id'];
                    $sc_type     = $dishes_combo[$m]['type'];
                    $sc_quantity = $dishes_combo[$m]['quantity'];
                    $scDisInfo   = $dishesCommonObj->disIdGetDisInfo($sc_dis_id);
                    if (empty($scDisInfo)){
                        return $this->com_return(false,config("params.ABNORMAL_ACTION")."(HOME-DD006)");
                    }
                    $sc_dis_name = $scDisInfo['dis_name'];
                    $sc_dis_sn   = $scDisInfo['dis_sn'];
                    $sc_is_give  = $scDisInfo['is_give'];
                    $sc_dis_desc = $scDisInfo['dis_desc'];

                    $cz_params = [
                        "parent_id" => $billPayDetailReturn,
                        "pid"       => $pid,
                        "trid"      => $trid,
                        "is_give"   => $sc_is_give,
                        "dis_id"    => $sc_dis_id,
                        "dis_type"  => $sc_type,
                        "dis_sn"    => $sc_dis_sn,
                        "dis_name"  => $sc_dis_name,
                        "dis_desc"  => $sc_dis_desc,
                        "quantity"  => $sc_quantity,
                        "price"     => 0
                    ];

                    $czBillPayDetailReturn = $billPayDetailModel
                        ->insertGetId($cz_params);
                    if ($czBillPayDetailReturn === false){
                        return $this->com_return(false,\config("params.ABNORMAL_ACTION")."(HOME-DD007)");
                    }

                    if ($sc_type){
                        //如果是套餐内换品组
                        $children = $dishes_combo[$m]['children'];
                        if (empty($children)){
                            return $this->com_return(false,\config("params.DISHES")['COMBO_ID_NOT_EMPTY']."(HOME-DD008)");
                        }
                        for ($n = 0; $n <count($children); $n ++){
                            $children_dis_id       = $children[$n]['dis_id'];
                            $children_quantity     = $children[$n]['quantity'];
                            $childrenDishInfo      = $dishesCommonObj->disIdGetDisInfo($children_dis_id);
                            if (empty($childrenDishInfo)){
                                return $this->com_return(false,\config("params.ABNORMAL_ACTION")."(HOME-DD009)");
                            }
                            $children_is_give  = $childrenDishInfo['is_give'];
                            $children_dis_sn   = $childrenDishInfo['dis_sn'];
                            $children_dis_name = $childrenDishInfo['dis_name'];
                            $children_dis_desc = $childrenDishInfo['dis_desc'];
                            $little_params = [
                                "parent_id" => $billPayDetailReturn,
                                "pid"       => $pid,
                                "trid"      => $trid,
                                "is_give"   => $children_is_give,
                                "dis_id"    => $children_dis_id,
                                "dis_sn"    => $children_dis_sn,
                                "dis_name"  => $children_dis_name,
                                "dis_desc"  => $children_dis_desc,
                                "quantity"  => $children_quantity,
                                "price"     => 0
                            ];

                            $lBillPayDetailReturn = $billPayDetailModel
                                ->insertGetId($little_params);

                            if ($lBillPayDetailReturn == false){
                                return $this->com_return(false,\config("params.ABNORMAL_ACTION")."(HOME-DD010)");
                            }
                        }
                    }
                }
            }
        }
        /*创建菜品订单付款详情 Off*/
        return $this->com_return(true,config("params.SUCCESS"));
    }


    /**
     * 根据订单id获取点单信息
     * @param $pid
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pidGetOrderInfo($pid)
    {
        $billPayModel = new BillPay();
        $list = $billPayModel
            ->where("pid",$pid)
            ->field("pid,type,sale_status,deal_time,return_point,order_amount")
            ->find();
        $list = json_decode(json_encode($list),true);
        $billPayDetailModel = new BillPayDetail();
        $bpd_column = [
            "bpd.id",
            "bpd.parent_id",
            "bpd.pid",
            "bpd.trid",
            "bpd.is_refund",
            "bpd.is_give",
            "bpd.dis_id",
            "bpd.dis_type",
            "bpd.dis_sn",
            "bpd.dis_name",
            "bpd.dis_desc",
            "bpd.quantity",
            "bpd.price",
            "bpd.amount"
        ];
        $info = $billPayDetailModel
            ->alias("bpd")
            ->join("dishes d","d.dis_id = bpd.dis_id")
            ->where("bpd.pid",$pid)
            ->field("d.dis_img")
            ->field($bpd_column)
            ->select();
        $info = json_decode(json_encode($info),true);
        $info = make_tree($info,"id","parent_id");
        $list['dish_info'] = $info;
        return $list;

    }


    /**
     * 钱包支付公共部分
     * @param $pid
     * @param $userInfo
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function walletPayPublic($pid,$userInfo)
    {
        $billPayModel = new BillPay();
        $orderInfo = $billPayModel
            ->where("pid", $pid)
            ->find();
        $orderInfo = json_decode(json_encode($orderInfo), true);
        if (empty($orderInfo)) {
            return $this->com_return(false, config("params.ORDER")['ORDER_ABNORMAL']);
        }
        $sale_status = $orderInfo['sale_status'];
        if ($sale_status == config("order.bill_pay_sale_status")['completed']['key']) {
            //订单已支付
            return $this->com_return(false, config("params.ORDER")['completed']);
        }
        if ($sale_status != config("order.bill_pay_sale_status")['pending_payment_return']['key']){
            return $this->com_return(false, config("params.ORDER")['NOW_STATUS_NOT_PAY']);
        }
        $uid             = $userInfo['uid'];
        $account_balance = $userInfo['account_balance'];//用户钱包可用余额
        $trid            = $orderInfo['trid'];
        $order_amount    = $orderInfo['order_amount'];//订单总金额
        $payable_amount  = $orderInfo['payable_amount'];//应付且未付金额

        /*用户余额付款操作 on*/
        $reduce_after_balance = $account_balance - $payable_amount;
        if ($reduce_after_balance < 0) {
            return $this->com_return(false, config("params.ORDER")['BALANCE_NOT_ENOUGH']);
        }
        //更改用户余额数据(先把余额扣除后,再去做回调)
        $userBalanceParams = [
            "account_balance" => $reduce_after_balance,
            "updated_at"      => time()
        ];

        $userCommonObj = new UserCommon();
        //更新用户余额数据
        $updateUserBalanceReturn = $userCommonObj->updateUserInfo($userBalanceParams,$uid);
        if ($updateUserBalanceReturn == false) {
            return $this->com_return(false, config("params.ABNORMAL_ACTION") . "PB001");
        }
        //插入用户余额消费明细
        //余额明细参数
        $insertUserAccountParams = [
            "uid"          => $uid,
            "balance"      => "-" . $payable_amount,
            "last_balance" => $reduce_after_balance,
            "change_type"  => '2',
            "action_user"  => 'sys',
            "action_type"  => config('user.account')['consume']['key'],
            "oid"          => $pid,
            "deal_amount"  => $payable_amount,
            "action_desc"  => config('user.account')['consume']['name'],
            "created_at"   => time(),
            "updated_at"   => time()
        ];

        //插入用户余额消费明细
        $insertUserAccountReturn = $userCommonObj->updateUserAccount($insertUserAccountParams);
        if ($insertUserAccountReturn == false) {
            return $this->com_return(false, config("params.ABNORMAL_ACTION") . "PB002");
        }
        /*用户余额付款操作 off*/

        //订单支付回调
        $payCallBackParams = [
            "out_trade_no"   => $pid,
            "cash_fee"       => $payable_amount * 100,
            "total_fee"      => $order_amount * 100,
            "transaction_id" => "",
            "pay_type"       => config("order.pay_method")['balance']['key']
        ];

        $notifyCommonObj = new NotifyCommon();
        $payCallBackReturn = $notifyCommonObj->pointListNotify($payCallBackParams,"");
        $payCallBackReturn = json_decode(json_encode(simplexml_load_string($payCallBackReturn, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($payCallBackReturn["return_code"] != "SUCCESS" || $payCallBackReturn["return_msg"] != "OK"){
            //回调失败
            return $this->com_return(false,$payCallBackReturn["return_msg"]);
        }
        return $this->com_return(true,config("params.SUCCESS"));
    }

    /**
     * 台位预定及营收状况信息表更新
     * @param array $params
     * @param $trid
     * @param $uid
     * @return bool
     */
    public function changeTableRevenueInfo($params = array(),$trid,$uid)
    {
        $tableRevenueModel = new TableRevenue();

        $is_ok = $tableRevenueModel
            ->where("trid",$trid)
//            ->where("uid",$uid)
            ->update($params);
        if ($is_ok !== false) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 台位预定定金缴费单更新
     * @param array $params
     * @param $suid
     * @return bool
     */
    public function changeBillSubscriptionInfo($params = array(),$suid)
    {
        $billSubscriptionModel = new BillSubscription();

        $is_ok = $billSubscriptionModel
            ->where('suid',$suid)
            ->update($params);

        if ($is_ok !== false) {
            return true;
        }else{
            return false;
        }

    }

    /**
     * 扫码获取桌台开台信息列表
     * @param $table_id
     * @param string $userInfo
     * @return array
     */
    public function qrCodeGetUserId($table_id,$userInfo = "")
    {
        $rule = [
            "table_id|桌号" => "require",
        ];
        $check_res = [
            "table_id" => $table_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $pointListCommonObj = new PointListCommon();
            /*table_id获取开台信息 On*/
            $tableBusinessInfo = $pointListCommonObj->tableIdGetTableBusinessInfo($table_id);
            if (empty($tableBusinessInfo)) {
                //如果桌子没有开台,则返回不可点单提示
                return $this->com_return(false,config("params.REVENUE")['NOT_OPEN_NOT_DISH']);
            }
            /*table_id获取开台信息 Off*/

            if (!empty($userInfo)) {
                if ($userInfo === false) {
                    return $this->com_return(false,config("params.FAIL"));
                }
                $uid = $userInfo['uid'];
                $res = $tableBusinessInfo;
                //如果有预约信息,查看是否有当前用户的预约信息
                foreach ($tableBusinessInfo as $key => $val) {
                    if ($tableBusinessInfo[$key]['uid'] != $uid) {
                        unset($tableBusinessInfo[$key]);
                    }
                }
                if (empty($tableBusinessInfo)){
                    $tableBusinessInfo = $res;
                }else{
                    $tableBusinessInfo = array_values($tableBusinessInfo);
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$tableBusinessInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}