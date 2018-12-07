<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午12:16
 */

namespace app\common\controller;


use app\common\model\Dishes;
use app\common\model\DishesCardPrice;
use think\Exception;

class DishesCommon extends BaseController
{
    /**
     * 菜品添加主信息
     * @param $params
     * @param $dishes_card_price
     * @return array|bool|int|string
     */
    public function dishSingleAdd($params,$dishes_card_price)
    {
        $is_vip = $params['is_vip'];

        try {
            $dishModel = new Dishes();
            $dis_id = $dishModel
                ->insertGetId($params);
            if ($dis_id === false) {
                return false;
            }
            if ($is_vip){
                //如果在vip上架,则记录vip各卡价格
                if (empty($dishes_card_price)){
                    return false;
                }
                $dishes_card_price = json_decode($dishes_card_price,true);

                $dishesCardPriceModel = new DishesCardPrice();
                for ($i = 0; $i <count($dishes_card_price); $i ++){
                    $card_id = $dishes_card_price[$i]['card_id'];
                    $price   = $dishes_card_price[$i]['price'];
                    $cardPriceParams = [
                        "dis_id"  => $dis_id,
                        "card_id" => $card_id,
                        "price"   => $price
                    ];
                    $is_ok = $dishesCardPriceModel
                        ->insert($cardPriceParams);
                    if ($is_ok === false) {
                        return false;
                    }
                }
            }

            return $dis_id;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 查看当前分类下是否存在菜品
     * @param $cat_id
     * @return bool
     */
    public function classifyHaveDish($cat_id)
    {
        try {
            $dishesModel = new Dishes();
            $is_have_dish = $dishesModel
                ->where("cat_id",$cat_id)
                ->where("is_delete","0")
                ->count();
            if ($is_have_dish > 0){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 根据菜品id获取菜品信息
     * @param $dis_id
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function disIdGetDisInfo($dis_id)
    {
        $dishesModel = new Dishes();
        $dishesInfo = $dishesModel
            ->where('dis_id',$dis_id)
            ->field("dis_id,dis_type,dis_sn,dis_name,dis_img,dis_desc,cat_id,att_id,is_normal,normal_price,is_gift,gift_price,is_vip,is_give")
            ->find();
        $dishesInfo = json_decode(json_encode($dishesInfo),true);
        return $dishesInfo;
    }
}