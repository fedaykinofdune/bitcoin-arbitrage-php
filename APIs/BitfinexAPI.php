<?php 
	class BitfinexAPI extends API {

		protected $url 					= "https://api.bitfinex.com/v1";
		protected $symbol				= "ltcbtc";
		protected $displayname			= "BITFINEX";

		public function __construct() {
			parent::__construct();
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

			if($this->dryrun) { $this->getLocalBalance(); } else { $this->getBalance(); }
		}

		private function getBalance() {
			$array = $this->bitfinex_query("balances");
			foreach ($array as $balance) {
				if($balance['type'] == 'exchange' && $balance['currency'] == 'btc') {
					$this->balanceBTC = $balance['available'];
				}
				if($balance['type'] == 'exchange' && $balance['currency'] == 'ltc') {
					$this->balanceLTC = $balance['available'];
				}
			}
			return array("btc" => $this->balanceBTC, "ltc" => $this->balanceLTC);
		}

		public function buyLTC($ltcAmount) {
			$array = $this->bitfinex_query("order/new", array(
				"symbol" 	=> "ltcbtc", 
				"amount" 	=> $ltcAmount, 
				"price" 	=> $this->lowestAsk, 
				"exchange" 	=> "bitfinex",
				"side" 		=> "buy",
				"type" 		=> "exchange limit"
			));
			print_r($array);
		}

		public function sellLTC($ltcAmount) {
			$array = $this->bitfinex_query("order/new", array(
				"symbol" 	=> "ltcbtc", 
				"amount" 	=> $ltcAmount, 
				"price" 	=> $this->highestBid, 
				"exchange" 	=> "bitfinex",
				"side" 		=> "sell",
				"type" 		=> "exchange limit"
			));
			print_r($array);
		}

		private function bitfinex_query($request, $extra_data = array()) {
			$apiUrl	= sprintf("%s/%s", $this->url, $request);
			$apiKey = $this->apikey;
	        $apiSecret = $this->apisecret;

			$payload = array(
								'request' => sprintf('/v1/%s', $request),
								'nonce' => strval(time() * 100000),
							);
			$payload = array_merge($payload, $extra_data);
			$payload = base64_encode(json_encode($payload));
			$signature = hash_hmac('sha384', $payload, $apiSecret);
			$headers = array(
								"X-BFX-APIKEY : " . $apiKey,
								"X-BFX-PAYLOAD : " . $payload,
								"X-BFX-SIGNATURE : " . $signature,
							);
							
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_POSTFIELDS, '');
			curl_setopt($curl, CURLOPT_POST, 0);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);      
			curl_setopt ($curl, CURLOPT_URL, $apiUrl);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$curlResult = curl_exec ($curl);
			curl_close($curl);

			return json_decode($curlResult, true);
		}
	}