<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/27
 * Time: 下午2:37
 */

namespace app\common\controller;


use app\common\model\Dishes;
use app\common\model\DishesCardPrice;
use app\common\model\DishesCategory;
use think\Db;

class GoodsCommon extends BaseController
{
    /**
     * 菜品分类获取public
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function dishTypePublic()
    {
        $dishCateGateModel = new DishesCategory();
        $res = $dishCateGateModel
            ->where("is_enable",1)
            ->where("is_delete",0)
            ->order("sort")
            ->field("cat_id,cat_name,cat_img")
            ->select();
        $res = json_decode(json_encode($res),true);
        return $res;
    }

    /**
     * 菜品id获取菜品信息
     * @param $dis_id
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function disIdGetDishesInfo($dis_id)
    {
        $dishesModel = new Dishes();

        $res = $dishesModel
            ->alias("d")
            ->where("dis_id",$dis_id)
            ->where("d.is_enable",1)
            ->where("d.is_delete",0)
            ->find();
        $res = json_decode(json_encode($res),true);

        return $res;
    }

    /**
     * 菜品id获取菜品价格
     * @param $dis_id
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function disIdGetPrice($dis_id)
    {
        $dishesCardPriceModel = new DishesCardPrice();
        $res = $dishesCardPriceModel
            ->alias("dcp")
            ->join("mst_card_vip mcv","mcv.card_id = dcp.card_id")
            ->where("dcp.dis_id",$dis_id)
            ->field("mcv.card_name")
            ->field("dcp.dis_id,dcp.card_id,dcp.price")
            ->select();

        $res = json_decode(json_encode($res),true);

        return $res;
    }

    /**
     * 菜品id获取套餐信息
     * @param $dis_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function disIdGetComboInfo($dis_id)
    {
        $res = Db::name("dishes_combo")
            ->alias("dc")
            ->join("dishes d","d.dis_id = dc.dis_id","LEFT")
            ->where("dc.main_dis_id",$dis_id)
            ->field("d.dis_name,d.dis_img")
            ->field("dc.combo_id,dc.dis_id,dc.type,dc.type_desc,dc.parent_id,dc.quantity")
            ->select();
        $res = json_decode(json_encode($res),true);
        return $res;
    }
}