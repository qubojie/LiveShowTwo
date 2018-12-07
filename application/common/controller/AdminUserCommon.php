<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午4:57
 */
namespace app\common\controller;

use app\common\model\MstCardVip;
use app\common\model\UserCard;
use think\Controller;

class AdminUserCommon extends Controller
{
    /**
     * 获取各种卡开卡统计
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOpenCardCount()
    {
        /*获取卡 On*/
        $cardVipModel = new MstCardVip();

        $cardType = $cardVipModel
            ->field("card_id,card_name")
            ->select();

        $cardType = json_decode(json_encode($cardType),true);

        $userCardModel = new UserCard();

        for ($i = 0; $i < count($cardType); $i ++) {
            $card_id = $cardType[$i]['card_id'];
            $num = $userCardModel
                ->where("card_id",$card_id)
                ->count();
            $cardType[$i]['user_num'] = $num;
        }
        /*获取卡 Off*/
        return $cardType;
    }
}