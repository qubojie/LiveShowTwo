<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午5:20
 */
namespace app\xcx_member\controller\main;

use app\common\controller\BaseController;
use think\Exception;
use think\Validate;

class GetSettingInfo extends BaseController
{
    /**
     * 获取需要的后台设置的系统信息
     * @return array
     */
    public function getSettingInfo()
    {
        $keys = $this->request->param('key',"");
        $rule = [
            "key" => "require"
        ];
        $request_res = [
            "key" => $keys
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $key_array = explode(",",$keys);
            $res = array();
            foreach ($key_array as $key => $value){
                $key = $value;
                $values = $this->getSysSettingInfo($key);
                $res[$value] = $values;
            }
            return $this->com_return(true,config("SUCCESS"),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}