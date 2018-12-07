<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午12:09
 */
namespace app\admin\controller\dishes;

use app\common\controller\AdminAuthAction;
use app\common\controller\DishesCommon;
use app\common\model\Dishes;
use app\common\model\DishesCardPrice;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class Dish extends AdminAuthAction
{
    /**
     * 菜品类型
     * @return array
     */
    public function dishType()
    {
        $dishType = config("dish.dish_type");
        return $this->com_return(true,config("params.SUCCESS"),$dishType);
    }

    /**
     * 菜品列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $keyword    = $request->param("keyword","");
        $cat_id     = $request->param("cat_id","");//菜品分类id
        $att_id     = $request->param("att_id","");//菜品属性id
        $dis_type   = $request->param("dis_type","");//菜品类型
        $pagesize   = $request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage    = $request->param("nowPage","1");
        if (empty($pagesize)) $pagesize = config('page_size');

        $where = [];
        if (!empty($keyword)){
            $where["d.dis_sn|d.dis_name"] = ["like","%$keyword%"];
        }

        $cat_where = [];
        if (!empty($cat_id)){
            $cat_where['d.cat_id'] = ['eq',$cat_id];
        }

        $att_where = [];
        if (!empty($att_where)){
            $att_where['d.att_id'] = ['eq',$att_id];
        }

        if (empty($dis_type)){
            $dis_type = 0;
        }

        $config = [
            "page" => $nowPage,
        ];

        try {
            $dishModel = new Dishes();
            $column = $dishModel->column;
            foreach ($column as $key => $val){
                $column[$key] = "d.".$val;
            }
            $list = $dishModel
                ->alias("d")
                ->join("dishes_attribute da","da.att_id = d.att_id")
                ->join("dishes_category dc","dc.cat_id = d.cat_id")
                ->where("d.is_delete","0")
                ->where($where)
                ->where($cat_where)
                ->where($att_where)
                ->where("d.dis_type",$dis_type)
                ->order("d.sort")
                ->field($column)
                ->field("da.att_name")
                ->field("dc.cat_name")
                ->field("dc.cat_img")
                ->paginate($pagesize,false,$config);
            $list = json_decode(json_encode($list),true);

            $dishesCardPriceModel = new DishesCardPrice();

            for ($i = 0; $i < count($list['data']); $i++){
                $dis_id = $list['data'][$i]['dis_id'];
                $dishes_card_price = $dishesCardPriceModel
                    ->alias("dcp")
                    ->join("mst_card_vip mcv","mcv.card_id = dcp.card_id")
                    ->where('dcp.dis_id',$dis_id)
                    ->field("mcv.card_name")
                    ->field("dcp.dis_id,dcp.card_id,dcp.price")
                    ->select();
                $dishes_card_price = json_decode(json_encode($dishes_card_price),true);
                $list['data'][$i]['dishes_card_price'] = $dishes_card_price;
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品添加
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        $dis_type     = $request->param("dis_type","");//菜品类型  0 单品    1 套餐
        $dis_name     = $request->param("dis_name","");//菜品名称
        $dis_img      = $request->param("dis_img","");//菜品图片
        $dis_desc     = $request->param("dis_desc","");//菜品规格属性描述
        $cat_id       = $request->param("cat_id","");//菜品分类id
        $att_id       = $request->param("att_id","");//菜品属性id
        $is_normal    = $request->param("is_normal","");//是否在普通区上架   0否  1是
        $normal_price = $request->param("normal_price","");//普通区单价
        $is_gift      = $request->param("is_gift","");//是否在礼金区上架   0否  1是
        $gift_price   = $request->param("gift_price","");//礼金区单价
        $is_vip       = $request->param("is_vip","");//是否在会员区上架
        $is_give      = $request->param("is_give","");//是否可赠送 0 否 1是
        $sort         = $request->param("sort","");//排序
        $is_enable    = $request->param("is_enable","");//是否启用  0否 1是

        $dishes_card_price = $request->param("dishes_card_price","");//菜品绑定卡价格

        $rule = [
            "dis_type|菜品类型"         => "require",
            "dis_name|菜品名称"         => "require|max:100|unique_delete:dishes",
            "dis_img|菜品图片"          => "require",
            "dis_desc|菜品描述"         => "max:300",
            "cat_id|菜品分类id"         => "require",
            "att_id|菜品属性id"         => "require",
            "is_normal|是否在普通区上架" => "require",
            "normal_price|普通区单价"   => "egt:0",
            "is_gift|是否在礼金区上架"   => "require",
            "gift_price|礼金区单价"     => "egt:0",
            "is_vip|是否在会员区上架"    => "require",
            "is_give|是否可赠送"        => "require",
            "sort|排序"                => "number",
            "is_enable|是否启用"        => "require|number",
        ];

        $check_res = [
            "dis_type"     => $dis_type,
            "dis_name"     => $dis_name,
            "dis_img"      => $dis_img,
            "dis_desc"     => $dis_desc,
            "cat_id"       => $cat_id,
            "att_id"       => $att_id,
            "is_normal"    => $is_normal,
            "normal_price" => $normal_price,
            "is_gift"      => $is_gift,
            "gift_price"   => $gift_price,
            "is_vip"       => $is_vip,
            "is_give"      => $is_give,
            "sort"         => $sort,
            "is_enable"    => $is_enable,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $dis_sn = generateReadableUUID("D");
        //此时添加单品
        $params = [
            "dis_type"     => $dis_type,
            "dis_sn"       => $dis_sn,
            "dis_name"     => $dis_name,
            "dis_img"      => $dis_img,
            "dis_desc"     => $dis_desc,
            "cat_id"       => $cat_id,
            "att_id"       => $att_id,
            "is_normal"    => $is_normal,
            "normal_price" => $normal_price,
            "is_gift"      => $is_gift,
            "gift_price"   => $gift_price,
            "is_vip"       => $is_vip,
            "is_give"      => $is_give,
            "sort"         => $sort,
            "is_enable"    => $is_enable,
            "created_at"   => time(),
            "updated_at"   => time(),
        ];

        Db::startTrans();
        try {
            $disheCommonObj = new DishesCommon();
            //先去添加主菜单信息
            $dis_id =  $disheCommonObj->dishSingleAdd($params,$dishes_card_price);

            if ($dis_id === false){
                return $this->com_return(false,config("params.FAIL"));
            }
            if (!$dis_type){
                Db::commit();
                return $this->com_return(true,config("params.SUCCESS"));
            }

            //此时添加套餐
            $dishes_combo = $request->param("dishes_combo","");//套餐内信息数据
            $dishes_combo = json_decode($dishes_combo,true);
            //换品组
            if (empty($dishes_combo)){
                //选中的换品组内单品不能为空
                return $this->com_return(false,config("params.DISHES")['COMBO_DIST_EMPTY']);
            }

            for ($i = 0; $i < count($dishes_combo); $i ++){
                $type  = $dishes_combo[$i]['type'];
                if ($type == 1){
                    $combo_dish_id  = 0;
                }else{
                    $combo_dish_id  = (int)$dishes_combo[$i]['dis_id'];
                }
                $type_desc         = $dishes_combo[$i]['type_desc'];
                $quantity          = $dishes_combo[$i]['quantity'];
                $dish_little_group = $dishes_combo[$i]['children'];
                //将数据写入套餐表
                $comboDishParams = [
                    "main_dis_id" => $dis_id,
                    "dis_id"      => $combo_dish_id,
                    "type"        => $type,
                    "type_desc"   => $type_desc,
                    "quantity"    => $quantity
                ];
                $combo_little_id = Db::name("dishes_combo")
                    ->insertGetId($comboDishParams);
                if ($combo_little_id === false) {
                    return $this->com_return(false,config("params.FAIL"));
                }

                if ($type){
                    if (empty($dish_little_group)){
                        return $this->com_return(false,config("params.DISHES")['COMBO_ID_NOT_EMPTY']);
                    }

                    for ($m = 0; $m < count($dish_little_group); $m ++){

                        $little_dish_id  = $dish_little_group[$m]['dis_id'];
                        $little_quantity = $dish_little_group[$m]['quantity'];

                        $littleDishParams = [
                            "main_dis_id" => $dis_id,
                            "dis_id"      => $little_dish_id,
                            "parent_id"   => $combo_little_id,
                            "quantity"    => $little_quantity
                        ];

                        $is_ok = Db::name("dishes_combo")
                            ->insertGetId($littleDishParams);
                        if ($is_ok === false) {
                            return $this->com_return(false,config("params.FAIL"));
                        }
                    }
                }
            }
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));

        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品详情
     * @param Request $request
     * @return array
     */
    public function dishDetails(Request $request)
    {
        $dis_id = $request->param("dis_id","");//菜品id
        $rule = [
            "dis_id|菜品id"         => "require"
        ];
        $check_res = [
            "dis_id"     => $dis_id
        ];
        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }
        try {
            $dishModel = new Dishes();
            $column = $dishModel->column;
            foreach ($column as $key => $val){
                $column[$key] = "d.".$val;
            }
            $info = $dishModel
                ->alias("d")
                ->join("dishes_attribute da","da.att_id = d.att_id")
                ->join("dishes_category dc","dc.cat_id = d.cat_id")
                ->where("dis_id",$dis_id)
                ->where("d.is_delete","0")
                ->field($column)
                ->field("da.att_name")
                ->field("dc.cat_name")
                ->field("dc.cat_img")
                ->find();
            $info = json_decode(json_encode($info),true);

            $dishesCardPriceModel = new DishesCardPrice();
            $dishes_card_price = $dishesCardPriceModel
                ->alias("dcp")
                ->join("mst_card_vip mcv","mcv.card_id = dcp.card_id")
                ->where('dcp.dis_id',$dis_id)
                ->field("mcv.card_name")
                ->field("dcp.dis_id,dcp.card_id,dcp.price")
                ->select();
            $dishes_card_price = json_decode(json_encode($dishes_card_price),true);
            $info["dishes_card_price"] = $dishes_card_price;
            $dishes_combo = Db::name("dishes_combo")
                ->alias("dc")
                ->join("dishes d","d.dis_id = dc.dis_id","LEFT")
                ->where("dc.main_dis_id",$dis_id)
                ->field("dc.combo_id,dc.main_dis_id,dc.dis_id,dc.type,dc.type_desc,dc.parent_id,dc.quantity")
                ->field("d.dis_name")
                ->select();
            $dishes_combo = json_decode(json_encode($dishes_combo),true);

            for ($i = 0; $i < count($dishes_combo); $i ++){
                if ($dishes_combo[$i]['type']){
                    $dishes_combo[$i]['dis_name'] = $dishes_combo[$i]['type_desc'];
                }
            }

            $dishes_combo = make_tree($dishes_combo,"combo_id","parent_id");

            $info["dishes_combo"] = $dishes_combo;

            return $this->com_return(true,config("params.SUCCESS"),$info);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }


    /**
     * 主菜品编辑提交
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        $dis_id       = $request->param("dis_id","");//菜品id
        $dis_name     = $request->param("dis_name","");//菜品名称
        $dis_img      = $request->param("dis_img","");//菜品图片
        $dis_desc     = $request->param("dis_desc","");//菜品规格属性描述
        $cat_id       = $request->param("cat_id","");//菜品分类id
        $att_id       = $request->param("att_id","");//菜品属性id
        $is_normal    = $request->param("is_normal","");//是否在普通区上架   0否  1是
        $normal_price = $request->param("normal_price","");//普通区单价
        $is_gift      = $request->param("is_gift","");//是否在礼金区上架   0否  1是
        $gift_price   = $request->param("gift_price","");//礼金区单价
        $is_vip       = $request->param("is_vip","");//是否在会员区上架
        $is_give      = $request->param("is_give","");//是否可赠送 0否 1是
        $sort         = $request->param("sort","");//排序
        $is_enable    = $request->param("is_enable","");//是否启用  0否 1是

        $dishes_card_price = $request->param("dishes_card_price","");//菜品绑定卡价格

        $rule = [
            "dis_id|菜品id"            => "require",
            "dis_name|菜品名称"         => "require|max:100|unique_me:dishes,dis_id",
            "dis_img|菜品图片"          => "require",
            "dis_desc|菜品描述"         => "max:300",
            "cat_id|菜品分类id"         => "require",
            "att_id|菜品属性id"         => "require",
            "is_normal|是否在普通区上架" => "require",
            "normal_price|普通区单价"   => "egt:0",
            "is_gift|是否在礼金区上架"   => "require",
            "gift_price|礼金区单价"     => "egt:0",
            "is_vip|是否在会员区上架"    => "require",
            "is_give|是否可赠送"        => "require",
            "sort|排序"                => "number",
            "is_enable|是否启用"        => "require|number",
        ];
        $check_res = [
            "dis_id"       => $dis_id,
            "dis_name"     => $dis_name,
            "dis_img"      => $dis_img,
            "dis_desc"     => $dis_desc,
            "cat_id"       => $cat_id,
            "att_id"       => $att_id,
            "is_normal"    => $is_normal,
            "normal_price" => $normal_price,
            "is_gift"      => $is_gift,
            "gift_price"   => $gift_price,
            "is_vip"       => $is_vip,
            "is_give"      => $is_give,
            "sort"         => $sort,
            "is_enable"    => $is_enable,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "dis_name"     => $dis_name,
            "dis_img"      => $dis_img,
            "dis_desc"     => $dis_desc,
            "cat_id"       => $cat_id,
            "att_id"       => $att_id,
            "is_normal"    => $is_normal,
            "normal_price" => $normal_price,
            "is_gift"      => $is_gift,
            "gift_price"   => $gift_price,
            "is_vip"       => $is_vip,
            "is_give"      => $is_give,
            "sort"         => $sort,
            "is_enable"    => $is_enable,
            "updated_at"   => time(),
        ];

        Db::startTrans();
        try{
            $dishModel = new Dishes();
            $is_ok = $dishModel
                ->where('dis_id',$dis_id)
                ->update($params);
            if ($is_ok === false){
                return $this->com_return(false,config("params.FAIL"));
            }
            if ($is_vip){
                //如果在vip上架,则记录vip各卡价格
                if (empty($dishes_card_price)){
                    return $this->com_return(false,config("params.DISHES")['CARD_PRICE_EMPTY']);
                }

                $dishesCardPriceModel = new DishesCardPrice();
                //删除已绑定的卡价格信息
                $is_delete = $dishesCardPriceModel
                    ->where("dis_id",$dis_id)
                    ->delete();
                if ($is_delete === false){
                    return $this->com_return(false,config("params.FAIL"));
                }

                $dishes_card_price = json_decode($dishes_card_price,true);

                for ($i = 0; $i <count($dishes_card_price); $i ++){
                    $card_id = $dishes_card_price[$i]['card_id'];
                    $price   = $dishes_card_price[$i]['price'];
                    $cardPriceParams = [
                        "dis_id"  => $dis_id,
                        "card_id" => $card_id,
                        "price"   => $price
                    ];

                    $is_true = $dishesCardPriceModel
                        ->insert($cardPriceParams);
                    if ($is_true === false){
                        return $this->com_return(false,config("params.FAIL"));
                    }
                }
            }
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        }catch (Exception $e){
            Db::rollback();
            return $this->com_return(false,$e->getMessage());
        }
    }

    /**
     * 菜品套餐编辑提交
     * @param Request $request
     * @return array
     */
    public function combEdit(Request $request)
    {
        $dis_id       = $request->param("dis_id","");//菜品id
        $dishes_combo = $request->param("dishes_combo","");//菜品套餐信息
        $rule = [
            "dis_id|菜品id"            => "require",
            "dishes_combo|菜品套餐信息" => "require",
        ];
        $request_res = [
            "dis_id"       => $dis_id,
            "dishes_combo" => $dishes_combo,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        $dishes_combo = json_decode($dishes_combo,true);

        Db::startTrans();
        try{
            $delete_old = Db::name("dishes_combo")
                ->where("main_dis_id",$dis_id)
                ->delete();
            if (!$delete_old){
                return $this->com_return(false,config("params.ABNORMAL_ACTION"));
            }
            for ($i = 0; $i < count($dishes_combo); $i ++){
                $type              = $dishes_combo[$i]['type'];
                $combo_dish_id     = (int)$dishes_combo[$i]['dis_id'];
                $type_desc         = $dishes_combo[$i]['type_desc'];
                $quantity          = $dishes_combo[$i]['quantity'];
                $dish_little_group = $dishes_combo[$i]['children'];

                //将数据写入套餐表
                $comboDishParams = [
                    "main_dis_id" => $dis_id,
                    "dis_id"      => $combo_dish_id,
                    "type"        => $type,
                    "type_desc"   => $type_desc,
                    "quantity"    => $quantity
                ];

                $combo_little_id = Db::name("dishes_combo")
                    ->insertGetId($comboDishParams);

                if ($combo_little_id === false){
                    return $this->com_return(false,config("params.FAIL"));
                }

                if ($type){
                    if (empty($dish_little_group)){
                        return $this->com_return(false,config("params.DISHES")['COMBO_ID_NOT_EMPTY']);
                    }
                    for ($m = 0; $m < count($dish_little_group); $m ++){
                        $little_dish_id = $dish_little_group[$m]['dis_id'];
                        $little_quantity = $dish_little_group[$m]['quantity'];
                        $littleDishParams = [
                            "main_dis_id" => $dis_id,
                            "dis_id"      => $little_dish_id,
                            "parent_id"   => $combo_little_id,
                            "quantity"    => $little_quantity
                        ];

                        $is_ok = Db::name("dishes_combo")
                            ->insertGetId($littleDishParams);
                        if ($is_ok === false){
                            return $this->com_return(false,config("params.FAIL"));
                        }
                    }
                }
            }

            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        }catch (Exception $e){
            Db::rollback();
            return $this->com_return(false,$e->getMessage());
        }
    }

    /**
     * 菜品删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        $dis_ids = $request->param("dis_id","");//菜品id
        $rule = [
            "dis_id|菜品id"      => "require",
        ];
        $check_res = [
            "dis_id" => $dis_ids,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }
        $params = [
            "is_delete"  => 1,
            "updated_at" => time()
        ];

        Db::startTrans();
        try{
            $id_array = explode(",",$dis_ids);
            $dishModel = new Dishes();
            foreach ($id_array as $dis_id){
                $is_ok = $dishModel
                    ->where("dis_id",$dis_id)
                    ->update($params);
                if ($is_ok === false){
                    return $this->com_return(false,config("params.FAIL"));
                }
            }

            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        }catch (Exception $e){
            Db::rollback();
            return $this->com_return(false,$e->getMessage());
        }
    }

    /**
     * 菜品排序
     * @param Request $request
     * @return array
     */
    public function sortEdit(Request $request)
    {
        $dis_id = $request->param("dis_id","");//菜品id
        $sort   = $request->param("sort","");//排序

        $rule = [
            "dis_id|菜品id"  => "require",
            "sort|排序"      => "require|number",
        ];

        $check_res = [
            "dis_id"  => $dis_id,
            "sort"    => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "sort"       => $sort,
            "updated_at" => time()
        ];

        try {
            $dishModel = new Dishes();

            $is_ok = $dishModel
                ->where("dis_id",$dis_id)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品是否启用
     * @param Request $request
     * @return array
     */
    public function enable(Request $request)
    {
        $dis_id    = $request->param("dis_id","");//菜品id
        $is_enable = $request->param("is_enable","");//是否启用

        $rule = [
            "dis_id|菜品id"     => "require",
            "is_enable|是否启用" => "require|number",
        ];
        $check_res = [
            "dis_id"    => $dis_id,
            "is_enable" => $is_enable,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "is_enable"  => $is_enable,
            "updated_at" => time()
        ];

        try {
            $dishModel = new Dishes();

            $is_ok = $dishModel
                ->where("dis_id",$dis_id)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}