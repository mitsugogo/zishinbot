<?php

/**
 * つぶやく
 */

require_once 'commonTwitterConst.php';
require_once 'twitter/twitteroauth.php';
require_once 'log.php';
//require_once 'kayac.php';


set_time_limit(0);
ini_set("date.timezone", "Asia/Tokyo");

class Tweet {

	private $con;

	private $tweetStr;
	private $lat;
	private $long;

	private $accountConfig;

	const INI_DIR = "/usr/local/mitsugogo/zishinbot/config/account.ini";

	public function __construct( $account ) {
		$config = parse_ini_file(self::INI_DIR, true);
		$this->accountConfig = $config[$account];
	}

	/**
	 * ツイート文字列をセット
	 * @param $str
	 */
	public function setTweetString( $str ){
		$this->tweetStr = $str;
	}

	/**
	 * 緯度経度をセット
	 * @param $lat
	 * @param $long
	 */
	public function setLatLong( $lat, $long ){
		if ( empty($lat) || empty($long) ) return;
		$this->lat = $lat;
		$this->long = $long;
	}

	/**
	 * ツイート
	 * @return bool
	 */
	public function tweet(){

		if ( empty($this->tweetStr) ) return false;

		// connection
		$this->_getConnection();

		// post
		$ret = $this->_postToTwitter( $this->tweetStr, $this->lat, $this->long );

		// syslog
		$logging = new Logging();
		$logging->tweetLog($this->tweetStr);

		// kayac
		//$imkayac = new ImKayac();
		// sendKayacに戻り値があるので拾っておく。0以外でエラー。
		//$kayac_ret = $imkayac->sendKayac($this->tweetStr);

		// 念のため空にする
		$this->tweetStr = '';
		$this->lat = '';
		$this->long = '';

		return $ret;
	}


	/**
	 * Twitterにpostします
	 * @param $message
	 * @param null $lat
	 * @param null $long
	 * @return bool
	 */
	private function _postToTwitter( $message, $lat = null , $long = null ){

		if ( empty($message) ) return false;

		$params = array(
			'status' => $message
		);

		if ( !empty($lat) && !empty($long) ){
			$params['lat'] = $lat;
			$params['long'] = $long;
		}

		return $this->con->OAuthRequest( CommonTwitterConst::POST_TWEET_URL, "POST", $params );
	}

	private function _getConnection(){
		$this->con = new TwitterOAuth($this->accountConfig['consumer_key'],$this->accountConfig['consumer_secret'],$this->accountConfig['access_token'],$this->accountConfig['access_token_secret']);
	}



}
