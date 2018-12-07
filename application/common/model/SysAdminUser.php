<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午5:41
 */
namespace app\common\model;

use think\Model;

class SysAdminUser extends Model
{
    protected $table = 'el_sys_admin_user';

    protected $fillable = ['user_name', 'password'];

    protected $hidden = ['password', 'remember_token'];

    public $column = [
        "id",
        "user_sn",
        "user_name",
        "ec_salt",
        "avatar",
        "phone",
        "email",
        "last_ip",
        "action_list",
        "nav_list",
        "lang_type",
        "role_id",
        "is_delete",
        "is_sys",
        "created_at",
        "updated_at",
    ];
}