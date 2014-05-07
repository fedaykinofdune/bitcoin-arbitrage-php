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

		function getBalance() {
			$array = $this->api_query("getinfo");

			foreach ($array['return']['balances_available'] as $key => $balance) {
				if($key == 'BTC') {
					$this->balanceBTC = $balance;
				}
				if($key == 'LTC') {
					$this->balanceLTC = $balance;
				}
			}
			return array("btc" => $this->balanceBTC, "ltc" => $this->balanceLTC);
		}

		public function buyLTC($ltcAmount) {
			$array = $this->api_query("createorder", array(
				"marketid" 	=> 3, 
				"ordertype" => 'Buy', 
				"quantity" 	=> $ltcAmount, 
				"price" 	=> $this->lowestAsk
			));
			print_r($array);
		}

		public function sellLTC($ltcAmount) {
			$array = $this->api_query("createorder", array(
				"marketid" 	=> 3, 
				"ordertype" => 'Sell', 
				"quantity" 	=> $ltcAmount, 
				"price" 	=> $this->highestBid
			));
			print_r($array);
		}

		function api_query($method, array $req = array()) {
			// API settings
			$key = $this->apikey;
			$secret = $this->apisecret;
			$req['method'] = $method;
			$mt = explode(' ', microtime());
			$req['nonce'] = $mt[1];
			// generate the POST data string
			$post_data = http_build_query($req, '', '&');
			$sign = hash_hmac("sha512", $post_data, $secret);
			// generate the extra headers
			$headers = array(
				'Sign: '.$sign,
				'Key: '.$key,
			);
			// our curl handle (initialize if required)
			static $ch = null;
			if (is_null($ch)) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Cryptsy API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
			}
			curl_setopt($ch, CURLOPT_URL, 'https://www.cryptsy.com/api');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			// run the query
			$res = curl_exec($ch);
		
//			if ($res === false) { return; } //throw new Exception('Could not get reply: '.curl_error($ch));
			$dec = json_decode($res, true);
//			if (!$dec) throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
			return $dec;
		}
	}