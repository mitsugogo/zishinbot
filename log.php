<?php
Class Logging{
	public function tweetLog($str){
		$now = date("Y-m-d H:i:s");
		exec('logger -p local6.info "[' . $now . '] ' . $str . '"');
	}
	public function systemLog($str){
		$now = date("Y-m-d H:i:s");
		exec('logger -p local6.warn "[' . $now . '] ' . $str . '"');
	}
}
