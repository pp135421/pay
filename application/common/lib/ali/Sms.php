<?php
namespace app\common\lib\ali;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Ecs\Ecs;

class Sms
{
    public static function sendSms($phone, $code) {
        // 设置全局客户端
        AlibabaCloud::accessKeyClient('LTAIFEmIOg62SjfK', 'PbA40BOJtP8GHmtfiPWgU1QQ0T5Ptj')
        ->regionId('cn-hangzhou') // replace regionId as you need
        ->asDefaultClient();

        $result = AlibabaCloud::rpc()
        ->product('Dysmsapi')
        ->version('2017-05-25')
        ->action('SendSms')
        ->method('POST')
        ->options([
            'query' => [
              'PhoneNumbers' => $phone,
              'SignName' => "南居",
              'TemplateCode' => "SMS_169636588",
              'TemplateParam' => json_encode(['code' => $code])
            ],
        ])
        ->request();
        return $result;
    }
}