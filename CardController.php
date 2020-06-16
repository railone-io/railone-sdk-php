<?php
namespace console\components\card;

use Yii;
use yii\base\Module;
use linslin\yii2\curl\Curl;
use console\models\card\CardApiConfigModel;

/**
 * 借记卡base类
 * Class CardController
 */
class CardController extends \console\components\controllers\BaseController
{
    /**
     * @var Curl
     */
    private $curl;

    private $baseUrl = '';

    private $apiKey = '';

    private $apiSecret = '';

    private $passphrase = '';

    private $getParams = [];

    private $postParams = [];


    public function __construct($id, Module $module, array $config = [])
    {
       /* $baseUrl = 'https://api.sandbox.railone.io';
        $apiKey = '14db63d7f3614664ad1c71dd134a21dc';
        $apiSecret = 'ed8cb3a0-8365-4340-9d9c-33f051eedccd';
        $passphrase  = '12345678a';*/

        $apiData = CardApiConfigModel::getConfig();

        $this->passphrase = $apiData['passphrase'];
        $this->apiKey = $apiData['api_key'];
        $this->apiSecret = $apiData['api_secret'];
        $this->baseUrl = $apiData['domain_url'];
        $this->curl = new Curl();

        parent::__construct($id, $module, $config);
    }

    /**
     * Set get params
     *
     * @param array $params
     * @return $this
     */
    public function setGetParams($params)
    {
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                $this->getParams[$key] = $value;
            }
        }

        $this->curl->setGetParams($params);

        return $this;
    }

    /**
     * Set post params
     *
     * @param array $params
     * @return $this
     */
    public function setPostParams($params)
    {
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                $this->postParams[$key] = $value;
            }
        }

        $this->curl->setRequestBody(json_encode($params, JSON_UNESCAPED_SLASHES));

        return $this;
    }

    /**
     * curl 参数设置
     * @param $method
     * @param $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        call_user_func_array([$this->curl, $method], $arguments);

        return $this;
    }

    /**
     * 返回当前的毫秒时间戳
     */
    public function getMicroTime() {
        list($msec, $sec) = explode(' ', microtime());

        $microTime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);

        return $microTime;
    }

    /**
     * get post 请求封装
     * @param $actionName
     * @param $methodType
     * @return mixed
     */
    public function request($actionName, $methodType = 'GET')
    {
        $methodType = strtolower($methodType);

        $url = $this->baseUrl. $actionName;

        $this->setHeader($this->getMicroTime(), $methodType, $actionName);

        $response = call_user_func_array([$this->curl, strtolower($methodType)], [$url]);

        $result = false;

        if ($this->curl->responseCode == 200 ){
            $result = json_decode($response, true);

            if ($result['code'] != 0) {
                $content = "借记卡请求出错,code:{$result['code']},msg:{$result['msg']}";
            }
        }else{
            $this->logger(print_r($this->curl, true));
            $content = "借记卡请求出错,'.code:{$this->curl->responseCode},errorCode:{$this->curl->errorCode}, msg:{$this->curl->errorText}";
        }

        if (!empty($content)){
            $this->sendDingMessage($content);
        }
        
        return $result;
    }

    /**
     * 设置请求头
     * @param $microTime
     * @param $methodType
     * @param $actionName
     */
    public function setHeader($microTime, $methodType, $actionName)
    {
        $uri = count($this->getParams) > 0 ? '?'.http_build_query($this->getParams) : '';

        ksort($this->postParams);

        $postStr = '';

        if (!empty($this->postParams)){
            foreach ($this->postParams as $key => $value){
                $postStr .= $key."=" .$value.'&';
            }

            $postStr = substr($postStr ,0, -1);
        }

        $signatureData = [
            $microTime,
            strtoupper($methodType),
            $this->apiKey,
            $actionName.$uri,
            $postStr
        ];

        $signStr = implode("", $signatureData);

       // echo '待签名字符串：'.$signStr.PHP_EOL;

        $signature = base64_encode(hash_hmac('sha256', $signStr, $this->apiSecret, true));

       // echo '签名结果：：'.$signature.PHP_EOL;

        $headers = [
            "Content-Type:application/json",
            "Authorization:Noumena:{$this->apiKey}:{$microTime}:{$signature}",
            "Access-Passphrase:{$this->passphrase}"
        ];

        $this->curl->setOption(CURLOPT_HTTPHEADER, $headers);
    }
}
