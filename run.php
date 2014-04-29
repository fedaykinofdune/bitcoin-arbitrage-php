<?php
	date_default_timezone_set('Europe/Amsterdam');
	$cli = false;
	$sapi_type = trim(php_sapi_name());
	if (substr($sapi_type, 0, 3) == 'cli' || substr($sapi_type, 0, 3) == 'cgi') {
	    $cli = true;
	}
	include 'config.php';
	include 'utility.php';

	if(false == $cli) { 
		header( "refresh:10;url=run.php" );
		echo '<html><head><style type="text/css">* { font-family:Courier;}</style></head><body>'; 
		run();
	} else {
		while(true) {
			run();
			sleep(60);
		}		
	}


	function run() {
		global $cli, $config;
		Utility::output(sprintf("\n======== start %0.2f %0.2f %0.2f ===========\n", $config['minimumProfitPerc'], $config['minAcceptableVolume'], $config['buySellVolume']));

		$minimumProfitPerc = $config['minimumProfitPerc'];

		foreach (glob("APIs/*.php") as $filename) {
		    include_once $filename;
		}

		$apis = array(
	//		new BitfinexAPI(),
			new BTCEAPI(),
			new HitBtcAPI(),
			new KrakenAPI(),
	//		new VircurexAPI(),
			new CryptoTradeAPI(),
		);

		$file_db = new PDO('sqlite:'.__DIR__.'/localdata.sqlite3');
		$init = Utility::initializeDatabase($file_db, $apis);
		if($init) { return; }

		foreach ($apis as $api) {
			Utility::output($api);
			Utility::output("\n");
		}

		$totalBalanceBTCBeforeTrades = Utility::getTotalBTC($apis);
		$totalBalanceLTCBeforeTrades = Utility::getTotalLTC($apis);
		
		Utility::output(sprintf("Total BTC before trades: %0.8f\n", $totalBalanceBTCBeforeTrades));
		Utility::output(sprintf("Total LTC before trades: %0.8f\n\n", $totalBalanceLTCBeforeTrades));

		$profits = array();

		$lowestAskAPI = Utility::getLowestAskApi($apis);
		Utility::output(sprintf("Lowest Ask: %s (%0.8f)\n", $lowestAskAPI->getDisplayName(), $lowestAskAPI->getLowestAsk()));

		$highestBidAPI = Utility::getHighestBidApi($apis);
		Utility::output(sprintf("Highest Bid: %s (%0.8f)\n", $highestBidAPI->getDisplayName(), $highestBidAPI->getHighestBid()));

		$highestBid = $highestBidAPI->getHighestBid();
		$lowestAsk = $lowestAskAPI->getLowestAsk();

		$profit = $highestBid - $lowestAsk;
		$profitPerc = $profit / ($lowestAsk / 100);

		$style = "";
		if($profitPerc >= $minimumProfitPerc) {
			$style = "style='font-size: 140%%; font-weight:bold;'";
			$fh = fopen(__DIR__ . "\\found.log", "a+");

			$res = sprintf(
				"buy at %s, sell at %s || %0.8f -> %0.8f || BTC profit per ltc: %0.8f (%0.4f%% profit)",
				$lowestAskAPI->getDisplayName(), 
				$highestBidAPI->getDisplayName(),
				$lowestAsk, 
				$highestBid,
				$profit, 
				$profitPerc
			);
			fwrite($fh, sprintf("[%s] %s\n", date('c'), $res));
			fclose($fh);

	//		if($lowestAskAPI->getDisplayName() != $highestBidAPI->getDisplayName()) {
			if(true){
				$lowestAskAPI->buyLTC($config['buySellVolume']);
				$highestBidAPI->sellLTC($config['buySellVolume']);
				Utility::output(sprintf("\n<b>Bought LTC at %s</b>\n", nl2br($lowestAskAPI)));
				Utility::output(sprintf("<b>Sold LTC at %s</b>\n", nl2br($highestBidAPI)));

				$totalBalanceBTCAfterTrades = Utility::getTotalBTC($apis);
				$totalBalanceLTCAfterTrades = Utility::getTotalLTC($apis);
				Utility::output(sprintf("Total BTC after trades: %0.8f (%0.8f)\n", $totalBalanceBTCAfterTrades, ($totalBalanceBTCAfterTrades - $totalBalanceBTCBeforeTrades)));
				Utility::output(sprintf("Total LTC after trades: %0.8f (%0.8f)\n", $totalBalanceLTCAfterTrades, ($totalBalanceLTCAfterTrades - $totalBalanceLTCBeforeTrades)));

				$lowestAskAPI->transferLTCToAPI($config['buySellVolume'], $highestBidAPI);
				$highestBidAPI->transferBTCToAPI($config['buySellVolume'] * $lowestAsk, $lowestAskAPI);

				$totalBalanceBTCAfterTransfers = Utility::getTotalBTC($apis);
				$totalBalanceLTCAfterTransfers = Utility::getTotalLTC($apis);
				Utility::output(sprintf("Total BTC after transfers: %0.8f (%0.8f)\n", $totalBalanceBTCAfterTransfers, ($totalBalanceBTCAfterTransfers - $totalBalanceBTCAfterTrades)));
				Utility::output(sprintf("Total LTC after transfers: %0.8f (%0.8f)\n", $totalBalanceLTCAfterTransfers, ($totalBalanceLTCAfterTransfers - $totalBalanceLTCAfterTrades)));

				Utility::output(sprintf("\n<b>Transfered LTC from %s</b>\n", nl2br($lowestAskAPI)));
				Utility::output(sprintf("<b>Transfered LTC to %s</b>\n", nl2br($highestBidAPI)));
			}
		} 

		Utility::output(sprintf("<p %s>Highest profit: buy at %s, sell at %s ||  %0.8f BTC per LTC (%0.4f%% profit)</p>\n", $style, $lowestAskAPI->getDisplayName(), $highestBidAPI->getDisplayName(), $profit, $profitPerc));
		Utility::output(sprintf("\n======== end %0.2f %0.2f %0.2f ===========\n", $config['minimumProfitPerc'], $config['minAcceptableVolume'], $config['buySellVolume']));

		if(false == $cli) { echo '</body></html>'; } 
	}
	

	