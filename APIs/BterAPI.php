<?php 
	class BterAPI extends API {

		protected $url 					= "https://data.bter.com/api/1";
		protected $symbol				= "ltc_btc";
		protected $displayname			= "BTER";

		public function __construct() {
			parent::__construct();
			$call 		= sprintf("%s/%s/%s", $this->url, "depth", $this->symbol);
			$ch 		= curl_init($call);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
		    if( ! $result = curl_exec($ch)) { return null; } 
		    
		    $data = json_decode($result, true);

			foreach ($data['bids'] as $bidData) {
				if($bidData[0] > $this->highestBid || false == isset($this->highestBid)) {
					if($bidData[1] >= $this->minAcceptableVolume) {
						$this->highestBid			= $bidData[0];
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

			if($this->dryrun) { $this->getLocalBalance(); } else { $this->getBalance(); }
		}
	}