<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午5:08
 */

namespace app\xcx_member\controller\personal;


use app\common\controller\MemberAuthAction;
use app\common\model\MstMerchant;
use app\common\model\MstMerchantCategory;
use think\Exception;
use think\Request;

class MerchantAction extends MemberAuthAction
{
    /**
     * 分类列表 ———— 键值对（小程序）
     * @return array
     */
    public function cateList()
    {
        try {
            $merchantCategoryModel = new MstMerchantCategory();
            $res = $merchantCategoryModel
                ->where('is_delete',0)
                ->field("cat_id,cat_name")
                ->select();
            $res = json_decode(json_encode($res),true);
            $list = [];
            foreach ($res as $key => $val){
                foreach ($val as $k => $v){
                    if ($k == "cat_id"){
                        $k = "key";
                    }else{
                        $k = "name";
                    }
                    $list[$key][$k] = $v;
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 联盟商家列表（小程序）
     * @param Request $request
     * @return array
     */
    public function merchatList(Request $request)
    {
        $pagesize   = $request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage    = $request->param("nowPage","1");
        $cat_id     = $request->param("cat_id","");//联盟商家分类id
        if (empty($pagesize)) $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];
        $cat_where = [];
        if (!empty($cat_id)){
            $cat_where['m.cat_id'] = ['eq',$cat_id];
        }

        try {
            $merchantModel = new MstMerchant();
            $column = $merchantModel->column;
            foreach ($column as $key => $val){
                $column[$key] = "m.".$val;
            }

            $list = $merchantModel
                ->alias("m")
                ->join("mst_merchant_category mc","mc.cat_id = m.cat_id")
                ->where($cat_where)
                ->order("m.sort")
                ->field($column)
                ->field("mc.cat_name")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}