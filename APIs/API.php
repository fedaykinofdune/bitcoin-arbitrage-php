<?php
	class API {
		protected	$url 					= "";
		protected	$apikey 				= "";
		protected	$apisecret				= "";
		protected	$symbol					= "";
		protected	$displayname			= "";

		protected	$minAcceptableVolume	= 1.02;
		protected	$highestBid				= null;
		protected	$lowestAsk				= null;
		protected	$volumeForHighestBid	= 0.0;
		protected	$volumeForLowestAsk		= 0.0;
		protected	$balanceBTC				= 0.0;
		protected	$balanceLTC				= 0.0;
		protected	$tradingFee				= 0.002;
		protected	$btcTransferFee			= 0.0005;
		protected	$ltcTransferFee			= 0.01;

		protected	$dryrun					= false;

		private		$file_db				= false;


		public function __construct() {
		    global $config;
		    $this->apikey 				= $config['keys'][$this->displayname]['key'];
		    $this->apisecret 			= $config['keys'][$this->displayname]['secret'];
		    $this->tradingFee 			= $config['keys'][$this->displayname]['tradingFee'];
		    $this->btcTransferFee 		= $config['keys'][$this->displayname]['btcTransferFee'];
		    $this->ltcTransferFee 		= $config['keys'][$this->displayname]['ltcTransferFee'];
		    $this->minAcceptableVolume	= $config['minAcceptableVolume'];
		    $this->dryrun				= $config['dryrun'];
		}

		public function __toString() {
			$s =  sprintf("%s\n", $this->displayname);
			$s .= sprintf("bid: %0.8f (vol: %0.8f)\n", $this->highestBid, $this->volumeForHighestBid);
			$s .= sprintf("ask: %0.8f (vol: %0.8f)\n", $this->lowestAsk, $this->volumeForLowestAsk);
			$s .= sprintf("balance BTC: %0.8f\nbalance LTC: %0.8f\n", $this->balanceBTC, $this->balanceLTC);
			return $s;
		}

		public function getHighestBid() {
			return $this->highestBid;
		}
		public function getLowestAsk() {
			return $this->lowestAsk;
		}
		public function getVolumeForHighestBid() {
			return $this->volumeForHighestBid;
		}
		public function getVolumeForLowestAsk() {
			return $this->volumeForLowestAsk;
		}
		public function getBalanceLTC() {
			return $this->balanceLTC;
		}
		public function getBalanceBTC() {
			return $this->balanceBTC;
		}
		public function getDisplayname() {
			return $this->displayname;
		}

		public function getLocalBalance() {
			if(false == $this->file_db) {
				$this->file_db = new PDO('sqlite:'.__DIR__.'/../localdata.sqlite3');
			}

			$query = sprintf("SELECT * FROM balances WHERE key = '%s';", $this->displayname);
			$result = $this->file_db->query($query);
			if(false == $result) {
				$this->balanceBTC = 0.0;
				$this->balanceLTC = 0.0;
				return;
			}
			foreach($result as $row) {
				$this->balanceBTC = $row['btc'];
				$this->balanceLTC = $row['ltc'];
			}
		}

		public function storeLocalBalance() {
			if(false == $this->file_db) {
				$this->file_db = new PDO('sqlite:'.__DIR__.'/../localdata.sqlite3');
			}

			$query = "UPDATE balances SET btc = :btc, ltc = :ltc WHERE key = :key;";
		    $stmt = $this->file_db->prepare($query);
			$stmt->bindValue(':key', $this->getDisplayName(), PDO::PARAM_STR);
			$stmt->bindValue(':btc', $this->getBalanceBTC(), PDO::PARAM_STR);
			$stmt->bindValue(':ltc', $this->getBalanceLTC(), PDO::PARAM_STR);
			$stmt->execute();

		}

		public function buyLTC($ltcAmount) {
			$this->balanceLTC += ($ltcAmount * (1 - $this->tradingFee));
			$this->balanceBTC -= ($ltcAmount * $this->lowestAsk);
			if($this->dryrun) { $this->storeLocalBalance(); }
		}

		public function sellLTC($ltcAmount) {
			$this->balanceLTC -= ($ltcAmount);
			$this->balanceBTC += (($ltcAmount * $this->highestBid) * (1 - $this->tradingFee));
			if($this->dryrun) { $this->storeLocalBalance(); }
		}

		public function transferLTCToAPI($ltcAmount, $receivingApi) {
			printf("<p><u>Transfering %0.8f LTC from %s to %s</u></p>", $ltcAmount, $this->getDisplayName(), $receivingApi->getDisplayName());
			$this->balanceLTC -= ($ltcAmount);
			$receivingApi->receiveLTC($ltcAmount - $this->ltcTransferFee);
			if($this->dryrun) { $this->storeLocalBalance(); }
		}

		public function transferBTCToAPI($btcAmount, $receivingApi) {
			printf("<p><u>Transfering %0.8f BTC from %s to %s</u></p>", $btcAmount, $this->getDisplayName(), $receivingApi->getDisplayName());
			$this->balanceBTC -= ($btcAmount);
			$receivingApi->receiveBTC($btcAmount - $this->btcTransferFee);
			if($this->dryrun) { $this->storeLocalBalance(); }
		}
		public function receiveLTC($amount) {
			$this->balanceLTC += ($amount);
			if($this->dryrun) { $this->storeLocalBalance(); }
		}
		public function receiveBTC($amount) {
			$this->balanceBTC += ($amount);
			if($this->dryrun) { $this->storeLocalBalance(); }
		}
	}