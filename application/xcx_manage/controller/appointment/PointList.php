<?php
/**
 * Created by 棒哥的IDE.
 * Email QuBoJie@163.com
 * QQ 3106954445
 * WeChat 17703981213
 * User: QuBoJie
 * Date: 2018/12/6
 * Time: 下午4:22
 * App: LiveShowTwo
 */

namespace app\xcx_manage\controller\appointment;


use app\common\controller\BaseController;
use app\common\controller\ManageAuthAction;
use app\common\controller\PointListCommon;
use app\common\model\TableBusiness;
use think\Exception;
use think\Validate;

//class PointList extends ManageAuthAction
class PointList extends BaseController
{
    /**
     * 扫码确认用户身份
     */
    public function qrCodeGetUserIdentity()
    {
        $table_id = $this->request->param("table_id","");//桌id
        $pointListCommonObj = new PointListCommon();
        return $pointListCommonObj->qrCodeGetUserId($table_id);
    }


    /**
     * 获取可点单台位列表
     * @return array
     */
    public function selectionOpenTableList()
    {
        $location_id = $this->request->param("location_id","");
        $rule = [
            "location_id|大区" => "require",
        ];
        $check_res = [
            "location_id" => $location_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }
        try {
            $date_time     = 1;//今天
            $date_time_arr = $this->getSysTimeLong($date_time);
            $beginTime     = $date_time_arr['beginTime'];
            $endTime       = $date_time_arr['endTime'];

            $tableBusinessModel = new TableBusiness();
            $res = $tableBusinessModel
                ->alias("tb")
                ->join("mst_table t","t.table_id = tb.table_id","LEFT")
                ->join("mst_table_area ta","ta.area_id = t.area_id","LEFT")
                ->join("mst_table_location tl","tl.location_id = ta.location_id","LEFT")
                ->where('tl.location_id',$location_id)
                ->where('tb.status',config('order.table_business_status')['open']['key'])
                ->whereTime('tb.open_time','between',[$beginTime,$endTime])
                ->group('tb.table_id')
                ->field('t.table_id,t.table_no')
                ->select();
            $res = json_decode(json_encode($res),true);

            if (!empty($res)) {
                for ($i = 0; $i < count($res); $i ++) {
                    $table_id = $res[$i]['table_id'];
                    $children =  $tableBusinessModel
                        ->alias("tb")
                        ->where('tb.table_id',$table_id)
                        ->join('user u','u.uid = tb.uid','LEFT')
                        ->where('tb.status',config('order.table_business_status')['open']['key'])
                        ->whereTime('tb.open_time','between',[$beginTime,$endTime])
                        ->field('u.name,u.phone')
                        ->field('tb.buid,tb.table_no,tb.ssname sales_name')
                        ->select();
                    $res[$i]['children'] = $children;
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

}