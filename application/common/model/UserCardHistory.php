<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 上午10:49
 */

namespace app\common\model;


use think\Model;

class UserCardHistory extends Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_user_card_history';

    protected $primaryKey = 'uid';

    public $timestamps = false;
}