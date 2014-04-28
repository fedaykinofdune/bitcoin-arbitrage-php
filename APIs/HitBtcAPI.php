<?php 
	class HitBtcAPI extends API {

		protected $url 					= "http://api.hitbtc.com";
		protected $apikey 				= "";
		protected $apisecret			= "";
		protected $symbol				= "LTCBTC";
		protected $displayname			= "HITBTC";

		public function __construct() {
			$call 		= sprintf("%s/%s/%s/orderbook", $this->url, "api/1/public", $this->symbol);
			$ch 		= curl_init($call);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
		    if( ! $result = curl_exec($ch)) { return null; } 

		    global $config;
		    $this->apikey 		= $config['keys'][$this->displayname]['key'];
		    $this->apisecret 	= $config['keys'][$this->displayname]['secret'];

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

			$this->getBalance();
		}

		public function getBalance() {
			$interface = new TradingApi($this->apikey, $this->apisecret);

			$result = json_decode($interface->balance(), true);
			foreach ($result['balance'] as $balance) {
					if($balance['currency_code'] == "BTC") {
						$this->balanceBTC = $balance['cash'];
					}
					if($balance['currency_code'] == "LTC") {
						$this->balanceLTC = $balance['cash'];
					}
			}
			return array("btc" => $this->balanceBTC, "ltc" => $this->balanceLTC);
		}

		private function randomString($length) {
		    $key = '';
		    $keys = array_merge(range(0, 9), range('a', 'z'));
		    for ($i = 0; $i < $length; $i++) {
		        $key .= $keys[array_rand($keys)];
		    }
		    return $key;
		}
 
// 		echo $interface->new_order(array(
//         'clientOrderId' => randomString(rand(8, 30)),
//         'symbol' => 'BTCUSD',
//         'side' => 'sell',
//         'price' => 10000.1,
//         'quantity' => 1, // 1 lot => 0.01 BTC
//         'type' => 'limit',
//         'timeInForce' => 'GTC'
//     	));
	}
 
	class TradingApi {
	 
	    CONST HITBTC_API_URL = 'http://api.hitbtc.com';
	    CONST HITBTC_TRADING_API_URL_SEGMENT = '/api/1/trading/';
	 
	    private $_key, $_secret;
	 
	    private $_availableMethods = array(
	        'balance',
	        'orders/active',
	        'new_order',
	        'cancel_order',
	        'trades',
	        'orders/recent',
	    );
	 
	    private $_postMethods = array(
	        'new_order'
	    );
	 
	    public function __construct($key, $secret)
	    {
	        $this->_key = $key;
	        $this->_secret = $secret;
	    }
	 
	    public function __call($name, $arguments) {
	 
	        $methodPathParts = preg_split('/(?=[A-Z])/', $name);
	 
	        $methodPathParts = array_map(
	            function($pathSegment) { return strtolower($pathSegment); },
	            $methodPathParts
	        );
	 
	        $method = implode('/', $methodPathParts);
	 
	        if(!in_array($method, $this->_availableMethods)){
	            throw new \Exception( 'Method that you try to call doesn\'t exists!' );
	        }
	 
	        return $this->_request($method, $arguments, in_array($method, $this->_postMethods));
	 
	    }
	 
	    private function _request($method, $arguments, $isPost = FALSE)
	    {
	        $requestUri = self::HITBTC_TRADING_API_URL_SEGMENT
	            . $method
	            . '?nonce=' . $this->_nonce()
	            . '&apikey=' . $this->_key;
	 
	        $arguments = sizeof($arguments) > 0 ? $arguments[0] : array();
	        $params = http_build_query($arguments);
	 
	        if (strlen($params) && $isPost === FALSE) {
	            $requestUri .= '&' . $params;
	        }
	 
	        $ch = curl_init();
	        curl_setopt_array($ch, array(
	            CURLOPT_URL => self::HITBTC_API_URL . $requestUri,
	            CURLOPT_CONNECTTIMEOUT => 10,
	            CURLOPT_RETURNTRANSFER => 1
	        ));
	 
	        if($isPost) {
	            curl_setopt($ch, CURLOPT_POST, TRUE);
	            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	        }
	 
	        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Signature: ' . $this->_signature($requestUri, $isPost ? $params : '')));
	 
	        $result = curl_exec($ch);
	        curl_close($ch);
	        return $result;
	    }
	 
	    private function _signature($uri, $postData)
	    {
	        return strtolower(hash_hmac('sha512', $uri . $postData, $this->_secret));
	    }
	 
	    private function _nonce()
	    {
	        return time();
	    }
	 
	}
