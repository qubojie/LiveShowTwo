<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午5:19
 */

namespace app\common\model;


use think\Model;

class JobUser extends Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_job_user';

    protected $primaryKey = 'uid';

    public $timestamps = false;
}