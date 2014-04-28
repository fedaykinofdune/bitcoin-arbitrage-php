<?php 
	class VircurexAPI extends API {

		protected $url 					= "https://api.vircurex.com/api";
		protected $apikey 				= "";
		protected $apisecret			= "";
		protected $symbol1				= "LTC";
		protected $symbol2				= "BTC";
		protected $displayname			= "VIRCUREX";

		public function __construct() {
			$call 		= sprintf("%s/orderbook.json?base=%s&alt=%s", $this->url, $this->symbol1, $this->symbol2);
			$ch 		= curl_init($call);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
		    if( ! $result = curl_exec($ch)) { return null; } 

		    $data = json_decode($result, true);

			foreach ($data['bids'] as $bidData) {
				if($bidData[0] > $this->highestBid || false == isset($this->highestBid)) {
					if($bidData[1] >= $this->minAcceptableVolume) {
						$this->highestBid 			= $bidData[0];
						$this->volumeForHighestBid += $bidData[1];
					}
				}
			}
			foreach ($data['asks'] as $askData) {
				if($askData[0] <= $this->lowestAsk || false == isset($this->lowestAsk)) {
					if($askData[1] >= $this->minAcceptableVolume) {
						$this->lowestAsk 		   = $askData[0];
						$this->volumeForLowestAsk += $askData[1];
					}
				}
			}
		}
	}