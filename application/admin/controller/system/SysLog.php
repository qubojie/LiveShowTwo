<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午2:29
 */
namespace app\admin\controller\system;

use app\common\controller\AdminAuthAction;
use think\Exception;

class SysLog extends AdminAuthAction
{

    /**
     * 系统日志列表
     * @return array
     */
    public function sysLogList()
    {
        $pagesize   = $this->request->param("pagesize",config('page_size'));//当前页,不传时为10
        $nowPage    = $this->request->param("nowPage","1");

        if (empty($pagesize))  $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];

        try {
            $sysLogModel = new \app\common\model\SysLog();

            $res = $sysLogModel
                ->order("log_time DESC")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config('params.SUCCESS'),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }
}