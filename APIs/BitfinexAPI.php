<?php 
	class BitfinexAPI extends API {

		protected $url 					= "https://api.bitfinex.com/v1";
		protected $apikey 				= "";
		protected $apisecret			= "";
		protected $symbol				= "ltcbtc";
		protected $displayname			= "BITFINEX";

		public function __construct() {
			$call 		= sprintf("%s/%s/%s", $this->url, "book", $this->symbol);
			$ch 		= curl_init($call);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
		    if( ! $result = curl_exec($ch)) { return null; } 

		    $data = json_decode($result, true);

			foreach ($data['bids'] as $bidData) {
				if($bidData['price'] > $this->highestBid || false == isset($this->highestBid)) {
					if($bidData['amount'] >= $this->minAcceptableVolume) {
						$this->highestBid			= $bidData['price'];
						$this->volumeForHighestBid += $bidData['amount'];
					}
				}
			}
			foreach ($data['asks'] as $askData) {
				if($askData['price'] <= $this->lowestAsk || false == isset($this->lowestAsk)) {
					if($askData['amount'] >= $this->minAcceptableVolume) {
						$this->lowestAsk 		   = $askData['price'];
						$this->volumeForLowestAsk += $askData['amount'];
					}
				}
			}
		}
	}