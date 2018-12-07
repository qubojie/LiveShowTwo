<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午11:40
 */

namespace app\admin\controller\table;


use app\common\controller\AdminAuthAction;
use app\common\controller\SalesUserCommon;
use app\common\model\MstCardVip;
use app\common\model\MstTable;
use app\common\model\MstTableArea;
use think\Exception;
use think\Request;
use think\Validate;

class TableArea extends AdminAuthAction
{
    /**
     * 区域列表
     * @return array
     */
    public function index()
    {
        $location_id    = $this->request->param('location_id','');
        $pagesize       = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage        = $this->request->param("nowPage","1");
        if (empty($pagesize)) $pagesize = config('page_size');

        $location_where = [];
        if (!empty($location_id)){
            $location_where['ta.location_id'] = ['eq',$location_id];
        }
        try {
            $tableAreaModel = new MstTableArea();
            $column = $tableAreaModel->column;
            foreach ($column as $k => $v){
                $column[$k] = "ta.".$v;
            }
            $config = [
                "page" => $nowPage,
            ];
            $list = $tableAreaModel
                ->alias('ta')
                ->join("mst_table_location tl","tl.location_id = ta.location_id")
                ->join("manage_salesman ms","ms.sid = ta.sid","LEFT")
                ->where('ta.is_delete',0)
                ->where($location_where)
                ->field("tl.location_id,tl.location_title")
                ->field($column)
                ->field("ms.sid,ms.sales_name area_manager")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取区域服务负责人列表
     * @return array
     */
    public function getGovernorSalesman()
    {
        try {
            $salesUserCommonObj = new SalesUserCommon();

            $res = $salesUserCommonObj->getGovernorSalesman("1");

            $list = [];
            if (!empty($res)){

                for ($i = 0; $i < count($res); $i++){
                    $list[$i]['key']  = $res[$i]['sid'];
                    $list[$i]['name'] = $res[$i]['sales_name'];
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取所有的有效卡种
     * @return array
     */
    public function getCardInfo()
    {
        try {
            $cardModel = new MstCardVip();
            $cardInfo = $cardModel
                ->where('is_enable',1)
                ->where('is_delete',0)
                ->order('sort')
                ->field('card_id,card_name')
                ->select();
            $list = json_decode(json_encode($cardInfo),true);
            $cardInfo = [];
            foreach ($list as $key => $val){
                foreach ($val as $k => $v){
                    if ($k == "card_id"){
                        $k = "key";
                    }else{
                        $k = "name";
                    }
                    $cardInfo[$key][$k] = $v;
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 区域添加
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        $location_id       = $request->param('location_id','');//位置id
        $area_title        = $request->param('area_title','');
        $area_desc         = $request->param('area_desc','');
        $turnover_limit_l1 = $request->param('turnover_limit_l1','');//平日最低消费 0表示无最低消费（保留）
        $turnover_limit_l2 = $request->param('turnover_limit_l2','');//周末最低消费 0表示无最低消费（保留）
        $turnover_limit_l3 = $request->param('turnover_limit_l3','');//假日最低消费 0表示无最低消费（保留）
        $sort              = $request->param('sort','100');//排序
        $is_enable         = $request->param('is_enable',0);//是否启用预定  0否 1是
        $sid               = $request->param("sid","");//服务负责人id

        if (empty($sort)) $sort = 100;

        $rule = [
            "location_id|位置id"            => "require",
            "area_title|区域名称"            => "require|max:30",
            "area_desc|区域描述"             => "max:200",
            "turnover_limit_l1|平日最低消费" => "require",
            "turnover_limit_l2|周末最低消费" => "require",
            "turnover_limit_l3|假日最低消费" => "require",
            "sort|排序"                     => "number",
            "sid|服务负责人id"               => "require",
        ];
        $check_data = [
            "location_id"       => $location_id,
            "area_title"        => $area_title,
            "area_desc"         => $area_desc,
            "turnover_limit_l1" => $turnover_limit_l1,
            "turnover_limit_l2" => $turnover_limit_l2,
            "turnover_limit_l3" => $turnover_limit_l3,
            "sort"              => $sort,
            "sid"               => $sid,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }
        $insert_data = [
            'location_id'       => $location_id,
            'area_title'        => $area_title,
            'area_desc'         => $area_desc,
            "turnover_limit_l1" => $turnover_limit_l1,
            "turnover_limit_l2" => $turnover_limit_l2,
            "turnover_limit_l3" => $turnover_limit_l3,
            'sort'              => $sort,
            'sid'               => $sid,
            'is_enable'         => $is_enable,
            'created_at'        => time(),
            'updated_at'        => time()
        ];

        try {
            $tableAreaModel = new MstTableArea();
            //查询当前位置下是否存在该区域
            $is_exist = $tableAreaModel
                ->where('location_id',$location_id)
                ->where("area_title",$area_title)
                ->count();
            if ($is_exist > 0){
                return $this->com_return(false,config("params.AREA_IS_EXIST"));
            }
            $is_ok = $tableAreaModel
                ->insert($insert_data);
            if ($is_ok){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 区域编辑
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        $area_id           = $request->param('area_id','');
        $location_id       = $request->param('location_id','');//位置id
        $area_title        = $request->param('area_title','');
        $area_desc         = $request->param('area_desc','');
        $turnover_limit_l1 = $request->param('turnover_limit_l1','');//平日最低消费 0表示无最低消费（保留）
        $turnover_limit_l2 = $request->param('turnover_limit_l2','');//周末最低消费 0表示无最低消费（保留）
        $turnover_limit_l3 = $request->param('turnover_limit_l3','');//假日最低消费 0表示无最低消费（保留）
        $sort              = $request->param('sort','100');//排序
        $is_enable         = $request->param('is_enable',0);//是否启用预定  0否 1是
        $sid               = $request->param("sid","");//服务负责人id

        if (empty($sort)) $sort = 100;

        $rule = [
            "area_id|区域id"                => "require",
            "location_id|位置id"            => "require",
            "area_title|区域名称"            => "require|max:30",
            "area_desc|区域描述"             => "max:200",
            "turnover_limit_l1|平日最低消费" => "require",
            "turnover_limit_l2|周末最低消费" => "require",
            "turnover_limit_l3|假日最低消费" => "require",
            "sort|排序"                     => "number",
            "sid|服务负责人id"               => "require",
        ];
        $check_data = [
            "area_id"           => $area_id,
            "location_id"       => $location_id,
            "area_title"        => $area_title,
            "area_desc"         => $area_desc,
            "turnover_limit_l1" => $turnover_limit_l1,
            "turnover_limit_l2" => $turnover_limit_l2,
            "turnover_limit_l3" => $turnover_limit_l3,
            "sort"              => $sort,
            "sid"               => $sid,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $update_data = [
            "location_id"       => $location_id,
            'area_title'        => $area_title,
            'area_desc'         => $area_desc,
            "turnover_limit_l1" => $turnover_limit_l1,
            "turnover_limit_l2" => $turnover_limit_l2,
            "turnover_limit_l3" => $turnover_limit_l3,
            'sort'              => $sort,
            'sid'               => $sid,
            'is_enable'         => $is_enable,
            'updated_at'        => time()
        ];

        try {
            $tableAreaModel = new MstTableArea();

            $is_ok = $tableAreaModel
                ->where('area_id',$area_id)
                ->update($update_data);

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
     * 区域删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        $area_id        = $request->param('area_id','');
        $delete_data = [
            'is_delete'  => 1,
            'updated_at' => time()
        ];

        try {
            //首先查询当前区域下是否有吧台
            $tableModel = new MstTable();
            $is_exist = $tableModel
                ->where('area_id',$area_id)
                ->where("is_delete",0)
                ->count();
            if ($is_exist > 0){
                return $this->com_return(false,config("params.AREA_TALE_EXIST"));
            }

            $tableAreaModel = new MstTableArea();
            $is_ok = $tableAreaModel
                ->where('area_id',$area_id)
                ->update($delete_data);

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
     * 是否启用
     * @param Request $request
     * @return array
     */
    public function enable(Request $request)
    {
        $is_enable = (int)$request->param("is_enable","");
        $area_id   = $request->param("area_id","");

        $rule = [
            "area_id|区域id"         => "require",
        ];
        $check_data = [
            "area_id"        => $area_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $update_data = [
            'is_enable'  => $is_enable,
            'updated_at' => time()
        ];

        try {
            $tableAreaModel = new MstTableArea();

            $is_ok = $tableAreaModel
                ->where('area_id',$area_id)
                ->update($update_data);

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