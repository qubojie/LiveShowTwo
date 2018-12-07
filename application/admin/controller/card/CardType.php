<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/26
 * Time: 下午3:43
 */

namespace app\admin\controller\card;


use app\common\controller\AdminAuthAction;
use app\common\controller\CardCommon;
use app\common\model\MstCardType;
use think\Exception;
use think\Validate;

class CardType extends AdminAuthAction
{
    /**
     * 获取卡种列表
     * @return array
     */
    public function index()
    {
        try {
            $cardTypeModel = new MstCardType();
            $res = $cardTypeModel
                ->where("is_delete",0)
                ->order("updated_at DESC")
                ->select();
            $res = json_decode(json_encode($res),true);
            return $this->com_return(true,config('params.SUCCESS'),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 卡种添加
     * @return array
     */
    public function add()
    {
        $type_name = $this->request->param("type_name","");//类型名称
        $is_enable = $this->request->param("is_enable","");//是否激活
        $rule = [
            'type_name|卡种名称'  =>  'require|unique:mst_card_type|max:100',  //卡片类型id
        ];
        $check_data = [
            "type_name"        => $type_name,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }
        if (empty($is_enable)) $is_enable = 0;

        $params = [
            "type_name"  => $type_name,
            "is_enable"  => $is_enable,
            "is_delete"  => 0,
            "created_at" => time(),
            "updated_at" => time()
        ];
        try {
            $cardTypeModel = new MstCardType();

            $res = $cardTypeModel
                ->insert($params);

            if ($res !== false) {
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 卡种编辑
     * @return array
     */
    public function edit()
    {
        $type_id   = $this->request->param("type_id","");//类型id
        $type_name = $this->request->param("type_name","");//类型名称
        $is_enable = $this->request->param("is_enable","");//是否激活
        $rule = [
            'type_id|卡种'       =>  'require',  //卡片类型id
            'type_name|卡种名称'  =>  'require|unique_me:mst_card_type,type_id|max:100',  //卡片类型id
            'is_enable|是否激活'  =>  'require',  //卡片类型id
        ];
        $check_data = [
            "type_id"    => $type_id,
            "type_name"  => $type_name,
            "is_enable"  => $is_enable,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "type_name"  => $type_name,
            "is_enable"  => $is_enable,
            "updated_at" => time()
        ];
        try {
            $cardTypeModel = new MstCardType();

            $res = $cardTypeModel
                ->where("type_id",$type_id)
                ->update($params);

            if ($res !== false) {
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 卡种删除
     * @return array
     */
    public function delete()
    {
        $type_id   = $this->request->param("type_id","");//类型id
        $rule = [
            'type_id|卡种'       =>  'require',  //卡片类型id
        ];
        $check_data = [
            "type_id"    => $type_id
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        /*查询当前卡种下是否存在有效卡片 On*/
        $cardCommonObj = new CardCommon();

        $is_exist= $cardCommonObj->cardTypeHaveCard($type_id);
        if ($is_exist){
            //如果存在
            return $this->com_return(false,config("params.CARD_EXIST_NOT_D"));
        }
        /*查询当前卡种下是否存在有效卡片 Off*/

        $params = [
            "is_enable"  => 0,
            "is_delete"  => 1,
            "updated_at" => time()
        ];
        try {
            $cardTypeModel = new MstCardType();

            $res = $cardTypeModel
                ->where("type_id",$type_id)
                ->update($params);

            if ($res !== false) {
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 卡种是否激活
     * @return array
     */
    public function enable()
    {
        $type_id   = $this->request->param("type_id","");//类型id
        $is_enable = $this->request->param("is_enable","");//是否激活
        $rule = [
            'type_id|卡种'       =>  'require',  //卡片类型id
            'is_enable|是否激活'  =>  'require',  //卡片类型id
        ];
        $check_data = [
            "type_id"    => $type_id,
            "is_enable"  => $is_enable,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "is_enable"  => $is_enable,
            "updated_at" => time()
        ];
        try {
            $cardTypeModel = new MstCardType();

            $res = $cardTypeModel
                ->where("type_id",$type_id)
                ->update($params);

            if ($res !== false) {
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

}