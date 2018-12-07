<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/27
 * Time: 下午2:53
 */

namespace app\admin\controller\dishes;


use app\common\controller\AdminAuthAction;
use app\common\model\DishesAttribute;
use think\Db;
use think\Exception;
use think\Request;

class DishAttribute extends AdminAuthAction
{
    /**
     * 菜品属性列表 无分页
     * @return array
     */
    public function dishAttr()
    {
        try {
            $dishAttributeModel = new DishesAttribute();
            $list = $dishAttributeModel
                ->where("is_delete","0")
                ->order("sort")
                ->select();
            $list = json_decode(json_encode($list),true);

            for ($i = 0; $i < count($list); $i ++){
                $att_id = $list[$i]["att_id"];
                $printer_info = Db::name("dishes_attribute_printer")
                    ->where("att_id",$att_id)
                    ->select();
                $printer_info = json_decode(json_encode($printer_info),true);
                $list[$i]["printer_info"] = $printer_info;
            }

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品属性列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $pagesize   = $request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage    = $request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');
        $config = [
            "page" => $nowPage,
        ];

        try {
            $dishAttributeModel = new DishesAttribute();
            $list = $dishAttributeModel
                ->where("is_delete","0")
                ->order("sort")
                ->paginate($pagesize,false,$config);
            $list = json_decode(json_encode($list),true);

            for ($i = 0; $i < count($list["data"]); $i ++){
                $att_id = $list["data"][$i]["att_id"];
                $printer_info = Db::name("dishes_attribute_printer")
                    ->where("att_id",$att_id)
                    ->select();
                $printer_info = json_decode(json_encode($printer_info),true);
                $list["data"][$i]["printer_info"] = $printer_info;
            }

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}