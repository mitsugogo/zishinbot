<?php

require_once 'commonTwitterConst.php';
require_once 'account/zishin3255_test.php';
require_once 'twitter/twitteroauth.php';

set_time_limit(0);
ini_set("date.timezone", "Asia/Tokyo");

class ZishinReport {
	
	private $con;
    
    const TARGET_URL = "http://zish.in/api/quake.json";
    
    /** 1:速報 */
    const TWEET_TYPE_PRELIMINARY = 1;
    /** 2:通常 */
    const TWEET_TYPE_NORMAL = 2;
    
    function __construct() {
        // todo:デバッグモードを設ける
    }
	
	public function main(){
	
		// connection
		$this->_getConnection();
        
        $newestData = $this->_getNewestZishinData();
        if ( empty($newestData) ) return;
        
        
        var_dump($newestData);
        exit;
		
		// post
//		$this->_postToTwitter( "hogeFuga ".date("Y-m-d H:i:s") );
	}
	
	/**
	 * 最新の地震情報を取得
	 */
	private function _getNewestZishinData(){
		
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_URL, self::TARGET_URL);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 2);
		
		$userAgent = "ZishinBOT created by mitsugogo";
		curl_setopt($this->ch, CURLOPT_USERAGENT, $userAgent);
		
		$ret = curl_exec($this->ch);
		
		$data = array();
		if ( !empty($ret) ){
			$data = json_decode( $ret, true );
		}
		
		curl_close($this->ch);
		
		return $data;
	}
	
    
    private function _getTweetType( $data ){
        switch( true ){
        }
    }
	
	private function _postToTwitter( $message ){
		$this->con->OAuthRequest( CommonTwitterConst::POST_TWEET_URL, "POST", array("status"=> $message ) );
	}
	
	private function _getConnection(){
		$this->con = new TwitterOAuth(ZishinConst::CONSUMER_KEY,ZishinConst::CONSUMER_SECRET,ZishinConst::ACCESS_TOKEN,ZishinConst::ACCESS_TOKEN_SECRET);
	}
	
	

}
$zishinObj = new ZishinReport();
$zishinObj->main();
