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

		private function getBalance() {
			$res = $this->bter_query ('1/private/getfunds');
//			print_r($res);
			$this->balanceBTC = $res['available_funds']['BTC'];
			$this->balanceLTC = $res['available_funds']['LTC'];
			return array("btc" => $this->balanceBTC, "ltc" => $this->balanceLTC);
		}

		public function buyLTC($ltcAmount) {
			$res = $this->bter_query (
				'1/private/placeorder', 
				array(
					'pair' => $this->symbol,
					'type' => "BUY",
					'rate' => $this->lowestAsk,
					'amount' => $ltcAmount
				)
			);
			print_r($res);
		}

		public function sellLTC($ltcAmount) {
			$res = $this->bter_query (
				'1/private/placeorder', 
				array(
					'pair' => $this->symbol,
					'type' => "SELL",
					'rate' => $this->highestBid,
					'amount' => $ltcAmount
				)
			);
			print_r($res);
		}

		function bter_query($path, array $req = array()) {
			// API settings, add your Key and Secret at here
			$key = $this->apikey;
			$secret = $this->apisecret;
		 
			// generate a nonce to avoid problems with 32bits systems
			$mt = explode(' ', microtime());
			$req['nonce'] = $mt[1].substr($mt[0], 2, 6);
		 
			// generate the POST data string
			$post_data = http_build_query($req, '', '&');
			$sign = hash_hmac('sha512', $post_data, $secret);
		 
			// generate the extra headers
			$headers = array(
				'KEY: '.$key,
				'SIGN: '.$sign,
			);

			//!!! please set Content-Type to application/x-www-form-urlencoded if it's not the default value

			// curl handle (initialize if required)
			static $ch = null;
			if (is_null($ch)) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_USERAGENT, 
					'Mozilla/4.0 (compatible; Bter PHP bot; '.php_uname('a').'; PHP/'.phpversion().')'
					);
			}
			curl_setopt($ch, CURLOPT_URL, 'https://bter.com/api/'.$path);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

			// run the query
			$res = curl_exec($ch);

			//echo $res;
			$dec = json_decode($res, true);
			return $dec;
		}
	}