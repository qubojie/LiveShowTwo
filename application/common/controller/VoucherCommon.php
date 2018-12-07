<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 上午11:49
 */

namespace app\common\controller;


use app\common\model\MstGift;
use app\common\model\MstGiftVoucher;
use app\common\model\UserCard;
use app\common\model\UserGiftVoucher;
use app\services\LuoSiMaoSms;
use think\Controller;
use think\Db;
use think\Exception;

class VoucherCommon extends Controller
{
    /**
     * 礼券发放
     * @param $user_phone
     * @param $gift_vou_id
     * @param $review_user
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function appointUser($user_phone,$gift_vou_id,$review_user)
    {
        $is_valid = self::checkVoucherValid($gift_vou_id);

        if (!$is_valid)  return false;

        $voucherInfo = self::getVoucherInfo($gift_vou_id);

        if (empty($voucherInfo))  return false;

        $userCommonObj = new UserCommon();

        $userInfo = $userCommonObj->uidOrPhoneGetUserInfo("$user_phone");

        if (empty($userInfo)) return false;

        $uid           = $userInfo['uid'];
        $gift_vou_code = uniqueCode(8); //礼品券兑换码

        if ($voucherInfo['gift_vou_type'] == config("voucher.type")['0']['key']){
            //单次 once
            $use_qty = 1;
        }else{
            //limitless 不限制
            $use_qty = 0;
        }

        $gift_validity_type = $voucherInfo['gift_validity_type'];

        $params = [
            "gift_vou_code"           => $gift_vou_code,
            "uid"                     => $uid,
            "gift_vou_id"             => $gift_vou_id,
            "gift_vou_type"           => $voucherInfo['gift_vou_type'],
            "gift_vou_name"           => $voucherInfo['gift_vou_name'],
            "gift_vou_desc"           => $voucherInfo['gift_vou_desc'],
            "gift_vou_amount"         => $voucherInfo['gift_vou_amount'],
            "gift_vou_validity_start" => $voucherInfo['gift_start_day'],
            "gift_vou_validity_end"   => $voucherInfo['gift_end_day'],
            "gift_vou_exchange"       => $voucherInfo['gift_vou_exchange'],
            "use_qty"                 => $use_qty,
            "qty_max"                 => $voucherInfo['qty_max'],
            "status"                  => config("voucher.status")['0']['key'],
            "use_time"                => 0,
            "review_user"             => $review_user,
            "created_at"              => time(),
            "updated_at"              => time()
        ];

        $userVoucherModel = new UserGiftVoucher();

        $res = $userVoucherModel
            ->insert($params);

        if ($res !== false) {
            $message = config('sms.voucher_send').config('sms.sign');
            $sms = new LuoSiMaoSms();
            $sms->send($user_phone,$message);

            return true;
        }else{
            return false;
        }
    }

    /**
     * 指定会员卡
     * @param $card_id
     * @param $gift_vou_id
     * @param $review_user
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function appointCard($card_id,$gift_vou_id,$review_user)
    {
        $is_valid = self::checkVoucherValid($gift_vou_id);
        if (!$is_valid)  return false;

        $voucherInfo  =self::getVoucherInfo("$gift_vou_id");
        if (empty($voucherInfo))  return false;

        $cardCommonObj = new CardCommon();
        $uidInfo = $cardCommonObj->getCardUserList($card_id);

        Db::startTrans();
        try {
            $userVoucherModel = new UserGiftVoucher();
            $phone = "";
            for ($i = 0; $i < count($uidInfo); $i ++){
                $uid           = $uidInfo[$i]['uid'];
                $gift_vou_code = uniqueCode(8); //礼品券兑换码

                if ($voucherInfo['gift_vou_type'] == config("voucher.type")['0']['key']){
                    //单次 once
                    $use_qty = 1;
                }else{
                    //limitless 不限制
                    $use_qty = 0;
                }

                $params = [
                    "gift_vou_code"           => $gift_vou_code,
                    "uid"                     => $uid,
                    "gift_vou_id"             => $gift_vou_id,
                    "gift_vou_type"           => $voucherInfo['gift_vou_type'],
                    "gift_vou_name"           => $voucherInfo['gift_vou_name'],
                    "gift_vou_desc"           => $voucherInfo['gift_vou_desc'],
                    "gift_vou_amount"         => $voucherInfo['gift_vou_amount'],
                    "gift_vou_validity_start" => $voucherInfo['gift_start_day'],
                    "gift_vou_validity_end"   => $voucherInfo['gift_end_day'],
                    "gift_vou_exchange"       => $voucherInfo['gift_vou_exchange'],
                    "use_qty"                 => $use_qty,
                    "qty_max"                 => $voucherInfo['qty_max'],
                    "status"                  => config("voucher.status")['0']['key'],
                    "use_time"                => 0,
                    "review_user"             => $review_user,
                    "created_at"              => time(),
                    "updated_at"              => time()
                ];

                $is_ok = $userVoucherModel
                    ->insert($params);

                if (!$is_ok){
                    return false;
                }

                if (!empty($uidInfo[$i]['phone'])){
                    $phone .= $uidInfo[$i]['phone'].",";
                }
            }

            $phone = substr($phone,0,strlen($phone)-1);
            $message = config('sms.voucher_send').config('sms.sign');
            $sms = new LuoSiMaoSms();
            $sms->send_batch($phone,$message);

            Db::commit();
            return true;

        } catch (Exception $e) {
            Db::rollback();
            return false;
        }
    }


    /**
     * 发券检测礼券有效性
     * @param $gift_vou_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function checkVoucherValid($gift_vou_id)
    {
        $info = self::getVoucherInfo($gift_vou_id);

        if (empty($info)) return false;

        $gift_validity_type    = $info['gift_validity_type']; //有效类型   0无限期   1按天数生效   2按指定有效日期
        $gift_vou_validity_day = $info['gift_vou_validity_day'];//礼券有效时间（天） 0表示无限期
        $gift_start_day        = $info['gift_start_day'];//有效开始日期   开始日期 0 或者空 表示 发劵日期开始      否则  指定开始日期
        $gift_end_day          = $info['gift_end_day'];//有效结束日期
        $gift_vou_exchange     = $info['gift_vou_exchange'];//使用时段信息规则（保存序列）

        if ($gift_validity_type == 1){
            //按天数生效
            if ($gift_start_day > 0){
                //指定开始日期
                if (time() > $gift_end_day){
                    //如果当前时间大于结束日期
                    return false;
                }
            }

        }elseif ($gift_validity_type == 2){
            //按指定有效日期
            if ($gift_start_day > 0){
                //指定开始日期
                if (time() > $gift_end_day){
                    //如果当前时间大于结束日期
                    return false;
                }
            }
        }else{
            //无限制
            return true;
        }
        return true;
    }


    /**
     * 获取礼券信息
     * @param $gift_vou_id
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getVoucherInfo($gift_vou_id)
    {
        $voucherModel = new MstGiftVoucher();

        $info = $voucherModel
            ->where("gift_vou_id",$gift_vou_id)
            ->where("is_delete",0)
            ->where("is_enable",1)
            ->find();

        $info = json_decode(json_encode($info),true);

        return $info;
    }

    /**
     * 开卡下发赠送的券
     * @param $card_id
     * @param $uid
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function putVoucher($card_id,$uid)
    {
        //获取礼券信息
        $time = time();
        $gift_vou_info = Db::name('mst_card_vip_voucher_relation')
            ->alias('vvr')
            ->join('mst_gift_voucher mgv','mgv.gift_vou_id = vvr.gift_vou_id')
            ->where('vvr.card_id',$card_id)
            ->where('mgv.is_enable','1')
            ->where('mgv.is_delete','0')
            ->field('vvr.gift_vou_type,vvr.qty,vvr.gift_vou_id')
            ->field('mgv.gift_vou_name,mgv.gift_vou_desc,mgv.gift_vou_amount,mgv.gift_validity_type,mgv.gift_vou_validity_day,mgv.gift_start_day,mgv.gift_end_day,mgv.gift_vou_exchange,mgv.qty_max')
            ->select();
        $giftVouReturn = false;
        if (empty($gift_vou_info)){
            $giftVouReturn = true;
        }
        for ($i=0;$i<count($gift_vou_info);$i++){
            $gift_validity_type    = $gift_vou_info[$i]['gift_validity_type'];
            $gift_start_day        = $gift_vou_info[$i]['gift_start_day'];//有效开始时间
            $gift_end_day          = $gift_vou_info[$i]['gift_end_day'];//有效结束时间
            $gift_vou_validity_day = $gift_vou_info[$i]['gift_vou_validity_day'];//有效天数

            if ($gift_validity_type == '1'){
                //如果有效期类型为 按天数生效
                if (empty($gift_start_day) || $gift_start_day == '0'){
                    //这时未设置有效开始时间
                    $gift_vou_validity_start = $time;
                    $gift_vou_validity_end   = $time + $gift_vou_validity_day * 24 * 60 * 60;
                }else{
                    //这里设置了有效开始时间
                    $gift_vou_validity_start = $gift_start_day;
                    $gift_vou_validity_end   = $gift_end_day;
                }
            }elseif ($gift_validity_type == '2'){
                //如果类型为 指定了有效日期段
                $gift_vou_validity_start = $gift_start_day;
                $gift_vou_validity_end   = $gift_end_day;

            }else{
                //如果类型为 0 无限期
                if (empty($gift_start_day) || $gift_start_day == '0'){
                    //这时未设置有效开始时间
                    $gift_vou_validity_start = $time;

                }else{
                    //这里设置了有效开始时间
                    $gift_vou_validity_start = $gift_start_day;
                }
                $gift_vou_validity_end = '0';
            }

            $gift_vou_code = uniqueCode(8); //礼品券兑换码

            $giftVouParams = [
                'gift_vou_code'           => $gift_vou_code,
                'uid'                     => $uid,
                'gift_vou_id'             => $gift_vou_info[$i]['gift_vou_id'],
                'gift_vou_type'           => $gift_vou_info[$i]['gift_vou_type'],
                'gift_vou_name'           => $gift_vou_info[$i]['gift_vou_name'],
                'gift_vou_desc'           => $gift_vou_info[$i]['gift_vou_desc'],
                'gift_vou_amount'         => $gift_vou_info[$i]['gift_vou_amount'],
                'gift_vou_validity_start' => $gift_vou_validity_start,
                'gift_vou_validity_end'   => $gift_vou_validity_end,
                'gift_vou_exchange'       => $gift_vou_info[$i]['gift_vou_exchange'],
                'use_qty'                 => $gift_vou_info[$i]['qty'],
                'qty_max'                 => $gift_vou_info[$i]['qty_max'],
                'use_time'                => 0,
                'review_user'             => 'sys',
                'created_at'              => $time,
                'updated_at'              => $time,
            ];


            $userCommonObj = new UserCommon();
            $giftVouReturn = $userCommonObj->updateUserGiftVoucher($giftVouParams);
        }


        return $giftVouReturn;
    }

    /**
     * uid获取指定状态的礼券
     * @param $uid
     * @param $status
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function uidGetVoucher($uid,$status)
    {
        $userGiftVoucherModel = new UserGiftVoucher();
        $res = $userGiftVoucherModel
            ->where('uid',$uid)
            ->where('status',$status)
            ->select();
        $res = json_decode(json_encode($res),true);
        return $res;
    }

    /**
     * 获取指定礼品信息
     * @param $gift_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGiftInfo($gift_id)
    {
        $giftModel = new MstGift();
        $column = $giftModel->column;
        $gift_info = $giftModel
            ->where('gift_id',$gift_id)
            ->field($column)
            ->find();
        $gift_info = json_decode(json_encode($gift_info),true);
        return $gift_info;
    }
}