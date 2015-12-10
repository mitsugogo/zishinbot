<?php

/**
 * 地震botツイート
 * @author mitsugogo
 * @version 1.3.1
 **/


set_time_limit(0);
ini_set("max_execution_time",0);
ini_set("date.timezone", "Asia/Tokyo");

require_once 'twitter/twistOAuth.php';
require_once 'tweet.php';
require_once 'log.php';
require_once 'push_kayac.php';


class zishin {

	const FILTER_USER_ID = '214358709'; // 16222364:mitsugogo 214358709:eewbot　539118908:zishin3255_test

	const INI_DIR = "/usr/local/mitsugogo/zishinbot/config/tweetConfig.ini";

	private $config;
	private $isTweet;
	private $logging;
	private $push;

	public function main( $retryCount = 0 ){
		//syslogクラス
		$this->logging = new Logging();

		// kayacへのPUSH送信クラス
		$this->push = new PushKayac();

		// configファイル取得
		$this->config = parse_ini_file(self::INI_DIR, true);

		require_once 'account/zishin3255_test.php';
		$tc = new TwistOAuth(ZishinConst::CONSUMER_KEY,ZishinConst::CONSUMER_SECRET,ZishinConst::ACCESS_TOKEN,ZishinConst::ACCESS_TOKEN_SECRET);

		// サービス開始をツイートする
		$tweet = new Tweet( 'zishin3255_test' );
		$tweet->setTweetString( '[service] zishinbot service start! '.date("Y/m/d H:i:s") );
		$response = $tweet->tweet();
		$this->insertTweetHistory( $response, "zishin3255_test", '[service] zishinbot service start!', "" );

		// サービス開始
		$this->logging->systemLog("[Notice] Zishinbot Service start! retryCount={$retryCount}");
		$this->push->send( "[監視] Zishinbot Service start! retryCount={$retryCount}" );

		$func = function ($status) use ($tc) {

			// 対象ユーザーのつぶやきのみ
			if ( $status->user->id != self::FILTER_USER_ID ) {
//				echo sprintf( '[skip] reply from id:%s name:%s', $status->user->id, $status->user->name );
				return;
			}

			$tweetStr = $status->text;

			$eewParams = $this->_parseCsv( $tweetStr );
			if ( $eewParams === false ) {
				$this->logging->systemLog( sprintf( "[Warning] Undefined message! message=%s", $tweetStr ) );
				return;
			}

			$this->logging->systemLog( sprintf( "[Notice] New status received. message=%s", $tweetStr ) );

			// DBに記録する。
			try {
				$this->insertHistory( $eewParams );
			} catch (Exception $ex) {
				echo $ex->getMessage()."\n";
			}

			list(
				$telegraphicMessageType,	// 電文の種別 35:最大震度のみ(Mの推定なし) 36,37:最大震度、Mの推定あり 39:キャンセル報
				$isKunren,					// 訓練かどうか 00:通常 01:訓練
				$sendDate,					// 発表時刻
				$sendType,					// 発表状況 0:通常 7:キャンセルを誤って発表　8,9:最終報
				$telegraphicMessageId,		// 電文番号
				$zishinId,					// 地震ID
				$zishinOccurrenceDate,		// 地震発生時刻
				$lat,						// 北緯
				$long,						// 東経
				$areaName,					// 震央地名
				$depth,						// 深さ
				$magnitude,					// マグニチュード
				$maxShindo,					// 最大震度
				$isSea,						// 海洋フラグ
				$isAlarm					// 警報有無(true:大きな地震、TVで警報でるやつ false:通常)
				) = $eewParams;


			// 最大震度を数値で扱う
			$_shindoList = array(
				'1'	=> 1,
				'2'	=> 2,
				'3'	=> 3,
				'4'	=> 4,
				'5弱' => 5,
				'5強' => 5.5,
				'6弱' => 6,
				'6強' => 6.5,
				'7' => 7
			);
			$maxShindoInt = ( isset($_shindoList[$maxShindo]) ) ? $_shindoList[$maxShindo] : $maxShindo;

			// フラグにする
			$isKunren = ( $isKunren == '01' );
			$isSea	= (bool)$isSea;
			$isAlarm  = (bool)$isAlarm;

			// 津波の有無
			$isTsunami = (float)$magnitude > (float)$this->config['tsunami']['base_magnitude'] && $isSea;

			// 最終報か
			$isFinal   = ( (int)$sendType == 8 || (int)$sendType == 9 );

			// 置換文字列を生成する
			$replaceStrings = array(
				'kunren'		=> ( $isKunren ) ? "【訓練】" : ""
			, 'sea'			=> ( $isSea )	 ? "海洋" : ""
			, 'areaName'	=> $areaName
			, 'depth'		=> $depth
			, 'depthStr'	=> ( is_float($depth) || is_numeric($depth) ) ? $depth."km" : $depth
			, 'magnitude'	=> $magnitude
			, 'maxShindo'	=> $maxShindo
			, 'messageId'	=> ( $isFinal ) ? "最終報" : $telegraphicMessageId
			, 'messageIdStr'=> ( $isFinal ) ? "最終報" : "第{$telegraphicMessageId}報"
			, 'sendDate'	=> $zishinOccurrenceDate
			, 'tsunamiAlert'=> ( $isTsunami ) ? "[備考] 今後の情報に注意してください " : ""
			, 'tsunamiHash'	=> ( $isTsunami ) ? "#tsunami " : ""
			);


			// ツイートの生成

			// zishin3255
			$tweetString = $this->_getFormatString( "zishin3255", $replaceStrings, $zishinId, $telegraphicMessageId, $isFinal, $magnitude, $maxShindoInt );
			echo $tweetString. "\n";
			if ( !empty($tweetString) ){
				$tweet = new Tweet( 'zishin3255' );
				$tweet->setTweetString( $tweetString );
				$tweet->setLatLong( $lat, $long );
				$response = $tweet->tweet();
				$this->insertTweetHistory( $response, "zishin3255", $tweetString, $zishinId );

				// PUSH送信
				$this->push->send( $tweetString );

			}
			// zishin3255_2
			$tweetString = $this->_getFormatString( "zishin3255_2", $replaceStrings, $zishinId, $telegraphicMessageId, $isFinal, $magnitude, $maxShindoInt );
			echo $tweetString. "\n";
			if ( !empty($tweetString) ){
				$tweet = new Tweet( 'zishin3255_2' );
				$tweet->setTweetString( $tweetString );
				$tweet->setLatLong( $lat, $long );
				$response = $tweet->tweet();
				$this->insertTweetHistory( $response, "zishin3255_2", $tweetString, $zishinId );
			}
			// zishin3255_3
			$tweetString = $this->_getFormatString( "zishin3255_3", $replaceStrings, $zishinId, $telegraphicMessageId, $isFinal, $magnitude, $maxShindoInt );
			echo $tweetString. "\n";
			if ( !empty($tweetString) ){
				$tweet = new Tweet( 'zishin3255_3' );
				$tweet->setTweetString( $tweetString );
				$tweet->setLatLong( $lat, $long );
				$response = $tweet->tweet();
				$this->insertTweetHistory( $response, "zishin3255_3", $tweetString, $zishinId );
			}

		};

		$params = array(
			'follow' => self::FILTER_USER_ID
		);
		try {
			$tc->streaming('statuses/filter', $func, $params);
		} catch (Exception $ex) {
			$this->logging->systemLog( sprintf( "[Warning] StreamingAPI error(Exception). Restart program. message=%s", $ex->getMessage() ) );
			// 自分自身を呼びなおす
			$retryCount++;
			$this->main( $retryCount );
		}

		// 最後まで来た場合も途中で接続が切れているため、再度呼びなおす
		$this->logging->systemLog( "[Warning] StreamingAPI error. Restart program." );
		$retryCount++;
		$this->main( $retryCount );
	}

	/**
	 * CSV形式のツイートをparseする
	 * @param string $str
	 * @return boolean|array
	 */
	private function _parseCsv( $str ){
		// 37,00,2014/10/15 12:52:20,9,5,ND20141015125126,2014/10/15 12:51:16,38.5,141.7,宮城県沖,60,4.4,3,1,0
		// 電文の種別,訓練識別符,発表時刻,発表状況,電文番号,地震ID,地震発生時刻,震源の北緯,震源の東経,震央地名,震源の深さ,マグニチュード(以下M),最大震度,震源の海陸判定,警報の有無
		if ( empty($str) ) return false;
		$params = explode(",", $str);

		return count($params) >= 15 ? $params : false;

	}

	/**
	 * ツイートする文字列を生成します。
	 * @param string $account
	 * @param array $replaceStrings
	 * @param string $zishinId				地震ID(地震ごとにユニーク)
	 * @param int $telegraphicMessageId		第n報(数値)
	 * @param bool $isFinal					最終報フラグ
	 */

	/**
	 * ツイートする文字列を生成します。
	 * @param $account
	 * @param $replaceStrings
	 * @param $zishinId
	 * @param $telegraphicMessageId
	 * @param $isFinal
	 * @param $magnitude
	 * @param $maxShindoInt
	 * @return mixed|string
	 * @throws Exception
	 */
	private function _getFormatString( $account, $replaceStrings, $zishinId, $telegraphicMessageId, $isFinal, $magnitude, $maxShindoInt ){
		if ( empty($account) ){
			throw new Exception("undefined account!");
		}

		// 設定ファイル取得
		if ( !isset($this->config[$account]) ){
			throw new Exception("undefined config!");
		}
		// 扱いやすいように変数に入れる
		$accountConfig = $this->config[$account];

		// メモリ消費量を減らすために、第1報受信時に履歴を綺麗にする
		if ( $telegraphicMessageId == 1 ) $this->isTweet = null;

		// この地震に対してつぶやいたことがあるかどうか
		$isTweetForThis = ( isset($this->isTweet[$account][$zishinId]) && $this->isTweet[$account][$zishinId] == true );
		if ( $accountConfig['enableTweetInOutOfSetting'] == false ) $isTweetForThis = false; // 条件を下回った場合を考慮しない場合はフラグを落とす

		// 無効の場合は終了
		if ( !isset($accountConfig['enable']) || $accountConfig['enable'] == 0 ) return "";

		// 第n報を数値型にする
		$telegraphicMessageId = (int)$telegraphicMessageId;

		// ツイートするメッセージIDリストをconfigから取得する
		$tweetMessageIdList = @$accountConfig['messageIdList'];

		// マグニチュード判定
		if ( !$isTweetForThis && isset($accountConfig['magnitude']) && ( $magnitude < $accountConfig['magnitude']) ){
			return "";
		}

		// 最大震度
		if ( !$isTweetForThis && isset($accountConfig['maxShindo']) && ( $maxShindoInt < $accountConfig['maxShindo']) ){
			return "";
		}

		// ツイートするメッセージIDが設定されている場合＆最終報でない場合は評価する
		if ( !empty($tweetMessageIdList) && !$isFinal ){
			if ( !in_array( $telegraphicMessageId, explode(',', $tweetMessageIdList) ) ){
				return "";
			}
		}

		// フォーマット取得
		$str = $accountConfig['format'];

		// このアカウントがこの地震に対してつぶやいたことを記憶する
		$this->isTweet[$account][$zishinId] = true;

		// 置換対象がない場合はここで返却する
		if ( empty($replaceStrings) ) return $str;

		foreach( $replaceStrings as $key => $replaceStr ){
			$str = str_replace( '{:'.$key.'}', $replaceStr, $str );
		}

		return $str;
	}

	/**
	 * DBに履歴を保存します
	 * @param array $params
	 */
	private function insertHistory( $params ){

		// 記録しない場合はなにもしない
		if ( $this->config['db']['enable'] != 1 ) return;

		list(
			$telegraphicMessageType,	// 電文の種別 35:最大震度のみ(Mの推定なし) 36,37:最大震度、Mの推定あり 39:キャンセル報
			$isKunren,					// 訓練かどうか 00:通常 01:訓練
			$sendDate,					// 発表時刻
			$sendType,					// 発表状況 0:通常 7:キャンセルを誤って発表　8,9:最終報
			$telegraphicMessageId,		// 電文番号
			$zishinId,					// 地震ID
			$zishinOccurrenceDate,		// 地震発生時刻
			$lat,						// 北緯
			$long,						// 東経
			$areaName,					// 震央地名
			$depth,						// 深さ
			$magnitude,					// マグニチュード
			$maxShindo,					// 最大震度
			$isSea,						// 海洋フラグ
			$isAlarm					// 警報有無(true:大きな地震、TVで警報でるやつ false:通常)
			) = $params;

		$insParams = array(
			$zishinId,
			(int)$telegraphicMessageId,
			$telegraphicMessageType,
			$isKunren,
			$sendDate,
			(int)$sendType,
			$zishinOccurrenceDate,
			(float)$lat,
			(float)$long,
			(float)$lat,
			(float)$long,
			$areaName,
			$depth,
			(float)$magnitude,
			$maxShindo,
			(int)$isSea,
			(int)$isAlarm
		);

		$telegraphicMessageId = (int)$telegraphicMessageId;
		$sendType = (int)$sendType;
		$lat = (float)$lat;
		$long = (float)$long;
		$magnitude = (float)$magnitude;
		$isSea = (int)$isSea;
		$isAlarm = (int)$isAlarm;

		$query = "
INSERT INTO eew_history_tbl
VALUES(
				NULL,
				'{$zishinId}',
				{$telegraphicMessageId},
				'{$telegraphicMessageType}',
				'{$isKunren}',
				'{$sendDate}',
				{$sendType},
				'{$zishinOccurrenceDate}',
				{$lat},
				{$long},
				GeomFromText('POINT({$lat} {$long})'),
				'{$areaName}',
				'{$depth}',
				{$magnitude},
				'{$maxShindo}',
				{$isSea},
				{$isAlarm},
				now(),
				now()
)";
		$options = array(
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
		);
		$pdo = new PDO( $this->config['db']['dsn'], $this->config['db']['user'], $this->config['db']['password'], $options );
		$pdo->query($query);

	}

	/**
	 * DBにツイート履歴を保存します
	 * @param array $response Tweetクラスのtweet()の戻り値
	 */
	private function insertTweetHistory( $response, $account, $tweet, $zishinId ){

		// 記録しない場合はなにもしない
		if ( $this->config['db']['enable'] != 1 || empty($response) ) return;

		$response = json_decode($response, true);

		$errorFlag = (int)(isset($response['errors']));

		ini_set( 'error_reporting', 'E_ALL & ~E_NOTICE' );

		$createAt = ( isset($response['created_at']) ) ? date("Y-m-d H:i:s", strtotime($response['created_at'])) : "";

		$query = "
INSERT INTO bot_tweet_history_tbl
VALUES(
				NULL,
				'{$account}',
				'$zishinId',
				'{$response['id']}',
				'{$tweet}',
				{$errorFlag},
				'{$response['errors']['code']}',
				'{$response['errors']['message']}',
				'{$createAt}',
				now(),
				now()
)";

		try {
			$options = array(
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
			);
			$pdo = new PDO( $this->config['db']['dsn'], $this->config['db']['user'], $this->config['db']['password'], $options );

			$pdo->query($query);
		} catch (Exception $ex) {
			echo $ex->getMessage()."\n";
		}

	}



}

$zishin = new zishin();
$zishin->main();


