<?php
/*

sendKayac($message) … $messageに任意の文字列を設定

戻り値：0 　　正常
　　　　0以外 エラー

*/
Class ImKayac{
	public function sendKayac($message) {
		$data = array(
			"message" => $message,
			"sig" => ""
		);


		$data['sig'] = sha1($data['message'] . "yayoikawaii3255");


		$data = http_build_query($data, "", "&");

		$header = array(
			"Content-Type: application/x-www-form-urlencoded",
			"Content-Length: ".strlen($data)
		);

		$context = array(
			"http" => array(
				"method" => "POST",
				"header" => implode("\r\n", $header),
				"content" => $data
			)
		);

		$url = "http://im.kayac.com/api/post/zishinbot";
		$post_status_json = file_get_contents($url, false, stream_context_create($context));

		$post_status = json_decode($post_status_json, true);

		$ret = strcmp($post_status['result'], "posted");
		return $ret;
	}
}
