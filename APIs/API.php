<?php
	class API {
		protected $url 						= "";
		protected $apikey 					= "";
		protected $apisecret				= "";
		protected $symbol					= "";
		protected $displayname				= "";

		protected $minAcceptableVolume		= 1.02;
		protected $highestBid				= null;
		protected $lowestAsk				= null;
		protected $volumeForHighestBid		= 0.0;
		protected $volumeForLowestAsk		= 0.0;
		protected $balanceBTC				= 0.0;
		protected $balanceLTC				= 0.0;

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
		public function getDisplayname() {
			return $this->displayname;
		}
	}