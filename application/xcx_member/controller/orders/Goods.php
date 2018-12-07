<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/27
 * Time: 下午2:34
 */
namespace app\xcx_member\controller\orders;



use app\common\controller\GoodsCommon;
use app\common\controller\MemberAuthAction;
use app\common\controller\UserCommon;
use app\common\model\Dishes;
use think\Exception;
use think\Request;
use think\Validate;

class Goods extends MemberAuthAction
{
    /**
     * 菜品分类
     * @return array
     */
    public function dishClassify()
    {
        try {
            $goodsCommonObj = new GoodsCommon();
            $res = $goodsCommonObj->dishTypePublic();
            if (!empty($res)) {
                $is_vip = [
                    "cat_id"   => config("dish.xcx_dish_menu")[0]['key'],
                    "cat_name" => config("dish.xcx_dish_menu")[0]['name'],
                    "cat_img"  => config("dish.xcx_dish_menu")[0]['img'],
                ];
                //向数组的前段新增元素
                array_unshift($res,$is_vip);
            }
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        if ($request->method() == "OPTIONS"){
            return $this->com_return(true,config("params.SUCCESS"));
        }
        $cat_id   = $request->param("cat_id","");//分类id
        $is_gift  = $request->param("is_gift","");//礼金专区
        $pagesize = $request->param("pagesize",config('page_size'));
        $nowPage  = $request->has("nowPage") ? $request->param("nowPage") : "1"; //当前页

        if ($cat_id == "vip"){
            $is_vip = 1;
            $cat_id = "";
        }else{
            $is_vip = "";
        }

        $cat_where = [];
        if (!empty($cat_id)){
            $cat_where['d.cat_id'] = ["eq",$cat_id];
        }

        $is_normal_where = [];
        $is_vip_where    = [];
        if (empty($is_vip)){
            $is_normal_where['d.is_normal'] = ["eq",1];
        }else{
            $is_vip_where['d.is_vip'] = ["eq",1];
        }

        $is_gift_where = [];
        if ($is_gift == "1"){
            $is_gift_where['d.is_gift'] = ["eq",1];
            $cat_where = [];
            $is_normal_where = [];
            $is_vip_where = [];
        }

        $config = [
            "page" => $nowPage
        ];

        try {
            $dishesModel = new Dishes();
            $list = $dishesModel
                ->alias("d")
                ->where($cat_where)
                ->where($is_normal_where)
                ->where($is_vip_where)
                ->where($is_gift_where)
                ->where("d.is_enable",1)
                ->where("d.is_delete",0)
//                ->paginate($pagesize,false,$config);
                ->select();

            $list = json_decode(json_encode($list),true);

            /*获取用户开卡信息 on*/
            $token    = $request->header("Token");
            $userInfo = $this->tokenGetUserInfo($token);
            if ($userInfo === false) {
                return $this->com_return(false,config("params.FAIL"));
            }
            $uid = $userInfo["uid"];
            $userCommonObj = new UserCommon();
            $userCardInfo  = $userCommonObj->uidGetCardInfo($uid);
            if (!empty($userCardInfo)){
                $card_id = $userCardInfo["card_id"];
            }else{
                $card_id = 0;
            }
            /*获取用户开卡信息 off*/
//            $list_data = $list['data'];
            $list_data = $list;

            $goodsCommonObj = new GoodsCommon();
            for ($i = 0; $i <count($list_data); $i ++){
                $dis_id = $list_data[$i]['dis_id'];
                $dishes_card_price = $goodsCommonObj->disIdGetPrice($dis_id);
                for ($m = 0; $m <count($dishes_card_price); $m ++){
                    if ($dishes_card_price[$m]['dis_id'] == $dis_id){
                        if ($dishes_card_price[$m]['card_id'] == $card_id){
                            $list_data[$i]['dis_vip_price']  = (int)$dishes_card_price[$m]['price'];
                        }
                        if ($card_id == 0){
                            $list_data[$i]['dis_vip_price']  = (int)$list_data[$i]['normal_price'];
                        }
                    }else{
                        $list_data[$i]['dis_vip_price']  = $list_data[$i]['normal_price'];
                    }
                    $list_data[$i]['dis_vip_all_price'] = $dishes_card_price;
                }
                $list_data[$i]['deal_price']     = $list_data[$i]['normal_price'];
                $list_data[$i]['discount_price'] = 0;
                if ($is_vip){
                    $list_data[$i]['deal_price'] = $list_data[$i]['dis_vip_price'];
                    $list_data[$i]['discount_price'] = $list_data[$i]['normal_price'] - $list_data[$i]['dis_vip_price'];
                }
                if ($is_gift){
                    $list_data[$i]['deal_price'] = $list_data[$i]['gift_price'];
                    $list_data[$i]['discount_price'] = 0;
                }
            }
//            $list['data'] = $list_data;

            return $this->com_return(true,config("params.SUCCESS"),$list_data);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品详情
     * @param Request $request
     * @return array
     */
    public function dishDetail(Request $request)
    {
        $dis_id  = $request->param("dis_id","");//菜品id
        $is_vip  = $request->param("is_vip","");//会员专区
        $is_gift = $request->param("is_gift","");//礼金区
        $rule = [
            "dis_id|菜品" => "require",
        ];
        $check_res = [
            "dis_id" => $dis_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            /*获取用户开卡信息 on*/
            $token    = $request->header("Token");
            $userInfo = $this->tokenGetUserInfo($token);
            $uid      = $userInfo["uid"];
            $userCommonObj = new UserCommon();
            $userCardInfo  = $userCommonObj->uidGetCardInfo($uid);
            if (!empty($userCardInfo)){
                $card_id = $userCardInfo["card_id"];
            }else{
                $card_id = 0;
            }
            $goodsCommonObj = new GoodsCommon();
            /*获取用户开卡信息 off*/
            $dishInfo = $goodsCommonObj->disIdGetDishesInfo($dis_id);
            if (empty($dishInfo)){
                return $this->com_return(false,config("params.ABNORMAL_ACTION"));
            }

            if ($card_id != 0){
                $dishes_card_price = $goodsCommonObj->disIdGetPrice($dis_id);
                for ($m = 0; $m <count($dishes_card_price); $m ++){
                    if ($dishes_card_price[$m]['dis_id'] == $dis_id){
                        if ($dishes_card_price[$m]['card_id'] == $card_id){
                            $dishInfo['dis_vip_price']  = $dishes_card_price[$m]['price'];
                        }
                    }else{
                        $dishInfo['dis_vip_price']  = $dishInfo['normal_price'];
                    }
                    $dishInfo['dis_vip_all_price'] = $dishes_card_price;
                }
            }else{
                $dishInfo['dis_vip_price'] = $dishInfo['normal_price'];
            }
            $dishInfo['deal_price']     = $dishInfo['normal_price'];
            $dishInfo['discount_price'] = 0;
            if ($is_vip == "vip"){
                $dishInfo['deal_price']     = $dishInfo['dis_vip_price'];
                $dishInfo['discount_price'] = $dishInfo['normal_price'] - $dishInfo['dis_vip_price'];
            }
            if ($is_gift){
                $dishInfo['deal_price']     = $dishInfo['gift_price'];
                $dishInfo['discount_price'] = 0;
            }
            $dis_type = $dishInfo['dis_type'];
            if ($dis_type){
                //套餐
                $dishesComboInfo = $goodsCommonObj->disIdGetComboInfo($dis_id);
                foreach ($dishesComboInfo as $k => $v){
                    if ($v['type']){
                        $dishesComboInfo[$k]['dis_name'] = $v['type_desc'];
                    }
                }
                $dishesComboInfo = make_tree($dishesComboInfo,"combo_id","parent_id");
                $dishInfo['dishes_combo_info'] = $dishesComboInfo;
            }else{
                $dishInfo['dishes_combo_info'] = [];
            }
            return $this->com_return(true,config("params.SUCCESS"),$dishInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}