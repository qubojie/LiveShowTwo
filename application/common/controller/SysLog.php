<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午5:21
 */
namespace app\common\controller;

use think\Controller;
use think\Db;

class SysLog extends Controller
{
    /**
     * 用户操作日志列表
     * @param $type
     * @param $val
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function log_list($type,$val)
    {
        $res = Db::name('sys_adminaction_log')
            ->where($type,$val)
            ->select();
        return $res;
    }


}