<?php 
	class BTCEAPI extends API {

		protected $url 					= "https://btc-e.com/api/2";
		protected $symbol				= "ltc_btc";
		protected $displayname			= "BTC_E";

		private   $privateApiUrl		= "https://btc-e.com/tapi";

		public function __construct() {
			parent::__construct();
			$call 		= sprintf("%s/%s/depth", $this->url, $this->symbol);
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

			if($this->dryrun) { $this->getLocalBalance(); } else { $this->getBalance(); }
		}

		public function getBalance() {
			$info = $this->btce_query("getInfo");

			$this->balanceBTC = $info['return']['funds']['btc'];
			$this->balanceLTC = $info['return']['funds']['ltc'];
			return array("btc" => $this->balanceBTC, "ltc" => $this->balanceLTC);
		}

		private function btce_query($method, array $req = array()) {
	        $key = $this->apikey;
	        $secret = $this->apisecret;
	 
	        $req['method'] = $method;
	        $mt = explode(' ', microtime());
	        $req['nonce'] = $mt[1];
	       
	        $post_data = http_build_query($req, '', '&');
	        $sign = hash_hmac('sha512', $post_data, $secret);
	        $headers = array('Sign: '.$sign, 'Key: '.$key);
	 
	        static $ch = null;
	        if (is_null($ch)) {
	                $ch = curl_init();
	                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; BTCE PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	        }
	        curl_setopt($ch, CURLOPT_URL, $this->privateApiUrl);
	        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	 
	        // run the query
	        $res = curl_exec($ch);
	        if ($res === false) throw new Exception('Could not get reply: '. curl_error($ch));
	        $dec = json_decode($res, true);
	        if (!$dec) throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
	        return $dec;
		}
	}