<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午5:58
 */

namespace app\admin\controller\member;


use app\common\controller\AdminAuthAction;
use app\common\model\MstUserLevel;
use app\common\model\User;
use think\Db;
use think\Exception;
use think\Validate;

class UserLevel extends AdminAuthAction
{
    /**
     * 会员等级列表
     * @return array
     */
    public function index()
    {
        $pagesize = $this->request->param("pagesize",config('page_size'));//当前页,不传时为10
        $nowPage  = $this->request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];

        try {
            $mstUserLevelModel = new MstUserLevel();

            $res = $mstUserLevelModel
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("GET_SUCCESS"),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会员等级添加
     * @return array
     */
    public function add()
    {
        $level_name = $this->request->param("level_name",""); //等级名称
        $level_desc = $this->request->param("level_desc",""); //等级描述
        $sort       = $this->request->param("sort","100");    //等级排序
        $point_min  = $this->request->param("point_min","");  //等级积分最小值
        $point_max  = $this->request->param("point_max","");  //等级积分最大值

        $rule = [
            "level_name|等级名称"     => "require|max:20|unique:mst_user_level",
            "point_min|等级积分最小值" => "require|number",
            "point_max|等级积分最大值" => "require|number",
        ];

        $request_res = [
            "level_name" => $level_name,
            "point_min"  => $point_min,
            "point_max"  => $point_max
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        if ($point_max < $point_min){
            return $this->com_return(false,config("params.POINT_POST_RETURN"));
        }

        $insert_data = [
            "level_name" => $level_name,
            "level_desc" => $level_desc,
            "point_min"  => $point_min,
            "point_max"  => $point_max,
            "sort"       => $sort,
            "created_at" => time(),
            "updated_at" => time()
        ];

        try {
            $mstUserLevelModel = new MstUserLevel();
            $res = $mstUserLevelModel
                ->insert($insert_data);

            if ($res !== false) {
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会员等级编辑
     * @return array
     */
    public function edit()
    {
        $level_id   = $this->request->param("level_id",""); //等级id
        $level_name = $this->request->param("level_name",""); //等级名称
        $level_desc = $this->request->param("level_desc",""); //等级描述
        $sort       = $this->request->param("sort","100");    //等级排序
        $point_min  = $this->request->param("point_min","");  //等级积分最小值
        $point_max  = $this->request->param("point_max","");  //等级积分最大值

        $rule = [
            "level_id|等级id"         => "require",
            "level_name|等级名称"     => "require|max:20|unique:mst_user_level",
            "point_min|等级积分最小值" => "require|number",
            "point_max|等级积分最大值" => "require|number",
        ];

        $request_res = [
            "level_id"   => $level_id,
            "level_name" => $level_name,
            "point_min"  => $point_min,
            "point_max"  => $point_max,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        if ($point_max < $point_min){
            return $this->com_return(false,config("params.POINT_POST_RETURN"));
        }

        $update_data = [
            "level_name" => $level_name,
            "level_desc" => $level_desc,
            "point_min"  => $point_min,
            "point_max"  => $point_max,
            "sort"       => $sort,
            "updated_at" => time()
        ];

        try {
            $mstUserLevelModel = new MstUserLevel();

            $res = $mstUserLevelModel
                ->where("level_id",$level_id)
                ->update($update_data);

            if ($res !== false) {
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会员等级删除
     * @return array
     */
    public function delete()
    {
        $level_ids = $this->request->param("level_id","");

        $rule = [
            "level_id|等级id"         => "require",
        ];

        $request_res = [
            "level_id"   => $level_ids,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        Db::startTrans();
        try {
            $id_array = explode(",",$level_ids);

            $userModel = new User();

            foreach ($id_array as $id_l){
                $is_exist = $userModel
                    ->where("level_id",$id_l)
                    ->count();
                if ($is_exist > 0){
                    return $this->com_return(false,config("params.LEVEL_USER_EXIST"));
                }
            }

            $mstUserLevelModel = new MstUserLevel();

            foreach ($id_array as $level_id){
                $res = $mstUserLevelModel
                    ->where("level_id",$level_id)
                    ->delete();

                if ($res === false) {
                    return $this->com_return(false, config("params.FAIL"));
                }
            }
            Db::commit();
            return $this->com_return(true, config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }
}