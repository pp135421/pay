<?php

function sendSmsAli($PhoneNumbers, $code)
{
    \AlibabaCloud\Client\AlibabaCloud::accessKeyClient('LTAIFEmIOg62SjfK', 'PbA40BOJtP8GHmtfiPWgU1QQ0T5Ptj')
                    ->regionId('cn-hangzhou') // replace regionId as you need
                    ->asDefaultClient();
    try {
        $result = \AlibabaCloud\Client\AlibabaCloud::rpc()
          ->product('Dysmsapi')
          // ->scheme('https') // https | http
          ->version('2017-05-25')
          ->action('SendSms')
          ->method('POST')
          ->options([
                'query' => [
                  'PhoneNumbers' => $PhoneNumbers,
                  'SignName' => "南居",
                  'TemplateCode' => "SMS_169636588",
                  'TemplateParam' => "{\"code\": \"$code\"}",
                ],
            ])
          ->request();
        $data = $result->toArray();
        // file_put_contents('./1.txt', json_encode([
        //                 'query' => [
        //                   'PhoneNumbers' => $PhoneNumbers,
        //                   'SignName' => "南居",
        //                   'TemplateCode' => "SMS_169636588",
        //                   'TemplateParam' => json_encode(['code' => trim($code)]),
        //                 ],
        // ], 320), FILE_APPEND);
        // file_put_contents('./1.txt', json_encode($data, 320), FILE_APPEND);
        if(isset($data['Code']) && $data['Code'] == 'OK'){
            return ['status' => true, 'msg' => $data['Message']];
        }else{
            return ['status' => false, 'msg' => $data['Message']];
        }
    } catch (\AlibabaCloud\Client\Exception\ClientException $e) {
        return ['status' => false, 'msg' => '报错：'.$e->getErrorMessage()];
    } catch (\AlibabaCloud\Client\Exception\ServerException $e) {
        return ['status' => false, 'msg' => '报错：'.$e->getErrorMessage()];
    }
}

function arrayToXml($arr)
{
    $xml = "<xml>";
    foreach ($arr as $key=>$val)
    {
        if (is_numeric($val)){
            $xml.="<".$key.">".$val."</".$key.">";
        }else{
             $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
        }
    }
    $xml.="</xml>";
    return $xml;
}

//将XML转为array
function xmlToArray($xml)
{
    //禁止引用外部xml实体
    libxml_disable_entity_loader(true);
    $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    return $values;
}

function RAS_openssl($data, $rsa_public_private, $type = 'encode'){
    $crypted = "";
    $decrypted = "";
    if (empty($data)) {
        return 'data参数不能为空';
    }
    //私钥解密
    if ($type == 'decode') {
        $private_key = openssl_pkey_get_private($rsa_public_private);
        if (!$private_key) {
            return('私钥不可用');
        }
        $return_de = openssl_private_decrypt(base64_decode($data), $decrypted, $private_key);
        if (!$return_de) {
            return('解密失败,请检查RSA秘钥');
        }
        return $decrypted;
    }
    //公钥加密
    $key = openssl_pkey_get_public($rsa_public_private);
    dump($key);
    if (!$key) {
        return('公钥不可用');
    }
    $return_en = openssl_public_encrypt($data, $crypted, $key);
    dump($return_en);
    if (!$return_en) {
        return('加密失败,请检查RSA秘钥');
    }
    return base64_encode($crypted);
}

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/26
 * Time: 14:29
 */
function getNeedBetween($message, $leftStr, $rightStr)
{
    $t1 = mb_strpos($message, $leftStr);
    $t2 = mb_strpos($message, $rightStr);
    $str = mb_substr($message, $t1 + mb_strlen($leftStr), $t2 - $t1 - mb_strlen($leftStr));
    $str = str_replace(',', '' , $str);
    return $str;
}

function remove_emoji($str){
    return preg_replace('/([0-9|#][\x{20E3}])|[\x{00ae}|\x{00a9}|\x{203C}|\x{2047}|\x{2048}|\x{2049}|\x{3030}|\x{303D}|\x{2139}|\x{2122}|\x{3297}|\x{3299}][\x{FE00}-\x{FEFF}]?|[\x{2190}-\x{21FF}][\x{FE00}-\x{FEFF}]?|[\x{2300}-\x{23FF}][\x{FE00}-\x{FEFF}]?|[\x{2460}-\x{24FF}][\x{FE00}-\x{FEFF}]?|[\x{25A0}-\x{25FF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{FE00}-\x{FEFF}]?|[\x{2900}-\x{297F}][\x{FE00}-\x{FEFF}]?|[\x{2B00}-\x{2BF0}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F6FF}][\x{FE00}-\x{FEFF}]?/u',
'', $str);
}

function echoJson($code, $msg, $json_mode = 320)
{
    echo json_encode(['code' => $code, 'msg' => $msg], $json_mode);
    die;
}

/**
 * 数据导出
 * @param array $title   标题行名称
 * @param array $data   导出数据
 * @param string $fileName 文件名
 * @param string $savePath 保存路径
 * @param $type   是否下载  false--保存   true--下载
 * @return string   返回文件全路径
 * @throws PHPExcel_Exception
 * @throws PHPExcel_Reader_Exception
 */
function exportExcel($title=array(), $data=array(), $fileName='', $savePath='./', $isDown=true){
    include('../extend/PHPExcel/Classes/PHPExcel.php');
    $obj = new PHPExcel();
    //横向单元格标识
    $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
    $obj->getActiveSheet(0)->setTitle('sheet名称');   //设置sheet名称
    $_row = 1;   //设置纵向单元格标识
    $obj->getActiveSheet(0)->setTitle('sheet名称');   //设置sheet名称
    $_row = 1;   //设置纵向单元格标识
    //设置sheet名称
    $obj->getActiveSheet(0)->setTitle('sheet名称');
    //设置纵向单元格标识
    $_row = 1;
    if($title){
        $_cnt = count($title);
        $dataTotal = count($data);
        //合并单元格
        $obj->getActiveSheet(0)->mergeCells('A'.$_row.':'.$cellName[$_cnt-1].$_row);
        $obj->setActiveSheetIndex(0)->setCellValue('A'.$_row, '导出：'.$dataTotal.'条'.'  |  导出时间：'.date('Y-m-d H:i:s'));
        //设置合并后的单元格内容
        $_row++;
        $i = 0;
        //设置列标题
        foreach($title AS $v){
            $obj->setActiveSheetIndex(0)->setCellValue($cellName[$i].$_row, $v)->getColumnDimension($cellName[$i])->setWidth(20);
            $i++;
        }
        $_row++;
    }
    //填写数据
    if($data){
        $i = 0;
        foreach($data AS $_v){
            $j = 0;
            foreach($_v AS $_cell){
                $obj->getActiveSheet(0)->setCellValue($cellName[$j] . ($i+$_row), $_cell);
                $j++;
            }
            $i++;
        }
    }
    //文件名处理
    if(!$fileName){
        $fileName = uniqid(time(),true);
    }
    $objWrite = PHPExcel_IOFactory::createWriter($obj, 'Excel2007');
    if($isDown){   //网页下载
        ob_end_clean();
        ob_start();
        header('pragma:public');
        header("Content-Disposition:attachment;filename=$fileName.xlsx");
        $objWrite->save('php://output');exit;
    }
    $_fileName = iconv("utf-8", "gb2312", $fileName);   //转码
    $_savePath = $savePath.$_fileName.'.xlsx';
    $objWrite->save($_savePath);
    return $savePath.$fileName.'.xlsx';
}

//获得IP
function getClientIP() {
    if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP") , "unknown")) {
        $ip = getenv("HTTP_CLIENT_IP");
    } else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR") , "unknown")) {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    } else if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR") , "unknown")) {
        $ip = getenv("REMOTE_ADDR");
    } else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = "unknown";
    }
    if(strpos($ip, ',') !== false){
        //假设存在代理IP，只取真实IP
        $arr = explode(',', $ip);
        $ip = $arr[0];
    }
    $ip = str_replace('::ffff:', '', $ip);
    return $ip;
}

function ip_to_city($ip, $is_area = true)
{
    if(strpos($ip, ',') !== false){
        //假设存在代理IP，只取真实IP
        $arr = explode(',', $ip);
        $ip = $arr[0];
    }
    $ip_arr = explode('.', $ip);
    if(count($ip_arr) != 4) return '-';
    include_once __DIR__. '/../extend/IpLocation/IpLocation.php';
    $ipModel = new IpLocation('qqwry_20190505.dat');
    $ipInfo = $ipModel->getlocation($ip);
    $country = $ipInfo['country'];
    $area = $ipInfo['area'] ? '_'. $ipInfo['area'] : '';
    $ip_to_city = $is_area ? ($country. $area) : $country;
    return $ip_to_city;
}

function only_province_city($province_city)
{
    $chinaProvinceCityArr = config('chinaProvinceCityArr');
    foreach ($chinaProvinceCityArr as $k => $v) {
        if($v){
            if(strpos($province_city, mb_substr($v, 0, mb_strlen($v) - 1)) !== false || strpos($v, $province_city) !== false){
                $province_city = $v;
                break;
            }
        }
    }
    return $province_city;
}

function baidu_map_ip($ip){
    $info=file_get_contents("http://api.map.baidu.com/location/ip?ip=". $ip. "&ak=nXrD3dxktSGqksPKk7MQAhr4X73WwcA2&coor=bd09ll");
    $info = json_decode($info, true);
    if($info['status'] == 0){
        $citys = explode('|', $info['address']);
        if($citys[2] == 'None'){
            return $citys[1];
        }else{
            return $citys[1] .'省'. $citys[2]. '市';
        }
    }
    return '-';
}

function checkImgExist($nongxin)
{
    if(is_object($nongxin)) $nongxin = $nongxin->toArray();
    if(!file_exists(ROOT_PATH. 'public'. $nongxin['img_path'])){
        $img_url = qrcode($nongxin['url']);
        $result = db('nongxin')->where(['id' => $nongxin['id']])->setField(['img_path' => $img_url]);
        if(!$result) return false;
        return $result;
    }
    return $nongxin;

}

function access_type($orderInfo)
{
    //判断连接方式
    if(strpos(strtolower($orderInfo['access_type']), 'Mac OS X') !== false || strpos(strtolower($orderInfo['access_type']), 'iphone') !== false || strpos(strtolower($orderInfo['access_type']), 'ipad') !== false || strpos(strtolower($orderInfo['access_type']), 'ipod') !== false){
        return '<button class="layui-btn layui-btn layui-btn-xs" style="width: 100px; background-color:black; margin: 0px 0px 3px 0px;" title="'.$orderInfo['access_ip'].'（' .ip_to_city($orderInfo['access_ip']).'）">iOS</button>';
    }else if(strpos($orderInfo['access_type'], 'Android') !== false || strpos($orderInfo['access_type'], 'Adr')!== false || strpos(strtolower($orderInfo['access_type']), 'symbianos') !== false){
        return '<button class="layui-btn layui-btn layui-btn-xs" style="width: 100px; background-color:black; margin: 0px 0px 3px 0px;" title="'.$orderInfo['access_ip'].'（' .ip_to_city($orderInfo['access_ip']).'）">Android</button>';
    }else if($orderInfo['access_type'] == 'wait'){
        return '';
    }else if($orderInfo['access_type'] != ''){
        return '<button class="layui-btn layui-btn layui-btn-xs" style="width: 100px; background-color:black; margin: 0px 0px 3px 0px;" title="'.$orderInfo['access_ip'].'（' .ip_to_city($orderInfo['access_ip']).'）">PC</button>';
    }
}

function access_type2($orderInfo)
{
    //判断连接方式
    if(strpos(strtolower($orderInfo['access_type']), 'Mac OS X') !== false || strpos(strtolower($orderInfo['access_type']), 'iphone') !== false || strpos(strtolower($orderInfo['access_type']), 'ipad') !== false || strpos(strtolower($orderInfo['access_type']), 'ipod') !== false){
        return '<img src="/static/admin/images/iOS.png" style="width: 20px;">';
    }else if(strpos($orderInfo['access_type'], 'Android') !== false || strpos($orderInfo['access_type'], 'Adr')!== false || strpos(strtolower($orderInfo['access_type']), 'symbianos') !== false){
        return '<img src="/static/admin/images/Android.png" style="width: 20px;">';
    }else if($orderInfo['access_type'] == 'wait'){
        return '-';
    }else if($orderInfo['access_type'] != ''){
        return '<img src="/static/admin/images/PC.png" style="width: 20px;">';
    }
}

function member_agent($agent)
{
    if($agent == 0){
        return '普通商户_无上级代理';
    }else if($agent == 1){
        return '普通商户_有上级代理';
    }else if($agent == 2){
        return '代理商户';
    }
}

function log_operation($msg)
{
    $user_name = defined('USER_NAME') ? USER_NAME : 'system';
    $data = [
        'username' => $user_name,
        'msg' => $msg,
        'create_date' => time(),
        'ip' => getClientIP(),
    ];
   return db('log_operation')->insert($data);
}

/**
 * @return bool
 */
function isMobile()
{
    // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
    if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))
    {
        return true;
    }
    // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
    if (isset ($_SERVER['HTTP_VIA']))
    {
        // 找不到为flase,否则为true
        return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
    }
    // 脑残法，判断手机发送的客户端标志,兼容性有待提高
    if (isset ($_SERVER['HTTP_USER_AGENT']))
    {
        $clientkeywords = array ('nokia','sony','ericsson','mot','samsung','htc','sgh','lg','sharp','sie-','philips','panasonic','alcatel','lenovo','iphone','ipod','blackberry','meizu','android','netfront','symbian','ucweb','windowsce','palm','operamini','operamobi','openwave','nexusone','cldc','midp','wap','mobile');
        // 从HTTP_USER_AGENT中查找手机浏览器的关键字
        if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT'])))
        {
            return true;
        }
    }
    // 协议法，因为有可能不准确，放到最后判断
    if (isset ($_SERVER['HTTP_ACCEPT']))
    {
        // 如果只支持wml并且不支持html那一定是移动设备
        // 如果支持wml和html但是wml在html之前则是移动设备
        if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html'))))
        {
            return true;
        }
    }
    return false;
}


/**
 * 描述：二维码处理
 * @return array
 * @author fengxing
 */
function getQrcode($url, $width = 200, $height = 200) {
    global $publicData;
    // dump($publicData['peizhi']['ewmcreate']);die;
    switch($publicData['peizhi']['ewmcreate']){
        case 'baidu':
            $tjurl = 'http://pan.baidu.com/share/qrcode?w=' . $width . '&h=' . $height . '&url=' . urlencode($url);
            break;
        case 'liantu':
            $tjurl = 'http://qr.liantu.com/api.php?text=' . urlencode($url) . '&w=' . $width . '&m=10';
            break;
        case 'lwl12':
            $tjurl = 'https://api.lwl12.com/img/qrcode/get?ct=' . urlencode($url) . '&w=' . $width . '&h=' . $height;
            break;
        case 'topscan':
            $tjurl = 'http://qr.topscan.com/api.php?text=' . urlencode($url) . '&w=' . $width . '&m=10';
            break;
        case 'k780':
            $tjurl = 'https://sapi.k780.com/?data=' . urlencode($url) . '&app=qr.get&level=L&size=6';
            break;
        case 'my':
            $tjurl = $publicData['peizhi']['httpstyle'] . '://' . $_SERVER['HTTP_HOST'] . '/Index/Index/qrcode?url=' . urlencode($url) . '&w=' . $width . '&k=' . md5(C('FX_QRCODE_KEY') . $url);
            break;
        default:
            $tjurl = 'http://qr.topscan.com/api.php?text=' . urlencode($url) . '&w=' . $width . '&m=10';
            break;
    }
    return $tjurl;
}

function form_post($param, $url){
    $headers = array('Content-Type: application/x-www-form-urlencoded');
    // $headers = array('Content-Type: application/json');
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
    curl_setopt($curl, CURLOPT_USERAGENT, isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '' ); // 模拟用户使用的浏览器
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
    curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($param)); // Post提交的数据包
    curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 设置超时限制防止死循环
    curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($curl); // 执行操作
    if (curl_errno($curl)) {
        return 'Errno'.curl_error($curl);//捕抓异常
    }
    curl_close($curl); // 关闭CURL会话
    return $result;
}

/**
 * @param $param
 * @param $url
 * @return mixed
 */
function curl_post($param, $url, $type = ''){
    $ch = curl_init();
    if($type == 'json'){
        $data = is_array($param) ? json_encode($param) : $param;
        $headers = array(
            'Content-Type: application/json',
            'Content-Length: '.strlen($data)
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }else if($type == 'xml'){
        $data = is_array($param) ? json_encode($param) : $param;
        $headers = array(
            'Content-Type: text/xml',
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }else{
        $data = is_array($param) ? http_build_query($param) : $param;
    }
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时限制防止死循环
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $output = $error ? $error : $result;
    curl_close($ch);
//    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return $output;
}

function curl_get($param, $url){
    $url .= '?';
    if(is_array($param)){
        foreach ($param as $k => $v) {
            $url .= $k. '='. $v. '&';
        }
    }else{
        $url .= $url. $param;
    }
    $url = trim($url, '&');
    $ch = curl_init();
    //设置选项，包括URL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//绕过ssl验证
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时限制防止死循环
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $output = $error ? $error : $result;
    curl_close($ch);
    return $output;
}

/**
 * @param $pay_status
 * @param $username
 * @return string
 */
function pay_status($orderInfo, $module = 'admin')
{
    if(is_object($orderInfo)) $orderInfo = $orderInfo->toArray();
    switch ($orderInfo['pay_status']) {
        case 1:
            $str = "<span class='layui-btn layui-btn-danger layui-btn-xs' style='width: 70px;'>待付款</span>";
            return $str;
            break;
        case 2:
            $str =  "<span class='layui-btn layui-btn layui-btn-xs' style='width: 70px; background-color: #009688;' title='".$orderInfo['return_msg']."'>已返";
            if($orderInfo['username'] && $module == 'admin'){
                $str .= "-". $orderInfo['username']. "</span>";
            }else if($module == 'admin'){
                $str .= "-自动</span>";
            }
            return $str;
            break;
        case 3:
            $str = "<span class='layui-btn layui-btn-warm layui-btn-xs' style='width: 70px;' title='".$orderInfo['return_msg']."'>未返";
            if($orderInfo['username'] && $module == 'admin'){
                $str .= "-". $orderInfo['username']. "</span>";
            }else if($module == 'admin'){
                $str .= "-自动</span>";
            }
            return $str;
            break;
        case 4:
            return "<span class='layui-btn layui-btn layui-btn-xs' style='width: 70px; background-color:blue'>超时成功</span>";
            break;
        case 98:
            return "<span class='layui-btn layui-btn layui-btn-xs' style='width: 70px; background-color:#ccc'><input type='text' style='width: 60px; border: 0px solid gray;background-color:#ccc;color:white;text-align:center;' title='".$orderInfo['poundage_error']."' value='"."[通道] ".$orderInfo['poundage_error']."'></span>";
            break;
        case 99:
            return "<span class='layui-btn layui-btn layui-btn-xs' style='width: 70px; background-color:black'><input type='text' style='width: 60px; border: 0px solid gray;background-color:black;color:white;text-align:center;' title='".$orderInfo['poundage_error']."' value='"."[内部] ". $orderInfo['poundage_error']."'></span>";
            break;
    }
}

function pay_status2($pay_status)
{
    switch ($pay_status) {
        case 1:
            $str = "待付款";
            return $str;
            break;
        case 2:
            $str =  "成功,已返回";
            return $str;
            break;
        case 3:
            $str = "成功,未返回";
            return $str;
            break;
        case 4:
            return "超时成功";
            break;
        case 99:
            return "获得链接失败";
            break;
    }
}


/**
 * @param $pay_status
 * @return string
 */
function change_type($change_type)
{
    $change_type_arr = config('change_type');
    if($change_type > 10 &&  $change_type < 20){
        return "<span style='color:green'>". $change_type_arr[$change_type]."</span>";
    }else if($change_type > 20 &&  $change_type < 30){
        return "<span style='color:red'>". $change_type_arr[$change_type]."</span>";
    }
}

/**
 * @param $type_name
 * @param $channel_name
 * @return string
 */
function channel_name($type_name, $channel_name)
{
    switch ($type_name) {
        case 'alipay':
            $type_name_cn = '支付宝';
            break;
        case 'wechat':
            $type_name_cn = '微信';
            break;
    }
    return "<span style='color:gray'>$channel_name</span><br>". "<span style='color:gray'>$type_name_cn</span>";
}

function record_error($platform_order_id, $msg = '')
{
    db('order')->where(['platform_order_id' => $platform_order_id])->setField([
        'poundage_error' => $msg,
        'pay_status' => 99,
    ]);
    showmessage($msg);
}

/**
 * @param string $msg
 * @param int $type
 * @param array $fields
 */
function showmessage($msg = '', $type = 0)
{
    header('Content-Type:application/json; charset=utf-8');
    $data = array('code' => $type ? 200 : 201, 'msg' => $msg);
    echo json_encode($data, 320);
    exit;
}

/**
 * @param string $msg
 * @param int $type
 * @param array $fields
 */
function show_success($data)
{
    header('Content-Type:application/json; charset=utf-8');
    echo json_encode($data, 320);
    exit;
}

/** 生成二维码
 * @param $content
 * @return bool|string
 */
function qrcode($content){
    require_once __DIR__.'/../extend/phpqrcode/phpqrcode.php';
    $value = $content;         //二维码内容
    $errorCorrectionLevel = 'L';  //容错级别
    $matrixPointSize = 6;      //生成图片大小
    //生成二维码图片
    $str = '/static/runtime/images/'.date('Ymd');
    $directory = __DIR__. '/../public'. $str;
    if(!file_exists($directory))  mkdir($directory, 0766);
    $filename = '.'.$str. '/QRcode_'.date("YmdHis").'_'.rand(100000,999999).'.png';
    $code = new \QRcode();
    $code->png($value,$filename , $errorCorrectionLevel, $matrixPointSize, 2);
    $QR = $filename;        //已经生成的原始二维码图片文件
    $QR = imagecreatefromstring(file_get_contents($QR));
    //输出图片
    imagepng($QR, $filename);
    imagedestroy($QR);
    $filename = substr($filename,1);
    return $filename;
}


//无限极分类
function getCateTree($data, $id=0, $lev=1, $flag=true)
{
    static $list = array();
    //首页递归之前必须先清理上次递归遗留下的静态$list
    if($lev == 1){
        $list = array();
    }
    foreach ($data as $v) {
        if($flag){
            if(!is_array($v)){
                $v = $v->toArray();
            }
            if($v['p_id'] == $id){
                $v['lev'] = $lev;
                $list[] = $v;
                getCateTree($data, $v['id'], $lev+1);
            }
        }else{
            if($v->p_id == $id){
                $v->lev = $lev;
                $list[] = $v;
                getCateTree($data, $v->id, $lev+1);
            }
        }
    }
    return $list;
}

//转义mysql字符
function DealChar($string)
{
    return strtr(trim($string),['%'=>'\%', '_'=>'\_', '\\'=>'\\\\']);
}

//盐值加密
function setPwdSalt($password)
{
    $salt = 'FOIDMSOF4TFfdsfggf4846.jfrhs4tr4791554f35fdofd9g977-3gg';
    return md5($password. md5($salt));
}

// 返回json数据
function ajaxReturn($status=1, $msg='', $data=[], $type='JSON'){
        $result = array(
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data
        );
        switch (strtoupper($type)){
            case 'JSON' :
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode($result));
            case 'XML'  :
                // 返回xml格式数据
                header('Content-Type:text/xml; charset=utf-8');
                exit(xml_encode($result));
            case 'JSONP':
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                $handler  =   isset($_GET[C('VAR_JSONP_HANDLER')]) ? $_GET[C('VAR_JSONP_HANDLER')] : C('DEFAULT_JSONP_HANDLER');
                exit($handler.'('.json_encode($result).');');
            case 'EVAL' :
                // 返回可执行的js脚本
                header('Content-Type:text/html; charset=utf-8');
                exit($result);
        }
}

//加密函数
function charEncode($txt, $key='MbPEut')
{
    $txt = (string)$txt;
    $txt = $txt.$key;
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
    $nh = rand(0,64);
    $ch = $chars[$nh];
    $mdKey = md5($key.$ch);
    $mdKey = substr($mdKey,$nh%8, $nh%8+7);
    $txt = base64_encode($txt);
    $tmp = '';
    $i=0;$j=0;$k = 0;
    for ($i=0; $i<strlen($txt); $i++) {
        $k = $k == strlen($mdKey) ? 0 : $k;
        $j = ($nh+strpos($chars,$txt[$i])+ord($mdKey[$k++]))%64;
        $tmp .= $chars[$j];
    }
    return urlencode(base64_encode($ch.$tmp));
}
//解密函数
function charDecode($txt, $key='MbPEut')
{
    $txt = (string)$txt;
    $txt = base64_decode(urldecode($txt));
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
    $ch = $txt[0];
    $nh = strpos($chars,$ch);
    $mdKey = md5($key.$ch);
    $mdKey = substr($mdKey,$nh%8, $nh%8+7);
    $txt = substr($txt,1);
    $tmp = '';
    $i=0;$j=0; $k = 0;
    for ($i=0; $i<strlen($txt); $i++) {
        $k = $k == strlen($mdKey) ? 0 : $k;
        $j = strpos($chars,$txt[$i])-$nh - ord($mdKey[$k++]);
        while ($j<0) $j+=64;
        $tmp .= $chars[$j];
    }
    return trim(base64_decode($tmp),$key);
}

function qrcodeCreate($url=''){
    require_once '/static/admin/phpqrcode/phpqrcode.php';
    $errorCorrectionLevel = 'L';  //容错级别
    $matrixPointSize = 5;      //生成图片大小
    //生成二维码图片
    $filename = './static/admin/images/QRcode_'.date("YmdHis").'_'.rand(100000,999999).'.png';
    $code = new \QRcode();
    $code->png($url,$filename , $errorCorrectionLevel, $matrixPointSize, 2);
    $QR = $filename;        //已经生成的原始二维码图片文件
    $QR = imagecreatefromstring(file_get_contents($QR));
    //输出图片
    imagepng($QR, $filename);
    imagedestroy($QR);
    $filename = substr($filename,1);
    return $filename;
}