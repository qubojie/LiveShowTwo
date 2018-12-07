<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 上午11:10
 */

namespace app\admin\controller\member;


use app\common\controller\AdminAuthAction;
use app\common\controller\CardCommon;
use app\common\controller\MakeQrCode;
use app\common\controller\UserCommon;
use app\common\controller\VoucherCommon;
use app\common\model\MstGiftVoucher;
use app\common\model\UserCard;
use think\Db;
use think\Exception;
use think\Validate;

class Voucher extends AdminAuthAction
{
    /**
     * 礼券列表
     * @return array
     */
    public function index()
    {
        $pagesize   = $this->request->param("pagesize", config('page_size'));//显示个数,不传时为10
        $nowPage    = $this->request->param("nowPage", "1");
        $is_enable  = $this->request->param("is_enable","");//是否只展示激活的卡
        $orderBy    = $this->request->param("orderBy","");
        $sort       = $this->request->param("sort","asc");

        if (empty($orderBy)) $orderBy = "gift_vou_id";
        if (empty($sort)) $sort = "asc";
        if (empty($pagesize)) $pagesize = config('page_size');

        $enable_where = [];
        if ($is_enable == '1'){
            $enable_where['is_enable'] = ['eq','1'];
        }
        if ($is_enable == '0'){
            $enable_where['is_enable'] = ['neq','1'];
        }

        $config = [
            "page" => $nowPage,
        ];

        try {
            $gitVoucherModel = new MstGiftVoucher();

            $gift_voucher_list = $gitVoucherModel
                ->where('is_delete', '0')
                ->where($enable_where)
                ->order($orderBy,$sort)
                ->paginate($pagesize, false, $config);

            $gift_voucher_list = json_decode(json_encode($gift_voucher_list),true);

            $gift_voucher_list['filter']['orderBy'] = $orderBy;
            $gift_voucher_list['filter']['sort']    = $sort;

            $list = $gift_voucher_list['data'];

            for ($i = 0; $i < count($list); $i++){
                $gift_validity_type     = $list[$i]['gift_validity_type'];//类型
                $gift_vou_validity_day  = (int)$list[$i]['gift_vou_validity_day'];//有效天数
                $gift_start_day         = $list[$i]['gift_start_day'];//有效开始时间
                $gift_end_day           = $list[$i]['gift_end_day'];//结束时间

                if ($gift_validity_type == '1'){
                    //如果有效期类型为 按天数生效
                    $gift_rule_info = "{'gift_time':'".$gift_start_day."','gift_validity_day':'".$gift_vou_validity_day."'}";

                }elseif ($gift_validity_type == '2'){
                    //如果类型为 指定了有效日期
                    $gift_rule_info = "{'gift_time':'".$gift_start_day.",".$gift_end_day."','gift_validity_day':'".$gift_vou_validity_day."'}";

                }else{
                    //如果类型为 0 无限期
                    $gift_rule_info = "{'gift_time':'".$gift_start_day."','gift_validity_day':'".$gift_vou_validity_day."'}";

                }

                //将int型的数据转换为string
                $gift_voucher_list['data'][$i] = arrIntToString($gift_voucher_list['data'][$i]);

                $gift_voucher_list['data'][$i]['gift_rule_info'] = $gift_rule_info;
            }

            return $this->com_return(true, config("params.SUCCESS"), $gift_voucher_list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 礼券添加
     * @return array
     */
    public function add()
    {
        $gift_vou_type         = $this->request->param("gift_vou_type", "");         //赠券类型  ‘once’单次    ‘multiple’多次   ‘limitless’ 无限制
        $gift_vou_name         = $this->request->param("gift_vou_name", "");         //礼券名称标题
        $gift_vou_desc         = $this->request->param("gift_vou_desc", "");         //礼券详细描述
        $gift_vou_amount       = $this->request->param("gift_vou_amount", "");       //礼券金额
        $gift_validity_type    = $this->request->param("gift_validity_type", "");    //有效类型   0无限期   1按天数生效   2按指定有效日期
        $gift_rule_info        = $this->request->param('gift_rule_info','');//json规则
        $gift_vou_exchange     = $this->request->param("gift_vou_exchange", "");     //兑换规则 （保存序列）
        $qty_max               = $this->request->param("qty_max", "");     //单日最大使用数量    无限制卡表示单日最大使用数量
        $is_enable             = $this->request->param("is_enable", 0);             //是否启用  0否 1是

        $rule = [
            "gift_vou_type|赠券类型"                  => "require",
            "gift_vou_name|礼券名称标题"               => "require|unique_delete:mst_gift_voucher",
            "gift_vou_amount|礼券金额"                => "require",
            "gift_validity_type|礼券有效类型"          => "require",
            "gift_vou_exchange|兑换规则"              => "require",
            "is_enable|是否启用"                      => "require",
        ];

        $request_res = [
            "gift_vou_type"         => $gift_vou_type,
            "gift_vou_name"         => $gift_vou_name,
            "gift_vou_amount"       => $gift_vou_amount,
            "gift_validity_type"    => $gift_validity_type,
            "gift_vou_exchange"     => $gift_vou_exchange,
            "is_enable"             => $is_enable,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)) {
            return $this->com_return(false, $validate->getError(), null);
        }

        try {
            $gift_rule_info = json_decode($gift_rule_info,true);

            if ($gift_validity_type == '1'){
                //如果有效期类型为 按天数生效
                $gift_time             = $gift_rule_info['gift_time'];
                $gift_validity_day     = $gift_rule_info['gift_validity_day'];

                //给前端将秒时间戳转换为毫秒
                if (strlen($gift_time) > 11){
                    $gift_time = (int)($gift_time * 0.001);
                }

                if (!empty($gift_time)){
                    $gift_start_day        = $gift_time;//有效开始时间
                    $gift_end_day          = $gift_time + $gift_validity_day * 24 * 60 * 60;
                    $gift_vou_validity_day = $gift_validity_day;
                }else{
                    $gift_start_day        = "";
                    $gift_end_day          = "";
                    $gift_vou_validity_day = $gift_validity_day;
                }

            }elseif ($gift_validity_type == '2'){
                //如果类型为 指定了有效日期
                $gift_time             = $gift_rule_info['gift_time'];
                $gift_time_arr         = explode(",",$gift_time);

                if (count($gift_time_arr) < 2){
                    return $this->com_return(false,'时间范围不正确');
                }
                $gift_start_day        = $gift_time_arr[0];
                $gift_end_day          = $gift_time_arr[1];

                if (strlen($gift_start_day) > 11){
                    $gift_start_day = (int)($gift_start_day * 0.001);
                }

                if (strlen($gift_end_day) > 11){
                    $gift_end_day = (int)($gift_end_day * 0.001);
                }

                $gift_vou_validity_day = "";


            }else{
                //如果类型为 0 无限期
                $gift_time = $gift_rule_info['gift_time'];

                if (strlen($gift_time) > 11){
                    $gift_time = (int)($gift_time * 0.001);
                }

                if (!empty($gift_time)){
                    //如果设置了有效开始时间
                    $gift_start_day        = $gift_time;//有效开始时间
                    $gift_end_day          = "";
                    $gift_vou_validity_day = 0; //有效时间无限期

                }else{
                    $gift_start_day = "";
                    $gift_end_day = "";
                    $gift_vou_validity_day = 0;
                }
            }

            $gitVoucherModel = new MstGiftVoucher();

            $insert_data = [
                "gift_vou_type"         => $gift_vou_type,
                "gift_vou_name"         => $gift_vou_name,
                "gift_vou_desc"         => $gift_vou_desc,
                "gift_vou_amount"       => $gift_vou_amount,
                "gift_validity_type"    => $gift_validity_type,
                "gift_vou_validity_day" => $gift_vou_validity_day,
                "gift_start_day"        => $gift_start_day,
                "gift_end_day"          => $gift_end_day,
                "gift_vou_exchange"     => $gift_vou_exchange,
                "qty_max"               => $qty_max,
                "is_enable"             => $is_enable,
                "created_at"            => time(),
                "updated_at"            => time()
            ];

            $res = $gitVoucherModel
                ->insert($insert_data);

            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(true,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 礼券编辑
     * @return array
     */
    public function edit()
    {
        $gift_vou_id            = $this->request->param("gift_vou_id", "");  //礼券id
        $gift_vou_type          = $this->request->param("gift_vou_type", "");  //赠券类型  ‘once’单次    ‘multiple’多次   ‘limitless’ 无限制
        $gift_vou_name          = $this->request->param("gift_vou_name", "");      //礼券名称标题
        $gift_vou_desc          = $this->request->param("gift_vou_desc", "");      //礼券详细描述
        $gift_vou_amount        = $this->request->param("gift_vou_amount", "");    //礼券金额
        $gift_validity_type     = $this->request->param('gift_validity_type',''); //有效类型   0无限期   1按天数生效   2按指定有效日期
        $gift_rule_info         = $this->request->param('gift_rule_info','');//json规则
        $gift_vou_exchange      = $this->request->param("gift_vou_exchange", "");     //兑换规则 （保存序列）
        $qty_max                = $this->request->param("qty_max", "");     //最大使用数量    无限制卡表示单日最大使用数量

        $rule = [
            "gift_vou_id|赠券id"                       => "require",
            "gift_vou_type|赠券类型"                    => "require",
            "gift_vou_name|礼券名称标题"                => "require|unique_delete:mst_gift_voucher,gift_vou_id",
            "gift_vou_amount|礼券金额"                  => "require",
            "gift_vou_exchange|兑换规则"                => "require",
        ];

        $request_res = [
            "gift_vou_id"           => $gift_vou_id,
            "gift_vou_type"         => $gift_vou_type,
            "gift_vou_name"         => $gift_vou_name,
            "gift_vou_amount"       => $gift_vou_amount,
            "gift_vou_exchange"     => $gift_vou_exchange,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)) {
            return $this->com_return(false, $validate->getError(), null);
        }

        try {
            $gift_rule_info = json_decode($gift_rule_info,true);
            if ($gift_validity_type == '1'){
                //如果有效期类型为 按天数生效
                $gift_time             = $gift_rule_info['gift_time'];
                $gift_validity_day     = $gift_rule_info['gift_validity_day'];

                if (strlen($gift_time) > 11){
                    $gift_time = (int)($gift_time * 0.001);
                }

                if (!empty($gift_time)){
                    $gift_start_day        = $gift_time;//有效开始时间
                    $gift_end_day          = $gift_time + $gift_validity_day * 24 * 60 * 60;
                    $gift_vou_validity_day = $gift_validity_day;
                }else{
                    $gift_start_day        = "";
                    $gift_end_day          = "";
                    $gift_vou_validity_day = $gift_validity_day;
                }

            }elseif ($gift_validity_type == '2'){
                if (empty($gift_rule_info)) {
                    return $this->com_return(false,config("params.FAIL"));
                }
                //如果类型为 指定了有效日期
                $gift_time             = $gift_rule_info['gift_time'];
                $gift_time_arr         = explode(",",$gift_time);
                $gift_start_day        = $gift_time_arr[0];
                $gift_end_day          = $gift_time_arr[1];
                $gift_vou_validity_day = "";

                if (strlen($gift_start_day) > 11){
                    $gift_start_day = (int)($gift_start_day * 0.001);
                }

                if (strlen($gift_end_day) > 11){
                    $gift_end_day = (int)($gift_end_day * 0.001);
                }


            }else{
                //如果类型为 0 无限期
                $gift_time = $gift_rule_info['gift_time'];

                if (strlen($gift_time) > 11){
                    $gift_time = (int)($gift_time * 0.001);
                }

                if (!empty($gift_time)){
                    //如果设置了有效开始时间
                    $gift_start_day        = $gift_time;//有效开始时间
                    $gift_end_day          = "";
                    $gift_vou_validity_day = 0; //有效时间无限期
                }else{
                    $gift_start_day = "";
                    $gift_end_day = "";
                    $gift_vou_validity_day = 0;
                }
            }

            $gitVoucherModel = new MstGiftVoucher();

            $update_data = [
                "gift_vou_type"         => $gift_vou_type,
                "gift_vou_name"         => $gift_vou_name,
                "gift_vou_desc"         => $gift_vou_desc,
                "gift_vou_amount"       => $gift_vou_amount,
                "gift_validity_type"    => $gift_validity_type,
                "gift_vou_validity_day" => $gift_vou_validity_day,
                "gift_start_day"        => $gift_start_day,
                "gift_end_day"          => $gift_end_day,
                "gift_vou_exchange"     => $gift_vou_exchange,
                "qty_max"               => $qty_max,
                "updated_at"            => time()
            ];

            $res = $gitVoucherModel
                ->where('gift_vou_id', $gift_vou_id)
                ->update($update_data);

            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(true,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 礼券删除
     * @return array
     */
    public function delete()
    {
        $gift_vou_ids = $this->request->param("gift_vou_id", "");  //礼券id

        $rule = [
            "gift_vou_id|赠券id" => "require",
        ];

        $request_res = [
            "gift_vou_id" => $gift_vou_ids,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)) {
            return $this->com_return(false, $validate->getError(), null);
        }

        Db::startTrans();
        try {
            $id_array = explode(",", $gift_vou_ids);


            $update_data = [
                "is_delete"  => "1",
                "updated_at" => time()
            ];
            $gitVoucherModel = new MstGiftVoucher();

            foreach ($id_array as $gift_vou_id) {
                $res = $gitVoucherModel
                    ->where("gift_vou_id", $gift_vou_id)
                    ->update($update_data);
                if ($res === false) {
                    return $this->com_return(true,config("params.FAIL"));

                }
            }

            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));

        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 是否启用
     * @return array
     */
    public function enable()
    {
        $is_enable     = (int)$this->request->param("is_enable","");
        $gift_vou_id   = $this->request->param("gift_vou_id","");

        $rule = [
            "gift_vou_id|赠券id" => "require",
        ];

        $request_res = [
            "gift_vou_id" => $gift_vou_id,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)) {
            return $this->com_return(false, $validate->getError(), null);
        }

        if ($is_enable == "1"){
            $success_message = "启用成功";
            $fail_message = "启用失败";
        }else{
            $success_message = "关闭成功";
            $fail_message = "关闭失败";
        }

        $update_data = [
            "is_enable"  => $is_enable,
            "updated_at" => time()
        ];

        try {
            $gitVoucherModel = new MstGiftVoucher();

            $res = $gitVoucherModel
                ->where("gift_vou_id",$gift_vou_id)
                ->update($update_data);

            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(true,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 指定人员 - 检索用户信息
     * @return array
     */
    public function retrievalUserInfo()
    {
        $keyword = $this->request->param("keyword","");//电话号码或者uid

        $rule = [
            "keyword|关键字" => "require",
        ];

        $request_res = [
            "keyword" => $keyword,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)) {
            return $this->com_return(false, $validate->getError(), null);
        }

        try {
            $userCommonObj = new UserCommon();

            $res  =$userCommonObj->uidOrPhoneGetUserInfo($keyword);

            return $this->com_return(true,config("params.SUCCESS"),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 指定会员 - 获取卡列表
     * @return array
     */
    public function getCardInfoNum()
    {
        try {
            $cardCommonObj = new CardCommon();

            $cardList = $cardCommonObj->getCardList();

            $userCardModel = new UserCard();

            for ($i = 0; $i < count($cardList); $i ++){
                $card_id = $cardList[$i]['card_id'];

                $num = $userCardModel
                    ->where("card_id",$card_id)
                    ->count();

                $cardList[$i]['num'] = $num;
            }

            return $this->com_return(true,config("params.SUCCESS"),$cardList);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 礼券发放
     * @return array
     */
    public function grantVoucher()
    {
        if ($this->request->method() == "OPTIONS"){
            return $this->com_return(true,config("params.SUCCESS"));
        }

        $type        = $this->request->param("type","");//1 指定人员; 2: 指定会员卡
        $gift_vou_id = $this->request->param("gift_vou_id","");//礼券id
        $user_phone  = $this->request->param("user_phone","");//用户手机号码
        $card_id     = $this->request->param("card_id","");//会员卡id


        $authorization = $this->request->header("Authorization");

        try {
            $adminInfo = self::tokenGetAdminLoginInfo($authorization);
            $review_user = $adminInfo['user_name'];

            $voucherCommonObj = new VoucherCommon();

            if ($type == 1){
                //指定人员
                $rule = [
                    "user_phone|电话号码" => "require|regex:1[0-9]{1}[0-9]{9}",
                    "gift_vou_id|礼券id" => "require",
                ];

                $request_res = [
                    "user_phone"  => $user_phone,
                    "gift_vou_id" => $gift_vou_id,
                ];

                $validate = new Validate($rule);

                if (!$validate->check($request_res)){
                    return $this->com_return(false,$validate->getError(),null);
                }

                $res = $voucherCommonObj->appointUser($user_phone,$gift_vou_id,$review_user);
            }elseif ($type == 2){
                //指定会员卡
                $rule = [
                    "card_id|会员卡id"   => "require",
                    "gift_vou_id|礼券id" => "require",
                ];

                $request_res = [
                    "card_id"     => $card_id,
                    "gift_vou_id" => $gift_vou_id,
                ];

                $validate = new Validate($rule);

                if (!$validate->check($request_res)){
                    return $this->com_return(false,$validate->getError(),null);
                }

                $res = $voucherCommonObj->appointCard($card_id,$gift_vou_id,$review_user);

            }else{
                $res = false;
            }

            if ($res) {
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 生成二维码
     */
    public function makeQrCode()
    {
        $arr = [
            'uid' => 'U1807191310549403287',
            'vid' => 'V180716163228380F72A',
        ];
        $json = json_encode($arr);
        $savePath   = APP_PATH . '/../public/upload/qrcode/';
        $webPath    = 'upload/qrcode/';
        $qrData     = $json;
        $qrLevel    = 'H';
        $qrSize     = '8';
        $savePrefix = 'V';

        try {
            $QrCodeObj = new MakeQrCode();

            $qrCode = $QrCodeObj->createQrCode($savePath, $qrData, $qrLevel, $qrSize, $savePrefix);
            if ($qrCode){
                $pic = $webPath . $qrCode;
            }else{
                $pic = null;
            }
            return $pic;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }
}