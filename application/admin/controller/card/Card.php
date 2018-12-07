<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午6:29
 */

namespace app\admin\controller\card;


use app\common\controller\AdminAuthAction;
use app\common\controller\CardCommon;
use app\common\model\MstCardType;
use app\common\model\MstCardVip;
use app\common\model\MstCardVipGiftRelation;
use app\common\model\MstCardVipVoucherRelation;
use app\common\model\MstSalesmanType;
use think\Db;
use think\Exception;
use think\Validate;

class Card extends AdminAuthAction
{
    /**
     * 获取卡类型
     * @return array
     */
    public function type()
    {
        try {
            $cardCommonObj = new CardCommon();
            $res = $cardCommonObj->getCardType();
            return $this->com_return(true,config('params.SUCCESS'),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取推荐人类型
     */
    public function getRecommendUserType()
    {
        try {
            $mstSalesmanType = new MstSalesmanType();
            $res = $mstSalesmanType
                ->where("is_enable",1)
                ->where("is_delete",0)
                ->order("sort,updated_at DESC")
                ->select();
            return $this->com_return(true,config('params.SUCCESS'),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 会籍卡列表
     * @return array
     */
    public function index()
    {
        $keyword      = $this->request->param("keyword","");//关键字
        $pagesize     = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage      = $this->request->param("nowPage","1");
        $orderBy      = $this->request->param("orderBy","sort");//根据什么排序
        $sort         = $this->request->param("sort","asc");//正序or倒叙
        $card_type_id = $this->request->param('card_type_id','');//卡类型.默认为 vip卡

        $cardTypeWhere = [];
        if (!empty($card_type_id )){
            $cardTypeWhere['cv.card_type_id'] = ['eq',$card_type_id];
        }
        $where = [];
        if (!empty($keyword)) {
            $where['ct.type_name,cv.card_name|cv.card_level|cv.card_no_prefix|cv.card_desc|cv.card_equities'] = ["like","%$keyword%"];
        }
        if (empty($pagesize)) $pagesize = config('page_size');

        if (empty($orderBy)){
            $orderBys = "cv.sort";
        }else{
            $orderBys = "cv.".$orderBy;
        }

        if (empty($sort)) $sort = "asc";

        $config = [
            "page" => $nowPage,
        ];

        try {
            $cardVipModel = new MstCardVip();

            $column = $cardVipModel->column;

            foreach ($column as $key => $val) {
                $column[$key] = "cv.".$val;
            }

            $card_list = $cardVipModel
                ->alias("cv")
                ->join("mst_card_type ct","ct.type_id = cv.card_type_id")
                ->where("ct.is_enable",1)
                ->where("ct.is_delete",0)
                ->where('cv.is_delete','0')
                ->where($cardTypeWhere)
                ->where($where)
                ->order($orderBys,$sort)
                ->field("ct.type_name")
                ->field($column)
                ->paginate($pagesize,false,$config);

            $card_list = json_decode(json_encode($card_list),true);

            $card_list['filter']['orderBy'] = $orderBy;
            $card_list['filter']['sort']    = $sort;
            $card_list['filter']['keyword'] = $keyword;

            $card_list_data = $card_list['data'];

            for ($i=0;$i<count($card_list_data);$i++){
                $salesman = $card_list_data[$i]['salesman'];
                $card_list_data[$i]['salesman']       = explode(",",$salesman);
                $card_list_data[$i]['card_cash_gift'] = (string)$card_list_data[$i]['card_cash_gift'];
            }
            $card_list['data'] = $card_list_data;

            return $this->com_return(true,config('params.SUCCESS'),$card_list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会籍卡添加
     * @return array
     */
    public function add()
    {
//        $gift_info      = $this->request->param("gift_info","");//赠送礼品id组,逗号隔开
//        $voucher_info   = $this->request->param("voucher_info","");//礼券id组,逗号隔开

        $card_type_id   = $this->request->param("card_type_id","");//卡片类型id
        $card_name      = $this->request->param("card_name","");//VIP卡名称
        $card_image     = $this->request->param("card_image","");//VIP卡背景图
        $card_no_prefix = $this->request->param("card_no_prefix","LV");//卡号前缀（两位数字）
        $card_desc      = $this->request->param("card_desc","");//VIP卡使用说明及其他描述
        $card_equities  = $this->request->param("card_equities","");//卡片享受权益详情
        $is_giving      = $this->request->param("is_giving","");//是否可赠送

        $card_amount         = $this->request->param("card_amount","");//充值金额
        $card_pay_amount     = $this->request->param("card_pay_amount","");//支付金额
        $card_validity_time  = $this->request->param("card_validity_time","");//卡片有效期限(年)
        $card_cash_gift      = $this->request->param("card_cash_gift","");//开卡赠送礼金
//        $card_job_cash_gif   = $this->request->param("card_job_cash_gif","");//推荐人返还礼金
//        $card_job_commission = $this->request->param("card_job_commission","");//推荐人返还佣金
        $salesman            = $this->request->param('salesman',"");//销售人员类型,多个以逗号拼接


        $sort           = $this->request->param("sort","100");//排序
        $is_enable      = $this->request->param("is_enable","1");//是否启用  0否 1是

        $rule = [
            'card_type_id|卡片类型'             =>  'require|max:10',  //卡片类型id
            'card_name|VIP卡名称'               =>  'require|max:30|unique:mst_card_vip',  //VIP卡名称
            'card_image|VIP卡背景图'            =>  'require', //VIP卡背景图
            'card_no_prefix|卡号前缀'           =>  'require|max:2', //卡号前缀（两位数字）
            'card_desc|VIP卡使用说明及其他描述'   =>  'require|max:300', //VIP卡使用说明及其他描述
            'card_equities|卡片享受权益详情'     =>  'require|max:1000', //卡片享受权益详情
            'is_giving|是否可赠送'              =>  'require', //是否可赠送
            'card_amount|充值金额'              =>  'require|number|max:11', //充值金额
            'card_pay_amount|支付金额'          =>  'require|number|max:11', //充值金额
            'card_validity_time|有效期限'       =>  'require', //有效期限
            'card_cash_gift|开卡赠送礼金'        =>  'require|number|max:11', //开卡赠送礼金
//            'card_job_cash_gif|推荐人返还礼金'   =>  'require|number|max:11', //推荐人返还礼金
//            'card_job_commission|推荐人返还佣金' =>  'require|number|max:11', //推荐人返还佣金
            'salesman|销售人员类型'              =>  'require', //销售人员类型

            'is_enable|是否启用'                =>  'require', //是否启用  0否 1是
        ];

        $check_data = [
            "card_type_id"        => $card_type_id,
            "card_name"           => $card_name,
            "card_image"          => $card_image,
            "card_no_prefix"      => $card_no_prefix,
            "card_desc"           => $card_desc,
            "card_equities"       => $card_equities,
            "is_giving"           => $is_giving,
            "card_amount"         => $card_amount,
            "card_pay_amount"     => $card_pay_amount,
            "card_validity_time"  => $card_validity_time,
            "card_cash_gift"      => $card_cash_gift,
//            "card_job_cash_gif"   => $card_job_cash_gif,
//            "card_job_commission" => $card_job_commission,
            "salesman"            => $salesman,
            "is_enable"           => $is_enable,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $insert_data = [
            "card_type_id"        => $card_type_id,
            "card_name"           => $card_name,
            "card_image"          => $card_image,
            "card_no_prefix"      => $card_no_prefix,
            "card_desc"           => $card_desc,
            "card_equities"       => $card_equities,
            "is_giving"           => $is_giving,
            "card_amount"         => $card_amount,
            "card_pay_amount"     => $card_pay_amount,
            "card_validity_time"  => $card_validity_time,
            "card_cash_gift"      => $card_cash_gift,
//            "card_job_cash_gif"   => $card_job_cash_gif,
//            "card_job_commission" => $card_job_commission,
            "salesman"            => $salesman,
            "sort"                => $sort,
            "is_enable"           => $is_enable,
            "created_at"          => time(),
            "updated_at"          => time()
        ];

        Db::startTrans();
        try {
            $cardCommonObj = new CardCommon();
            //写入卡表
            $card_id = $cardCommonObj->insert_card_vip($insert_data);
            if (!$card_id) {
                return $this->com_return(false,config("FAIL"));
            }

            /*写入VIP开卡赠送礼品关系表 On*/
           /* if (!empty($gift_info)){
                $gift_id_arr = explode(",",$gift_info);
                for ($i = 0; $i < count($gift_id_arr); $i++){
                    $gift_id = $gift_id_arr[$i];
                    $gift_res = $cardCommonObj->insert_card_vip_gift_relation($card_id,$gift_id);
                    if (!$gift_res) {
                        return $this->com_return(false,config("FAIL"));
                    }
                }
            }*/
            /*写入VIP开卡赠送礼品关系表 Off*/


            /*写入VIP卡赠送消费券关系表 On*/
            /*if (!empty($voucher_info)){
                $gift_vou_id_arr = explode(",",$voucher_info);
                for ($m = 0; $m < count($gift_vou_id_arr); $m++){
                    $gift_vou_id = $gift_vou_id_arr[$m];
                    $voucher_res = $cardCommonObj->insert_card_vip_voucher_relation($card_id,$gift_vou_id);
                    if (!$voucher_res) {
                        return $this->com_return(false,config("FAIL"));
                    }
                }
            }*/
            /*写入VIP卡赠送消费券关系表 Off*/

            //获取当前登录管理员
           $user_info = self::tokenGetAdminLoginInfo($this->request->header('Authorization'));
           $action_user = $user_info['user_name'];

            //添加至系统操作日志
            $this->addSysLog(time(),"$action_user","添加卡 -> $card_id ($card_name)",$this->request->ip());

            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));

        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会籍卡编辑
     * @return array
     */
    public function edit()
    {
        $card_id        = $this->request->param("card_id","");//卡id
//        $gift_info      = $this->request->param("gift_info","");//礼品id组,以逗号隔开
//        $voucher_info   = $this->request->param("voucher_info","");//礼券id组,以逗号隔开
        $card_type_id   = $this->request->param("card_type_id","");//卡片类型必须id
        $card_name      = $this->request->param("card_name","");//VIP卡名称必须
        $card_image     = $this->request->param("card_image","");//VIP卡背景图必须
        $card_no_prefix = $this->request->param("card_no_prefix","LV");//卡号前缀必须
        $card_desc      = $this->request->param("card_desc","");//VIP卡使用说明及其他描述必须
        $card_equities  = $this->request->param("card_equities","");//卡片享受权益详情必须
        $is_giving      = $this->request->param("is_giving","");//是否可赠送

        $card_amount         = $this->request->param("card_amount","");//充值金额必须
        $card_pay_amount     = $this->request->param("card_pay_amount","");//支付金额
        $card_validity_time  = $this->request->param("card_validity_time","");//卡片有效期限(年)
        $card_cash_gift      = $this->request->param("card_cash_gift","");//开卡赠送礼金
//        $card_job_cash_gif   = $this->request->param("card_job_cash_gif","");//推荐人返还礼金
//        $card_job_commission = $this->request->param("card_job_commission","");//推荐人返还佣金
        $salesman            = $this->request->param('salesman',"");//销售人员类型,多个以逗号拼接

        $sort           = $this->request->param("sort","");//排序
        $is_enable      = $this->request->param("is_enable",0);//是否激活

        $rule = [
            "card_id|卡id"                    => "require",
            "card_type_id|卡片类型"            => "require|max:10",
            "card_name|VIP卡名称"              => "require|max:30|unique:mst_card_vip",
            "card_image|VIP卡背景图"           => "require",
            "card_no_prefix|卡号前缀"          => "require|max:2",
            "card_desc|VIP卡使用说明及其他描述"  => "require|max:300",
            "card_equities|卡片享受权益详情"    => "require|max:1000",
            "is_giving|是否可赠送"              => "require",
            "card_amount|充值金额"             => "require|number",
            'card_pay_amount|支付金额'          =>  'require|number|max:11', //充值金额
            'card_validity_time|有效期限'       =>  'require', //有效期限
            "card_cash_gift|开卡赠送礼金"       => "require|number",
//            'card_job_commission|推荐人返还佣金' =>  'require|number|max:11', //推荐人返还佣金
            'salesman|销售人员类型'              =>  'require', //销售人员类型
            "is_enable|是否激活"       => "require",
        ];

        $check_params = [
            "card_id"             => $card_id,
            "card_type_id"        => $card_type_id,
            "card_name"           => $card_name,
            "card_image"          => $card_image,
            "card_no_prefix"      => $card_no_prefix,
            "card_desc"           => $card_desc,
            "card_equities"       => $card_equities,
            "is_giving"           => $is_giving,
            "card_amount"         => $card_amount,
            "card_pay_amount"     => $card_pay_amount,
            "card_validity_time"  => $card_validity_time,
            "card_cash_gift"      => $card_cash_gift,
//            "card_job_cash_gif"   => $card_job_cash_gif,
//            "card_job_commission" => $card_job_commission,
            "salesman"            => $salesman,
            "is_enable"           => $is_enable,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_params)){
            return $this->com_return(false,$validate->getError());
        }

        $data = [
            "card_type_id"        => $card_type_id,
            "card_name"           => $card_name,
            "card_image"          => $card_image,
            "card_no_prefix"      => $card_no_prefix,
            "card_desc"           => $card_desc,
            "card_equities"       => $card_equities,
            "is_giving"           => $is_giving,
            "card_amount"         => $card_amount,
            "card_pay_amount"     => $card_pay_amount,
            "card_validity_time"  => $card_validity_time,
            "card_cash_gift"      => $card_cash_gift,
//            "card_job_cash_gif"   => $card_job_cash_gif,
//            "card_job_commission" => $card_job_commission,
            "salesman"            => $salesman,
            "sort"                => $sort,
            "is_enable"           => $is_enable,
            "updated_at"          => time()
        ];

        Db::startTrans();
        try {
            $cardCommonObj = new CardCommon();

            $updateMstCardVipRes = $cardCommonObj->updateMstCardVip($card_id,$data);
            if (!$updateMstCardVipRes) {
                return $this->com_return(false, config('params.FAIL'));
            }

            //删除礼品关系表中关于此卡的信息
           /* $deleteCardVipGiftRelationRes  =$cardCommonObj->deleteCardVipGiftRelation($card_id);
            if (!$deleteCardVipGiftRelationRes) {
                return $this->com_return(false, config('params.FAIL'));
            }*/

            /*更新礼品与卡关系表 On*/
            /*if (!empty($gift_info)){
                //如果勾选的有礼品
                $gift_id_arr = explode(",",$gift_info);
                for ($i = 0; $i < count($gift_id_arr); $i++){
                    $gift_id = $gift_id_arr[$i];
                    $gift_res = $cardCommonObj->insert_card_vip_gift_relation($card_id,$gift_id);
                    if (!$gift_res) {
                        return $this->com_return(false, config('params.FAIL'));
                    }
                }
            }*/
            /*更新礼品与卡关系表 Off*/

            /*删除礼券关系表中关于此卡的信息 On*/
           /* $deleteCardVipVoucherRelationRes = $cardCommonObj->deleteCardVipVoucherRelation($card_id);
            if (!$deleteCardVipVoucherRelationRes) {
                return $this->com_return(false, config('params.FAIL'));
            }*/
            /*删除礼券关系表中关于此卡的信息 Off*/

            /*if (!empty($voucher_info)){
                //如果勾选的有礼券
                $gift_vou_id_arr = explode(",",$voucher_info);

                for ($m = 0; $m < count($gift_vou_id_arr); $m++){
                    $gift_vou_id = $gift_vou_id_arr[$m];
                    //更新礼券与卡关系表
                    $voucher_res = $cardCommonObj->insert_card_vip_voucher_relation($card_id,$gift_vou_id);
                    if (!$voucher_res) {
                        return $this->com_return(false, config('params.FAIL'));
                    }
                }
            }*/

            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));

        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会籍卡删除
     * @return array
     */
    public function delete()
    {
        $card_ids = $this->request->param("card_id","");

        $rule = [
            'card_id|会员卡' =>  'require',
        ];

        $check_data = [
            "card_id" => $card_ids,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        Db::startTrans();
        try {
            $id_array = explode(",",$card_ids);

            $cardCommonObj = new CardCommon();

            foreach ($id_array as $card_id){

                $deleteMstCardVipRes= $cardCommonObj->deleteMstCardVip($card_id);
                if (!$deleteMstCardVipRes) {
                    return $this->com_return(false, config('params.FAIL'));
                }
            }

            //获取当前登录管理员
            $user_info   = self::tokenGetAdminLoginInfo($this->request->header('Authorization'));
            $action_user = $user_info['user_name'];

            //添加至系统操作日志
            $this->addSysLog(time(),"$action_user","删除卡 -> $card_ids",$this->request->ip());

            Db::commit();
            return $this->com_return(true, config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会籍卡是否启用
     * @return array
     */
    public function enable()
    {
        $card_id   = $this->request->param("card_id","");//卡id
        $is_enable = (int)$this->request->param("is_enable","");//是否启用

        $rule = [
            'card_id|会员卡' =>  'require',
        ];

        $check_data = [
            "card_id" => $card_id,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        if ($is_enable == 1){
            $action_des = "启用卡";
        }else{
            $action_des = "禁用卡";
        }

        $params = [
            'is_enable'  => $is_enable,
            'updated_at' => time()
        ];

        try {
            $cardCommonObj = new CardCommon();

            $updateMstCardVipRes = $cardCommonObj->updateMstCardVip($card_id,$params);

            if (!$updateMstCardVipRes) {
                return $this->com_return(false,config('params.FAIL'));
            }

            //获取当前登录管理员
            $user_info   = self::tokenGetAdminLoginInfo($this->request->header('Authorization'));
            $action_user = $user_info['user_name'];

            //添加至系统操作日志
            $this->addSysLog(time(),"$action_user","$action_des -> $card_id",$this->request->ip());

            return $this->com_return(true, config("params.SUCCESS"));

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 卡排序
     * @return array
     */
    public function sortEdit()
    {
        $card_id = $this->request->param('card_id','');
        $sort    = $this->request->param('sort','');
        $rule = [
            'card_id|会员卡' =>  'require',
        ];

        $check_data = [
            "card_id" => $card_id,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        if (empty($sort))  $sort = 100;

        $params = [
            'sort'       => $sort,
            'updated_at' => time()
        ];

        try {
            $cardCommonObj = new CardCommon();

            $updateMstCardVipRes = $cardCommonObj->updateMstCardVip($card_id,$params);

            if (!$updateMstCardVipRes) {
                return $this->com_return(false,config('params.FAIL'));
            }

            return $this->com_return(true, config("params.SUCCESS"));

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 给卡新增赠品or礼券
     * @return array
     */
    public function addGiftOrVoucher()
    {
        $type          = $this->request->param('type','');
        $card_id       = $this->request->param('card_id','');
        $gift_id       = $this->request->param('gift_id','');
        $qty           = $this->request->param('qty','');
        $gift_vou_id   = $this->request->param('gift_vou_id','');
        $gift_vou_type = $this->request->param('gift_vou_type','');
        $rule = [
            'card_id|会员卡' =>  'require',
            'type|类型'      =>  'require',
            'qty|数量'       =>  'require',
        ];

        $check_data = [
            "card_id" => $card_id,
            "type"    => $type,
            "qty"     => $qty,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $cardCommonObj = new CardCommon();

            $params = [
                'card_id' => $card_id,
                'qty'     => $qty
            ];

            if ($type == "gift"){
                if (empty($gift_id)) return $this->com_return(false,'礼品不能为空');
                $params['gift_id'] = $gift_id;

                $res = $cardCommonObj->checkCardVipGiftRelation("$card_id","$gift_id");

                $tableModel = new MstCardVipGiftRelation();

            }else{
                if (empty($gift_vou_id)) return $this->com_return(false,'礼券不能为空');
                if (empty($gift_vou_type)) return $this->com_return(false,'礼券类型不能为空');
                $params['gift_vou_id']   = $gift_vou_id;
                $params['gift_vou_type'] = $gift_vou_type;

                $res = $cardCommonObj->checkCardVipVoucherRelation("$card_id","$gift_vou_id","$gift_vou_type");

                $tableModel = new MstCardVipVoucherRelation();
            }

            if ($res){
                return $this->com_return(false,'已存在相关礼品');
            }

            $res = $tableModel
                ->insert($params);

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
     * 删除卡内关联赠品or礼券
     * @return array
     */
    public function deleteGiftOrVoucher()
    {
        $type          = $this->request->param('type','');
        $card_id       = $this->request->param('card_id','');
        $gift_id       = $this->request->param('gift_id','');
        $gift_vou_id   = $this->request->param('gift_vou_id','');
        $gift_vou_type = $this->request->param('gift_vou_type','');

        $rule = [
            'card_id|会员卡' =>  'require',
            'type|类型'      =>  'require',
        ];

        $check_data = [
            "card_id" => $card_id,
            "type"    => $type,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            if ($type == "gift"){
                if (empty($gift_id)) return $this->com_return(false,'礼品不能为空');
                $tableModel = new MstCardVipGiftRelation();
                $res = $tableModel
                    ->where('card_id',$card_id)
                    ->where('gift_id',$gift_id)
                    ->delete();
            }else{
                if (empty($gift_vou_id)) return $this->com_return(false,'礼券不能为空');
                if (empty($gift_vou_type)) return $this->com_return(false,'礼券类型不能为空');
                $tableModel = new MstCardVipVoucherRelation();
                $res = $tableModel
                    ->where('card_id',$card_id)
                    ->where('gift_vou_id',$gift_vou_id)
                    ->where('gift_vou_type',$gift_vou_type)
                    ->delete();
            }
            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(true,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }




}