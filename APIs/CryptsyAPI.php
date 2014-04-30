<?php 
	class CryptsyAPI extends API {

		protected $url 					= "http://pubapi.cryptsy.com/api.php?method=";
		protected $symbol				= "3";
		protected $displayname			= "CRYPTSY";

		public function __construct() {
			parent::__construct();
			$call 		= sprintf("%s%s&marketid=%s", $this->url, "singleorderdata", 3);
			$ch 		= curl_init($call);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
		    if( ! $result = curl_exec($ch)) { return null; } 
		    
		    $data = json_decode($result, true);

			foreach ($data['return']['LTC']['buyorders'] as $bidData) {
				if($bidData['price'] > $this->highestBid || false == isset($this->highestBid)) {
					if($bidData['quantity'] >= $this->minAcceptableVolume) {
						$this->highestBid			= $bidData['price'];
						$this->volumeForHighestBid += $bidData['quantity'];
					}
				}
			}
			foreach ($data['return']['LTC']['sellorders'] as $askData) {
				if($askData['price'] <= $this->lowestAsk || false == isset($this->lowestAsk)) {
					if($askData['quantity'] >= $this->minAcceptableVolume) {
						$this->lowestAsk 		   = $askData['price'];
						$this->volumeForLowestAsk += $askData['quantity'];
					}
				}
			}

			if($this->dryrun) { $this->getLocalBalance(); } else { $this->getBalance(); }
		}
	}