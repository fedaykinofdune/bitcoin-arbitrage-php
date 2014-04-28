<html>
	<head>
		<style type="text/css">
			* { font-family:Courier;}
		</style>
	</head>
	<body>
		<?php
			header( "refresh:10;url=run.php" );

			include 'config.php';
			$minimumProfitPerc = $config['minimumProfitPerc'];
			$sellVolume = $config['sellVolume'];

			foreach (glob("APIs/*.php") as $filename) {
			    include $filename;
			}

			$apis = array(
				new BitfinexAPI(),
				new BTCEAPI(),
				new HitBtcAPI(),
				new KrakenAPI(),
		//		new VircurexAPI(),
				new CryptoTradeAPI(),
			);

			$file_db = new PDO('sqlite:'.__DIR__.'/localdata.sqlite3');
			initializeDatabase($file_db, $apis);

			foreach ($apis as $api) {
				printf("%s<br>", nl2br($api));
			}

			$totalBalanceBTCBeforeTrades = getTotalBTC($apis);
			$totalBalanceLTCBeforeTrades = getTotalLTC($apis);
			
			printf("Total BTC before trades: %0.8f<BR>", $totalBalanceBTCBeforeTrades);
			printf("Total LTC before trades: %0.8f<BR><BR>", $totalBalanceLTCBeforeTrades);

			$profits = array();

			usort($apis, 'apiSortLowestAskAsc'); 
			$lowestAskAPI = $apis[0];
			printf("Lowest Ask: %s (%0.8f)<br>", $lowestAskAPI->getDisplayName(), $lowestAskAPI->getLowestAsk());

			usort($apis, 'apiSortHighestBidDesc'); 
			$highestBidAPI = $apis[0];
			printf("Highest Bid: %s (%0.8f)<br>", $highestBidAPI->getDisplayName(), $highestBidAPI->getHighestBid());

			$highestBid = $highestBidAPI->getHighestBid();
			$lowestAsk = $lowestAskAPI->getLowestAsk();

			$profit = $highestBid - $lowestAsk;
			$profitPerc = $profit / ($lowestAsk / 100);

			$style = "";
			if($profitPerc >= $minimumProfitPerc) {
				$style = "style='font-size: 140%%; font-weight:bold;'";
				$fh = fopen("C:\\wamp\\www\\arbitrage\\found.log", "a+");

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
					printf("<br><b>Bought LTC at %s</b><br>", nl2br($lowestAskAPI));
					printf("<b>Sold LTC at %s</b><br>", nl2br($highestBidAPI));

					$totalBalanceBTCAfterTrades = getTotalBTC($apis);
					$totalBalanceLTCAfterTrades = getTotalLTC($apis);
					printf("Total BTC after trades: %0.8f (%0.8f)<BR>", $totalBalanceBTCAfterTrades, ($totalBalanceBTCAfterTrades - $totalBalanceBTCBeforeTrades));
					printf("Total LTC after trades: %0.8f (%0.8f)<BR>", $totalBalanceLTCAfterTrades, ($totalBalanceLTCAfterTrades - $totalBalanceLTCBeforeTrades));

					$lowestAskAPI->transferLTCToAPI($config['buySellVolume'], $highestBidAPI);
					$highestBidAPI->transferBTCToAPI($config['buySellVolume'] * $lowestAsk, $lowestAskAPI);

					$totalBalanceBTCAfterTransfers = getTotalBTC($apis);
					$totalBalanceLTCAfterTransfers = getTotalLTC($apis);
					printf("Total BTC after transfers: %0.8f (%0.8f)<BR>", $totalBalanceBTCAfterTransfers, ($totalBalanceBTCAfterTransfers - $totalBalanceBTCAfterTrades));
					printf("Total LTC after transfers: %0.8f (%0.8f)<BR>", $totalBalanceLTCAfterTransfers, ($totalBalanceLTCAfterTransfers - $totalBalanceLTCAfterTrades));

					printf("<br><b>Transfered LTC from %s</b><br>", nl2br($lowestAskAPI));
					printf("<b>Transfered LTC to %s</b><br>", nl2br($highestBidAPI));
				}
			} 

			printf("<p %s>Highest profit: buy at %s, sell at %s ||  %0.8f BTC per LTC (%0.4f%% profit)</p>", $style, $lowestAskAPI->getDisplayName(), $highestBidAPI->getDisplayName(), $profit, $profitPerc);


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

		?>
	</body>
</html>