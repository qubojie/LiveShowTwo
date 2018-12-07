<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午4:39
 */

namespace app\common\model;


use think\Model;

class BcUserInfo extends Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_user_info';

    protected $primaryKey = 'uid';

    public $timestamps = false;
}