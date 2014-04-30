<?php
	class Utility {
		static function output($output) {
			global $cli;
			if($cli) {
				print(strip_tags($output));
			} else {
				print(nl2br($output));
			}
		}

		static function apiSortLowestAskAsc($a, $b) {
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
		static function apiSortHighestBidDesc($a, $b) { 
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

		static function initializeDatabase($db, $apis) {
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='balances';");
			if ($result) {
				if(false == $result->fetch(PDO::FETCH_ASSOC)) {
					Utility::output(sprintf("Creating SQLite database, then quiting.\n"));
					$db->exec("CREATE TABLE IF NOT EXISTS balances (
						key TEXT PRIMARY KEY,	
						btc TEXT, 
						ltc TEXT, 
						lasttrade INTEGER)");

					$insert = "INSERT INTO balances (key, btc, ltc, lasttrade) VALUES (:key, :btc, :ltc, :lasttrade)";
					$stmt = $db->prepare($insert);
					foreach ($apis as $api) {
						$stmt->bindValue(':key', $api->getDisplayName(), PDO::PARAM_STR);
						$stmt->bindValue(':btc', 0.25);
						$stmt->bindValue(':ltc', 10.0);
						$stmt->bindValue(':lasttrade', NULL);
						$stmt->execute();
					}
					return true;
				}
			}
			return false;
		}

		static function getTotalLTC($apis) {
			$totalFunds = 0.0;
			foreach ($apis as $api) {
				$totalFunds += $api->getBalanceLTC();
			}
			return $totalFunds;
		}

		static function getTotalBTC($apis) {
			$totalFunds = 0.0;
			foreach ($apis as $api) {
				$totalFunds += $api->getBalanceBTC();
			}
			return $totalFunds;
		}

		static function getLowestAskApi($apis) {
			usort($apis, array('Utility', 'apiSortLowestAskAsc')); 
			return $apis[0];
		}

		static function getHighestBidApi($apis) {
			usort($apis, array('Utility', 'apiSortHighestBidDesc')); 
			return $apis[0];
		}
	}
