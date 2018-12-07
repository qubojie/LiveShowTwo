<?php
/**
 * Created by 棒哥的IDE.
 * Email QuBoJie@163.com
 * QQ 3106954445
 * WeChat 17703981213
 * User: QuBoJie
 * Date: 2018/12/3
 * Time: 上午10:59
 * App: LiveShowTwo
 */

namespace app\common\controller;


use app\common\model\TableBusiness;

class ConsumptionCommon extends BaseController
{
    /**
     * buid获取开台信息
     * @param $buid
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function buidGetTableBusinessInfo($buid)
    {
        $tableBusinessModel = new TableBusiness();
        $res = $tableBusinessModel
            ->where("buid",$buid)
            ->find();
        $res = json_decode(json_encode($res),true);
        return $res;
    }
}