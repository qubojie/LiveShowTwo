<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午3:15
 */

namespace app\admin\controller\material;


use app\common\controller\AdminAuthAction;
use app\common\controller\QiNiuUpload;
use app\common\model\ResourceFile;
use think\Db;
use think\Env;
use think\Exception;
use think\Request;
use think\Validate;

class SourceMaterial extends AdminAuthAction
{
    /**
     * 素材列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $type     = $request->param('type', '');//类型 0 图片 ;  1 视频
        $cat_id   = $request->param('cat_id', '');//分类id
        $pagesize = $request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $rule = [
            "type|类型"     => "require",
            "cat_id|分类id" => "require",
        ];
        $check_data = [
            "type"    => $type,
            "cat_id"  => $cat_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $cat_where['cat_id'] = ['eq',$cat_id];

        $where['type'] = $type;

        $config = [
            "page" => $nowPage,
        ];

        try {
            $resourceFileModel = new ResourceFile();

            $res = $resourceFileModel
                ->where($where)
                ->where($cat_where)
                ->order('sort')
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 素材上传
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function upload(Request $request)
    {
        $type   = $request->param('type', '');
        $cat_id = $request->param('cat_id', '');
        $sort   = $request->param('sort', '500');

        $prefix = 'source';
        $genre  = 'file';//上传的文件容器参数名称
        if (empty($sort))   $sort   = '500';
        //验证
        $rule = [
            "type|素材类型"    => "require",
            "cat_id|分类id"   => "number",
            "sort|排序"       => "number",
            "prefix|前缀"     => "require",
        ];
        $check_data = [
            "type"   => $type,
            "cat_id" => $cat_id,
            "sort"   => $sort,
            "prefix" => $prefix,
        ];

        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $qiNiuUpload = new \app\common\controller\QiNiuUpload();
            $upload = $qiNiuUpload->upload("$genre","$prefix",$type);

            if (isset($upload['result']) && !$upload['result']){
                return $this->com_return(false, $upload['message']);
            }

            $link           = 'http://' . $upload['data']['url'] . '/' . $upload['data']['key'];
            $file_size      = $upload['data']['size'];
            $file_extension = $upload['data']['extension'];

            $params = [
                'cat_id'         => $cat_id,
                'type'           => $type,
                'link'           => $link,
                'file_size'      => $file_size,
                'file_extension' => $file_extension,
                'sort'           => $sort,
                'created_at'     => time(),
                'updated_at'     => time(),
            ];

            $resourceFileModel = new ResourceFile();

            $result = $resourceFileModel
                ->insert($params);

            if ($result !== false){
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 素材删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        $ids = $request->param('id', '');
        //验证
        $rule = [
            "id|素材id" => "require",
        ];
        $check_data = [
            "id" => $ids,
        ];

        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }
        Db::startTrans();
        try {
            $ids = explode(",",$ids);
            $resourceFileModel = new ResourceFile();
            foreach ($ids as $id){
                $res = $resourceFileModel
                    ->where('id', $id)
                    ->delete();
                if ($res === false){
                    return $this->com_return(false,config("params.FAIL"));
                }
            }
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 移动素材至新的分组
     * @param Request $request
     * @return array
     */
    public function moveMaterial(Request $request)
    {
        $type   = $request->param("type","");//素材类型
        $ids    = $request->param("id","");//素材id 多个以逗号隔开
        $cat_id = $request->param("cat_id","");//移动至的新分类id

        $rule = [
            "type|素材类型"   => "require",
            "id|素材id"      => "require",
            "cat_id|分类id"  => "require",
        ];
        $check_data = [
            "type"    => $type,
            "id"      => $ids,
            "cat_id"  => $cat_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $where['type'] = ['eq',$type];
        Db::startTrans();
        try {
            $ids = explode(",",$ids);//将素材id 以逗号分割为数组
            $resourceFile  = new ResourceFile();
            foreach ($ids as $id) {
                $where['id'] = ['eq',$id];
                $params = [
                    "cat_id"     => $cat_id,
                    "type"       => $type,
                    "updated_at" => time()
                ];
                $move_res = $resourceFile
                    ->where($where)
                    ->update($params);
                if ($move_res == false){
                    return $this->com_return(false,config("params.TEMP")['MOVE_FAIL']);
                }
            }
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 前端获取上传token
     */
    public function webGetQiNiuToken()
    {
        try {
            $qiNiuUploadObj = new QiNiuUpload();
            $res =$qiNiuUploadObj->getUploadToken();
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 图片存入素材库
     * @return array
     */
    public function uploadImageUrl()
    {
        $file        = $this->request->param('file', '500');
        $size        = $this->request->param('size', '500');//文件大小
        $extension   = $this->request->param('extension', '');//文件扩展名
        $type        = $this->request->param('type', '');
        $cat_id      = $this->request->param('cat_id', '');
        $sort        = $this->request->param('sort', '500');
//        $prefix      = 'source/';
        if (empty($sort))   $sort   = '500';
        //验证
        $rule = [
            "file|文件"       => "require",
            "size|大小"       => "require",
            "extension|扩展名"=> "require",
            "type|素材类型"    => "require",
            "cat_id|分类id"   => "number",
            "sort|排序"       => "number",

        ];
        $check_data = [
            "file"      => $file,
            "size"      => $size,
            "extension" => $extension,
            "type"      => $type,
            "cat_id"    => $cat_id,
            "sort"      => $sort,
        ];

        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            //空间绑定的域名
            $domain = Env::get("QINIU_IMG_URL");
            $link   = 'http://' . $domain . '/' . $file;
            $params = [
                'cat_id'         => $cat_id,
                'type'           => $type,
                'link'           => $link,
                'file_size'      => $size,
                'file_extension' => $extension,
                'sort'           => $sort,
                'created_at'     => time(),
                'updated_at'     => time(),
            ];

            $resourceFileModel = new ResourceFile();
            $result = $resourceFileModel
                ->insert($params);

            if ($result !== false){
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}