<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 下午2:23
 */

namespace app\xcx_member\controller\main;


use app\common\controller\BaseController;
use app\common\controller\CardCommon;
use app\common\controller\OrderCommon;
use app\common\controller\UserCommon;
use app\common\controller\VoucherCommon;
use app\common\model\MstCardType;
use app\common\model\MstGift;
use app\common\model\User;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class OpenCard extends BaseController
{
    /**
     * 获取所有有效礼品信息
     * @return array
     */
    public function getGiftListInfo()
    {
        try {
            $giftModel = new MstGift();
            $column    = $giftModel->column;
            $gift_info = $giftModel
                ->where('is_enable',1)
                ->where('is_delete',0)
                ->field($column)
                ->select();

            return $this->com_return(true,config('params.SUCCESS'),$gift_info);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 点击去开卡,获取相应参数,
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $pay_type         = $request->param("pay_type","");//支付方式
        $uid              = $request->param("uid","");//用户id
        $name             = $request->param('name',"");//用户真实姓名
        $card_id          = $request->param("card_id","");//卡id
        $card_pay_amount  = $request->param("card_pay_amount","");//支付金额
        if (empty($pay_type))  $pay_type = config("order.pay_method")['wxpay']['key'];

        $rule = [
            "uid|uid"   => "require",
            "name|姓名"  => "require",
        ];
        $request_res = [
            "uid"    => $uid,
            "name"   => $name,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        Db::startTrans();
        try {
            /*判断用户是否已开卡 On*/
            $userModel = new User();
            $is_open_card = $userModel
                ->where('uid',$uid)
                ->where('user_status',config("user.user_status")[2]['key'])
                ->count();
            if ($is_open_card) return $this->com_return(false,'该用户成功开卡');
            /*判断用户是否已开卡 Off*/

            /*①获取卡信息 On*/
            $cardCommonObj = new CardCommon();
            $card_info     = $cardCommonObj->cardIdGetCardInfo($card_id);

            if ($card_info === false) {
                return $this->com_return(false,'无效卡片');
            }

            $card_amount          = $card_info["card_amount"];//获取卡充值金额
            $cardInfo_pay_amount  = $card_info["card_pay_amount"];//获取卡支付金额
            if ($cardInfo_pay_amount < $card_pay_amount){
                //如果储值金额小于基本储值金额,则返回储值金额无效
                return $this->com_return(true,config("params.RECHARGE_MONEY_INVALID"));
            }
            /*①获取卡信息 Off*/

            /*获取推荐人信息  On*/
            $userCommonObj = new UserCommon();
            $referrer_info = $userCommonObj->getSalesmanId($uid);
            if (empty($referrer_info["referrer_id"]) || $referrer_info == false){
                $referrer_id      = $referrer_info['referrer_id'];
                $referrer_type    = $referrer_info['referrer_type'];
                $commission_ratio = 0;
            }else{
                $referrer_id      = "platform";
                $referrer_type    = "platform";
                $commission_ratio = 0;
            }
            /*获取推荐人信息  Off*/

            //获取自动取消分钟数
            $cardAutoCancelMinutes = $this->getSysSettingInfo("card_auto_cancel_time");
            //将分钟数转换为秒
            $cardAutoCancelTime = $cardAutoCancelMinutes * 60;

            $discount = 0;//折扣金额
            //③生成缴费订单,创建发货订单
            $billCardFeesParams = [
                'vid'             => generateReadableUUID("V"),//充值缴费单 V前缀
                'uid'             => $uid,//用户id
                'referrer_type'   => $referrer_type,//推荐人类型
                'referrer_id'     => $referrer_id,//推荐人id
                'sale_status'     => config("order.open_card_status")['pending_payment']['key'],//单据状态
                'deal_time'       => time(),//成交时间
                'auto_cancel_time'=> time()+$cardAutoCancelTime,//单据自动取消的时间
                'order_amount'    => $cardInfo_pay_amount,//订单金额
                'discount'        => $discount,//折扣,暂且为0
                'payable_amount'  => $cardInfo_pay_amount - $discount,//线上应付且未付金额
                'pay_type'        => $pay_type,
                'is_settlement'   => $referrer_type == "empty" ? 1 : 0 ,//是否结算佣金
                'commission_ratio'=> $commission_ratio,//下单时的佣金比例   百分比整数     没有推荐人的自动为0
                'commission'      => ($cardInfo_pay_amount - $discount) * $commission_ratio / 100,
                'send_type'       => config("order.send_type")['express']['key'],//赠品发货类型
                'created_at'      => time(),//创建时间
                'updated_at'      => time(),//更新时间
            ];

            //返回订单id
            $orderCommonObj = new OrderCommon();
            $billCardFeesReturn = $orderCommonObj->insertBillCardFees($billCardFeesParams);
            if ($billCardFeesReturn == false){
                return $this->com_return(false,'开卡失败');
            }

            /*将信息写入 bill_card_fees_detail表中 On*/
            $billCardFeesDetailParams = [
                'vid'                => $billCardFeesReturn,
                'card_id'            => $card_id,
                'card_type_id'       => $card_info["card_type_id"],//卡片类型   ‘vip’会籍卡      ‘value’ 储值卡
                'card_name'          => $card_info["card_name"],//VIP卡名称
                'card_level'         => $card_info["card_level"],//vip卡级别名称
                'card_image'         => $card_info["card_image"],//VIP卡背景图
                'card_no_prefix'     => $card_info["card_no_prefix"],//卡号前缀（两位数字）
                'card_validity_time' => $card_info["card_validity_time"],//卡的有效期
                'card_desc'          => $card_info["card_desc"],//VIP卡使用说明及其他描述
                'card_equities'      => $card_info["card_equities"],//卡片享受权益详情
                'card_deposit'       => $card_info["card_deposit"],//卡片权益保证金额

                'card_amount'         => $card_amount,//充值金额
                'card_pay_amount'     => $cardInfo_pay_amount,//支付金额
                'card_point'          => $card_info["card_point"],//开卡赠送积分
                'card_cash_gift'      => intval(($card_info["card_cash_gift"] / 100) * $card_amount),//开卡赠送礼金数
            ];

            $billCardFeesDetailReturn = $orderCommonObj->billCardFeesDetail($billCardFeesDetailParams);
            if ($billCardFeesDetailReturn === false){
                return $this->com_return(false,'开卡失败');
            }
            /*将信息写入 bill_card_fees_detail表中 Off*/

            //更改用户user_status为 1 提交订单状态
            $updateUserInfoParams = [
                'user_status' => config('user.user_status')['1']['key'],
                'name'        => $name
            ];
            $updateUserInfoReturn = $userCommonObj->updateUserInfo($updateUserInfoParams,$uid);

            if ($updateUserInfoReturn === false){
                return $this->com_return(false,'开卡失败');
            }

            $cardCommonObj = new CardCommon();
            $referrer_info = $cardCommonObj->getUserCardInfo($uid,'0');
            Db::commit();
            return $this->com_return(true,'请支付',$referrer_info);
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}