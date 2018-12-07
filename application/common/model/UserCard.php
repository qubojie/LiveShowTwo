<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午4:58
 */

namespace app\common\model;


use think\Model;

class UserCard extends Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_user_card';

    protected $primaryKey = 'uid';

    public $timestamps = false;
}