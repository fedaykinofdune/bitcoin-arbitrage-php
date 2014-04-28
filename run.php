<html>
	<head>
		<style type="text/css">
			* { font-family:Courier;}
		</style>
	</head>
	<body>
		<?php
			header( "refresh:15;url=run.php" );
			$minimumProfitPerc = 1.0;

			foreach (glob("APIs/*.php") as $filename) {
			    include $filename;
			}

			$apis = array(
		//		new BitfinexAPI(),
				new BTCEAPI(),
				new HitBtcAPI(),
				new KrakenAPI(),
		//		new VircurexAPI(),
				new CryptoTradeAPI(),
			);

			foreach ($apis as $api) {
				printf("%s<br>", nl2br($api));
			}

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
		?>
	</body>
</html>