<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 上午11:25
 */

namespace app\common\model;


use think\Model;

class JobAccount extends Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_job_account';

    protected $primaryKey = 'uid';

    public $timestamps = false;
}