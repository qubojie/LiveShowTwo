<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午2:38
 */
namespace app\admin\controller\content;

use app\common\controller\AdminAuthAction;
use app\common\model\PageArticles;
use think\Db;
use think\Exception;
use think\Validate;

class HomeArticles extends AdminAuthAction
{
    /**
     * 文章列表
     * @return array
     */
    public function index()
    {
        $sort     = $this->request->param("sort","desc");//排序方式
        $orderBy  = $this->request->param("orderBy","created_at");//排序依据
        $keyword  = $this->request->param("keyword","");//关键字搜索
        $nowPage  = $this->request->param("nowPage","1");//当前页,不传时为1
        $pagesize = $this->request->param("pagesize",config('page_size'));//页面大小

        $order = "is_top desc".",".$orderBy." ".$sort; //排序方式

        $where = [];
        if (!empty($keyword)){
            $where['article_title'] = ['like', "%$keyword%"];;
        }

        $config = [
            "page" => $nowPage
        ];
        try {
            $pageArticleModel = new PageArticles();

            $res = $pageArticleModel
                ->order($order)
                ->where($where)
                ->paginate($pagesize,false,$config);

            $res = json_decode(json_encode($res),true);

            $data = $res['data'];

            $res['data'] = arrIntToString($data);

            return $this->com_return(true,config('params.SUCCESS'),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 添加文章
     * @return array
     */
    public function add()
    {
        $article_title = $this->request->param("article_title","");   //文章标题
        $article_image = $this->request->param("article_image","http://ceshiurl.com");   //文章图片
        $type          = $this->request->param("type",0);            //文章类型 0 链接; 1 内容
        $link          = $this->request->param("link","");            //文章链接
        $content       = $this->request->param("content","");            //文章链接
        $sort          = $this->request->param("sort","100");         //文章排序
        $is_show       = $this->request->param("is_show",0);         //是否显示 0:false;1:true
        $is_top        = $this->request->param("is_top",0);           //是否置顶 0:false;1:true
        $rule = [
            "article_title|文章标题"  => "require|max:50|unique:page_article",
            "article_image|文章图片"  => "require",
            "sort|文章排序"           => "require|number",
            "is_show|是否显示"        => "require",
            "is_top|是否置顶"         => "require",
        ];

        $request_res = [
            "article_title" => $article_title,
            "article_image" => $article_image,
            "sort"          => $sort,
            "is_show"       => $is_show,
            "is_top"       => $is_top,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        if (empty($type)) $type = 0;

        $update_data = [
            "article_title" => $article_title,
            "article_image" => $article_image,
            "type"          => $type,
            "link"          => $link,
            "content"       => $content,
            "sort"          => $sort,
            "is_show"       => $is_show,
            "is_top"        => $is_top,
            "created_at"    => time(),
            "updated_at"    => time(),
        ];

        try {
            $pageArticleModel = new PageArticles();
            $res = $pageArticleModel
                ->insert($update_data);
            if ($res === false){
                return $this->com_return(false, config('params.FAIL'));
            }
            return $this->com_return(true, config('params.SUCCESS'));
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 文章编辑
     * @return array
     */
    public function edit()
    {
        $article_id    = $this->request->param("article_id","");
        $article_title = $this->request->param("article_title","");   //文章标题
        $article_image = $this->request->param("article_image","http://ceshiurl.com");   //文章图片
        $type          = $this->request->param("type","");            //文章类型
        $link          = $this->request->param("link","");            //文章链接
        $content       = $this->request->param("content","");          //文章链接
        $sort          = $this->request->param("sort","100");         //文章排序
        $is_top        = $this->request->param("is_top",0);         //是否置顶
        $is_show       = $this->request->param("is_show",0);       //是否显示

        $rule = [
            "article_id|文章id"      => "require",
            "article_title|文章标题"  => "require|max:50|unique:page_article",
            "article_image|文章图片"  => "require",
            "sort|文章排序"           => "require|number",
            "is_top|是否置顶"         => "require|number",
            "is_show|是否显示"        => "require|number",
        ];

        $request_res = [
            "article_id"    => $article_id,
            "article_title" => $article_title,
            "article_image" => $article_image,
            "sort"          => $sort,
            "is_top"        => $is_top,
            "is_show"       => $is_show,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if (empty($type)) $type = 0;

        $update_data = [
            "article_id"    => $article_id,
            "article_title" => $article_title,
            "article_image" => $article_image,
            "type"          => $type,
            "link"          => $link,
            "content"       => $content,
            "is_show"       => $is_show,
            "is_top"        => $is_top,
            "sort"          => $sort,
            "updated_at"    => time(),
        ];
        try {
            $pageArticleModel = new PageArticles();

            $res = $pageArticleModel
                ->where("article_id",$article_id)
                ->update($update_data);

            if ($res === false){
                return $this->com_return(false, config('params.FAIL'));
            }
            return $this->com_return(true, config('params.SUCCESS'));

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 文章删除
     * @return array
     */
    public function delete()
    {
        $article_ids= $this->request->param("article_id","");//文章id
        $rule = [
            "article_id|文章" => "require",
        ];

        $request_res = [
            "article_id" => $article_ids,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        Db::startTrans();
        try {
            $id_array          = explode(",",$article_ids);
            $pageArticlesModel = new PageArticles();

            foreach ($id_array as $article_id){
                $res = $pageArticlesModel
                    ->where('article_id',$article_id)
                    ->delete();
                if ($res === false) {
                    return $this->com_return(false, config("params.FAIL"));
                }
            }
            Db::commit();
            return $this->com_return(true, config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 置顶OR取消置顶
     * @return array
     */
    public function is_top()
    {
        $article_id = $this->request->param("article_id","");
        $is_top     = (int)$this->request->param("is_top","");

        $rule = [
            "article_id|文章" => "require",
        ];

        $request_res = [
            "article_id" => $article_id,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if ($is_top == 1){
            $success_message = "置顶成功";
            $fail_message    = "置顶失败";
        }else{
            $success_message = "取消置顶成功";
            $fail_message    = "取消置顶失败";
        }

        $update_data = [
            "is_top" => $is_top
        ];
        try {
            $pageArticlesModel = new PageArticles();

            $res = $pageArticlesModel
                ->where("article_id",$article_id)
                ->update($update_data);

            if ($res === false){
                return $this->com_return(false, $fail_message);
            }
            return $this->com_return(true, $success_message);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 是否显示
     * @return array
     */
    public function is_show()
    {
        $article_id  = $this->request->param("article_id","");
        $is_show     = (int)$this->request->param("is_show","");

        $rule = [
            "article_id|文章" => "require",
        ];

        $request_res = [
            "article_id" => $article_id,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if ($is_show == 1){
            $success_message = "已显示";
            $fail_message = "操作失败";
        }else{
            $success_message = "已隐藏";
            $fail_message = "隐藏失败";
        }

        $update_data = [
            "is_show" => $is_show
        ];

        try {
            $pageArticlesModel = new PageArticles();

            $res = $pageArticlesModel
                ->where("article_id",$article_id)
                ->update($update_data);

            if ($res === false){
                return $this->com_return(false, $fail_message);
            }
            return $this->com_return(true, $success_message);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 文章排序
     * @return array
     */
    public function sortEdit()
    {
        $article_id = $this->request->param("article_id","");
        $sort       = $this->request->param("sort","100");         //文章排序

        $rule = [
            "article_id|文章" => "require",
        ];

        $request_res = [
            "article_id" => $article_id,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if (empty($sort)) $sort = 100;

        $params = [
            'sort'       => $sort,
            'updated_at' => time()
        ];

        try {
            $pageArticlesModel = new PageArticles();

            $res = $pageArticlesModel
                ->where('article_id',$article_id)
                ->update($params);

            if ($res === false){
                return $this->com_return(false, config('params.FAIL'));
            }
            return $this->com_return(true, config('params.SUCCESS'));

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }
}