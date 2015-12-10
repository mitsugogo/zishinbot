<?php

require_once("const.php");

$oauthConfig = array(
    'callbackUrl' => 'http://net.pressmantech.com/twitter/accesskey.php',
    'authorizeUrl' => 'https://api.twitter.com/oauth/authorize',
    'requestTokenUrl' => 'https://api.twitter.com/oauth/request_token',
    'accessTokenUrl' => 'https://api.twitter.com/oauth/access_token',
    'consumerKey' => ZishinConst::CONSUMER_KEY,
    'consumerSecret' => ZishinConst::CONSUMER_SECRET
);

$method = 'POST'; //(0)

$nonce =  md5(uniqid(rand(), true));

$timestamp = time();

$authorization = array(
    'oauth_nonce' => $nonce,
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp' => $timestamp,
    'oauth_consumer_key' => $oauthConfig['consumerKey'],
    'oauth_version' => '1.0'
);

if($oauthConfig['callbackUrl']){
    $authorization['oauth_callback'] = $oauthConfig['callbackUrl'];
}else{
    $authorization['oauth_callback'] = 'oob';
}

ksort($authorization);

$signatureBaseString = '';
foreach($authorization as $key => $val){
    $signatureBaseString .= $key . '=' . rawurlencode($val) . '&';
}
$signatureBaseString = substr($signatureBaseString,0,-1);
$signatureBaseString = $method . '&' .
    rawurlencode($oauthConfig['requestTokenUrl']) . '&' .
    rawurlencode($signatureBaseString);

$signingKey = rawurlencode($oauthConfig['consumerSecret']) . '&';

$authorization['oauth_signature'] =
    base64_encode(hash_hmac('sha1',$signatureBaseString,$signingKey,true));

//↑ここまではGETがPOSTになる以外は前回と同じ---------------------------------
/**
 * (1)リクエストトークンを取得するためのhttpヘッダを作成
 * GETで取得したときとほぼ同じだが、値がダブルクォーテーションで囲まれて、
 * カンマで区切られている
 */
$oauthHeader = 'OAuth ';
foreach($authorization as $key => $val){
    $oauthHeader .= $key . '="' . rawurlencode($val) .'",';
}
$oauthHeader = substr($oauthHeader,0,-1);

/**
 * (2)curlを使用してPOSTでリクエストトークンを取得。
 * POSTで先ほど作成したヘッダで通信しているだけだが、デフォルト設定のままだと
 * Content-Length: -1,Expect: 100-continueをヘッダにつけることがあり、
 * そうするとtwitterのサーバーが413 Request Entity Too Large,
 * 417 Expectation Failedのエラーをはくので二つのヘッダは消してある
 */
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $oauthConfig['requestTokenUrl']);
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($curl, CURLOPT_TIMEOUT, 30);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Authorization :' . $oauthHeader,
        'Content-Length:',
        'Expect:',
        'Content-Type:')
);
curl_setopt($curl, CURLOPT_POST, true);
$response = curl_exec($curl);

var_dump($response);

/**
 * (3)curlが使えない環境もあるので簡単に書いたストリームで接続するソースも
 * つけておく。
 */

/*
$fp = stream_socket_client("ssl://api.twitter.com:443", $errno, $errstr, 30);
fwrite($fp, "POST /oauth/request_token HTTP/1.0\r\nHost: api.twitter.com\r\n"
    . "Accept: *"."/"."*\r\nAuthorization :" . $oauthHeader . "\r\n\r\n");
$response = '';
while (!feof($fp)) {
    $response .= fgets($fp, 1024);
}
fclose($fp);
$response = substr($response,strpos($response,"\r\n\r\n") + 4);
*/

$requestToken = array();
foreach(explode('&',$response) as $v){
    $param = explode('=',$v);
    $requestToken[$param[0]] = $param[1];
}

/**
 * (4)リクエストトークンはこの後の処理で必要なのでセッションに格納しておく。
 */
session_start();
$_SESSION['TWITTER_REQUEST_TOKEN'] = serialize($requestToken);

/**
 * (5)リダイレクトして認証画面を表示する。
 */
$redirectUrl = $oauthConfig['authorizeUrl'] . '?oauth_token='
    . $requestToken['oauth_token'];
header('location: ' . $redirectUrl);

echo $redirectUrl;