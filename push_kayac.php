<?php
/**
 * im.Kayac.comを使ったPUSH送信を行う
 */
Class PushKayac{

	/**
	 * メッセージを送信します
	 * @param str $message
	 * @return void
	 */
	public function send( $message ) {

		// 空は送信できない
		if ( empty($message) ) return;

		$data = array(
			"message"	=> $message,
			"password"	=> "",
			"handler"	=> ""
		);

		// 非同期で行うためにマルチ機能を利用(responseを見ない)
		$mh = curl_multi_init();

		// im.kayac.comへの送信設定
		$url = "http://im.kayac.com/api/post/xxxxxxx";
		$ch = curl_init( $url );

		$options = array(
			CURLOPT_POST			=> true,	// POST
			CURLOPT_POSTFIELDS		=> http_build_query($data),
			CURLOPT_RETURNTRANSFER	=> false	// responseを必要としない
		);
		curl_setopt_array($ch, $options);

		// マルチに追加
		curl_multi_add_handle($mh,$ch);

		// ハンドルを実行します
		$active = null;
		do {
			$mrc = curl_multi_exec($mh, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($mh) != -1) {
				do {
					$mrc = curl_multi_exec($mh, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}

		// おかたづけ
		curl_multi_remove_handle($mh, $ch);
		curl_multi_close($mh);

		return;	// 成功したかわからないのでvoid
	}
}
