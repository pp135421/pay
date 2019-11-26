<?php
namespace app\admin2020\controller;

use app\admin2020\model\Nongxin as NongxinModel;

class Nongxin extends Common
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $where = [];
        $status = input('status', '');
        if($status){
            $where['status'] = $status;
        }
        //每页显示30条数据
        $perCount = 30;
        $list = NongxinModel::where($where)->order('money desc')->paginate($perCount, false, ['query'=>input()]);
        //获得当前表的全部条数
        $count = NongxinModel::count();
        $showData = array(
            'status' => $status,
        );
        return view('index', compact('list', 'showData', 'channelInfo', 'count'));
    }

    // public function upload(){
    //     $file = request()->file('file');
    //     if($file){
    //         $uploads = ROOT_PATH .'public/static/runtime/uploads';
    //         $fileName = $file->getInfo()['name'];
    //         $result = preg_match('/\d+\.\d+/', $fileName, $arr);
    //         if($result){
    //             $money = $arr[0];
    //             $str = str_replace('.png', '', $fileName);
    //             $group = str_replace($money, '', $str);
    //         }
    //         $info = $file->move($uploads, $fileName);
    //         if($info){
    //             include_once ROOT_PATH. '/extend/parseQrcode/lib/QrReader.php';
    //             $qrcode = new \QrReader($uploads. '/'. $fileName);  //图片路径
    //             $url = $qrcode->text(); //返回识别后的文本
    //             $model = db('nongxin')->where(['money' => (float)$money, 'group' => $group])->find();
    //             //必须解析出链接才加入数据库
    //             if(!$model && $url){
    //                 $data = [
    //                     'username' => USER_NAME,
    //                     'url' => $url,
    //                     'img_path' => '/static/runtime/uploads/'. $fileName,
    //                     'is_used' => 2,
    //                     'create_date' => time(),
    //                     'money' => $money,
    //                     'group' => $group,
    //                 ];
    //                 $result = db('nongxin')->insert($data);
    //                 if(!$result){
    //                     $res = ['code' => 1, 'msg' => '更新数据失败！'];
    //                     echo json_encode($res, 320);
    //                 }else{
    //                     log_operation('上传二维码图片');
    //                     $res = ['code' => 0, 'msg' => '上传成功'];
    //                     echo json_encode($res, 320);
    //                 }
    //             }else{
    //                 if(!$url){
    //                     $res = ['code' => 1, 'msg' => '金额：[ '.$money.' ] 解析url失败！请重新截图上传！'];
    //                     echo json_encode($res, 320);
    //                 }else{
    //                     $res = ['code' => 1, 'msg' => '分组：[ '.$group.' ] ，金额：[ '.$money.' ] 已经存在！'];
    //                     echo json_encode($res, 320);
    //                 }
    //             }
    //         }else{
    //             // 上传失败获取错误信息
    //             echo $file->getError();
    //         }
    //     }
    // }

    public function set(){
        return view('set');
    }

    public function del()
    {
        $id = input('id', 0);
        if(!$id) showmessage('非法参数');
        $ret = NongxinModel::del($id);
        if(!$ret) showmessage('删除失败');
        log_operation('删除二维码图片-'.$id);
        showmessage('删除成功', 1);
    }

    public function set_money()
    {
        $id = input('id', '');
        if(!$id) showmessage('非法操作');
        $money = input('money/f', '');
        if(!$money) showmessage('金额不能为空');
        $info = db('nongxin')->where(['money' => $money])->find();
        if($info) showmessage('金额已经存在，修改失败');
        $result = db('nongxin')->where(['id' => $id])->setField(['money' => $money]);
        if(!$result) showmessage('修改金额失败-'. $money);
        log_operation('修改金额-id：'. $id. '，金额：'.$money);
        showmessage('修改金额成功-'. $money, 1);
    }

    public function set_group()
    {
        $id = input('id', '');
        if(!$id) showmessage('非法操作');
        $group = input('group', '');
        if(!$group) showmessage('分组不能为空');
        $result = db('nongxin')->where(['id' => $id])->setField(['group' => $group]);
        if(!$result) showmessage('修改分组失败-'. $group);
        log_operation('修改分组-id：'. $id. '，分组：'.$group);
        showmessage('修改分组成功-'. $group, 1);
    }
}
