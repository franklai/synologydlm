<?php
class TorrentSearchBitSnoop {
	private $qurl = 'http://bitsnoop.com/search/all/';
	public function __construct() {
	}
	public function prepare($curl, $query) {
		$url = $this->qurl . urlencode($query) . '/?fmt=rss';
		curl_setopt($curl, CURLOPT_URL, $url);
	}
	
	public function parse($plugin, $response) {
		return $plugin->addRSSResults($response);
	}
}
?>