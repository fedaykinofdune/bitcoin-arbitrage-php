<?php
	$cli = false;
	$sapi_type = trim(php_sapi_name());
	if (substr($sapi_type, 0, 3) == 'cli') {
	    $cli = true;
	}
	include 'config.php';

	if(false == $cli) { 
		header( "refresh:10;url=run.php" );
		echo '<html><head><style type="text/css">* { font-family:Courier;}</style></head><body>'; 
		run();
	} else {
		while(true) {
			output("\n===================\n");
			run();
			sleep(5);
		}		
	}


	function run() {
		global $cli, $config;

		$minimumProfitPerc = $config['minimumProfitPerc'];
		$sellVolume = $config['sellVolume'];

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
		initializeDatabase($file_db, $apis);

		foreach ($apis as $api) {
			output($api);
			output("\n");
		}

		$totalBalanceBTCBeforeTrades = getTotalBTC($apis);
		$totalBalanceLTCBeforeTrades = getTotalLTC($apis);
		
		output(sprintf("Total BTC before trades: %0.8f\n", $totalBalanceBTCBeforeTrades));
		output(sprintf("Total LTC before trades: %0.8f\n\n", $totalBalanceLTCBeforeTrades));

		$profits = array();

		usort($apis, 'apiSortLowestAskAsc'); 
		$lowestAskAPI = $apis[0];
		output(sprintf("Lowest Ask: %s (%0.8f)\n", $lowestAskAPI->getDisplayName(), $lowestAskAPI->getLowestAsk()));

		usort($apis, 'apiSortHighestBidDesc'); 
		$highestBidAPI = $apis[0];
		output(sprintf("Highest Bid: %s (%0.8f)\n", $highestBidAPI->getDisplayName(), $highestBidAPI->getHighestBid()));

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
				output(sprintf("\n<b>Bought LTC at %s</b>\n", nl2br($lowestAskAPI)));
				output(sprintf("<b>Sold LTC at %s</b>\n", nl2br($highestBidAPI)));

				$totalBalanceBTCAfterTrades = getTotalBTC($apis);
				$totalBalanceLTCAfterTrades = getTotalLTC($apis);
				output(sprintf("Total BTC after trades: %0.8f (%0.8f)\n", $totalBalanceBTCAfterTrades, ($totalBalanceBTCAfterTrades - $totalBalanceBTCBeforeTrades)));
				output(sprintf("Total LTC after trades: %0.8f (%0.8f)\n", $totalBalanceLTCAfterTrades, ($totalBalanceLTCAfterTrades - $totalBalanceLTCBeforeTrades)));

				$lowestAskAPI->transferLTCToAPI($config['buySellVolume'], $highestBidAPI);
				$highestBidAPI->transferBTCToAPI($config['buySellVolume'] * $lowestAsk, $lowestAskAPI);

				$totalBalanceBTCAfterTransfers = getTotalBTC($apis);
				$totalBalanceLTCAfterTransfers = getTotalLTC($apis);
				output(sprintf("Total BTC after transfers: %0.8f (%0.8f)\n", $totalBalanceBTCAfterTransfers, ($totalBalanceBTCAfterTransfers - $totalBalanceBTCAfterTrades)));
				output(sprintf("Total LTC after transfers: %0.8f (%0.8f)\n", $totalBalanceLTCAfterTransfers, ($totalBalanceLTCAfterTransfers - $totalBalanceLTCAfterTrades)));

				output(sprintf("\n<b>Transfered LTC from %s</b>\n", nl2br($lowestAskAPI)));
				output(sprintf("<b>Transfered LTC to %s</b>\n", nl2br($highestBidAPI)));
			}
		} 

		output(sprintf("<p %s>Highest profit: buy at %s, sell at %s ||  %0.8f BTC per LTC (%0.4f%% profit)</p>\n", $style, $lowestAskAPI->getDisplayName(), $highestBidAPI->getDisplayName(), $profit, $profitPerc));

		if(false == $cli) { echo '</body></html>'; } 
	}
	function output($output) {
		global $cli;
		if($cli) {
			print(strip_tags($output));
		} else {
			print(nl2br($output));
		}
	}

	function apiSortLowestAskAsc($a, $b) {
	    if ($a->getLowestAsk() == 0.0) {
	    	return 1;
	    } else if ($b->getLowestAsk() == 0.0) {
	    	return -1;
	    } // move 0.0 values down

	    if ($a->getLowestAsk() == $b->getLowestAsk()) {
	        return 0;
	    }
	    return ($a->getLowestAsk() < $b->getLowestAsk()) ? -1 : 1;
	}
	function apiSortHighestBidDesc($a, $b) { 
	    if ($a->getHighestBid() == 0.0) {
	    	return 1;
	    } else if ($b->getHighestBid() == 0.0) {
	    	return -1;
	    } // move 0.0 values down


	    if ($a->getHighestBid() == $b->getHighestBid()) {
	        return 0;
	    }
	    return ($a->getHighestBid() > $b->getHighestBid()) ? -1 : 1;
	}

	function initializeDatabase($db, $apis) {
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='balances';");
		if ($result) {
				if(false == $result->fetch(PDO::FETCH_ASSOC)) {
					echo "creating";
				$db->exec("CREATE TABLE IF NOT EXISTS balances (
                    key TEXT PRIMARY KEY,
                    btc TEXT, 
                    ltc TEXT)");

			    $insert = "INSERT INTO balances (key, btc, ltc) VALUES (:key, :btc, :ltc)";
			    $stmt = $db->prepare($insert);
		        foreach ($apis as $api) {
					$stmt->bindValue(':key', $api->getDisplayName(), PDO::PARAM_STR);
					$stmt->bindValue(':btc', 0.25);
					$stmt->bindValue(':ltc', 10.0);
					$stmt->execute();
				}
			}
			}
	}

	function getTotalLTC($apis) {
		$totalFunds = 0.0;
		foreach ($apis as $api) {
			$totalFunds += $api->getBalanceLTC();
		}
		return $totalFunds;
	}

	function getTotalBTC($apis) {
		$totalFunds = 0.0;
		foreach ($apis as $api) {
			$totalFunds += $api->getBalanceBTC();
		}
		return $totalFunds;
	}


	