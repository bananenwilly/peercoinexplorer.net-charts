<?php
require_once("config.php");
require_once("functions.php");

//init db
$db = new Sqlite3($dbFile);

$data = getData();
$coinSupplyNew = array();
$dailyBlocks = array();
$PoWDifficulty = array();
$PoSDifficulty = array();
$blockRatio = array();
$timing = array();
$realTX = array();
$realVOUT = array();
$AddrMintingMining = array();
$MintingMining = array();
$InflationRate = array();
$PowReward = array();

foreach ($data as $index => $block) {
    $blockTime = $block["timeBlock"];
    $day = date("Y-m-d", $blockTime);
    //coinsupply

    $coinSupplyNew[$day]["total"] = $block["coinsupply"];
    $coinSupplyNew[$day]["mining"] = 0;
    $coinSupplyNew[$day]["minting"] = 0;
    if (preg_match("/proof-of-work/", $block["type"])) {
        //found pow block
        $dailyBlocks[$day]["pow"][] = $block;
    } else {
        //found pos block
        $dailyBlocks[$day]["pos"][] = $block;
    }

    if ($index) { //don't wanna go -1 on the genesis block
        $previousBlockTime = $data[$index - 1]["timeBlock"];
        $timeDifference = $blockTime - $previousBlockTime;
        $timing[$day][] = $timeDifference;
    }
}

foreach ($dailyBlocks as $day => $block) {
    $dailyPoWCount = 0;
    $dailyPoSCount = 0;
    $dailyPoWMint = 0;
    $dailyPoSMint = 0;
    $dailyPoWSum = 0;
    $dailyPoSSum = 0;
    $blockRatio[$day] = 0;
    $dailyRealTX = 0;
    $dailyVOUT = 0;
    $PoWAddressArray = array();
    $PoSAddressArray = array();
    $PowReward[$day] = 0;
   
    if (array_key_exists("pow", $block)) {
        $dailyPoWCount = count($block["pow"]);

        foreach ($block["pow"] as $index => $powBlock) {
            $dailyPoWSum += $powBlock["difficulty"];
            $dailyPoWMint += $powBlock["mint"];
            $dailyRealTX += $powBlock["RealTX"];
            $dailyVOUT += $powBlock["RealVOUT"];

            if (!in_array($powBlock["FoundBy"], $PoWAddressArray)) {
                $PoWAddressArray[] = $powBlock["FoundBy"];
            }
        }
        $PoWDifficulty[$day] = $dailyPoWSum / $dailyPoWCount;
        $PowReward[$day] = round($dailyPoWMint / $dailyPoWCount, 2);
    } 
    if (array_key_exists("pos", $block)) {
        $dailyPoSCount = count($block["pos"]);

        foreach ($block["pos"] as $index => $posBlock) {
            $dailyPoSSum += $posBlock["difficulty"];
            $dailyPoSMint += $posBlock["mint"];
            $dailyRealTX += $posBlock["RealTX"];
            $dailyVOUT += $posBlock["RealVOUT"];

            if (!in_array($posBlock["FoundBy"], $PoSAddressArray)) {
                $PoSAddressArray[] = $posBlock["FoundBy"];
            }
        }
        $PoSDifficulty[$day] = $dailyPoSSum / $dailyPoSCount;

        if ($dailyPoWCount) {
            $blockRatio[$day] = round(($dailyPoSCount / $dailyPoWCount), 2);
        }
    }
    $realTX[$day] = $dailyRealTX;
    $realVOUT[$day] = $dailyVOUT;
    $MintingMining[$day]["minting"] = $dailyPoSMint;
    $MintingMining[$day]["mining"] = $dailyPoWMint;
    $AddrMintingMining[$day]["minting"] = count($PoSAddressArray);
    $AddrMintingMining[$day]["mining"] = count($PoWAddressArray);
}

foreach($MintingMining as $day => $block) {
    $oneDayAgo =  date("Y-m-d", strtotime($day) - 86400);
    if (array_key_exists($oneDayAgo, $coinSupplyNew)) {
        $coinSupplyNew[$day]["mining"] = $block["mining"] +  $coinSupplyNew[$oneDayAgo]["mining"];
        $coinSupplyNew[$day]["minting"] = $block["minting"] +  $coinSupplyNew[$oneDayAgo]["minting"];
    } else {
        $coinSupplyNew[$day]["mining"] = $block["mining"];
        $coinSupplyNew[$day]["minting"] = $block["minting"];
    }
}

foreach ($timing as $day => $timeDifference) {
    $blockTiming[$day] = round(((array_sum($timeDifference) / count($timeDifference)) / 60), 2);

    //inflation rate
    $oneYearAgo =  date("Y-m-d", strtotime($day) - 31556926);
    if (array_key_exists($oneYearAgo, $coinSupplyNew)) {
        $InflationRate[$day]["total"] = round((($coinSupplyNew[$day]["total"] - $coinSupplyNew[$oneYearAgo]["total"]) / $coinSupplyNew[$oneYearAgo]["total"]) * 100, 3);
        $InflationRate[$day]["mining"] = round((($coinSupplyNew[$day]["mining"] - $coinSupplyNew[$oneYearAgo]["mining"]) / $coinSupplyNew[$oneYearAgo]["total"]) * 100, 3);
        $InflationRate[$day]["minting"] = round((($coinSupplyNew[$day]["minting"] - $coinSupplyNew[$oneYearAgo]["minting"]) / $coinSupplyNew[$oneYearAgo]["total"]) * 100, 3);
    }
}

//add series
$series1 = array("series" => array("minting", "mining"));
$series2 = array("series" => array("total", "mining", "minting")); 
$AddrMintingMining = $series1 + $AddrMintingMining;
$MintingMining = $series1 + $MintingMining;
$coinSupplyNew = $series2 + $coinSupplyNew;
$InflationRate = $series2 + $InflationRate;

//remove last day, make json and write
file_put_contents("$dataDir/powdifficulty.json", json_encode(array_trim_end($PoWDifficulty)));
file_put_contents("$dataDir/posdifficulty.json", json_encode(array_trim_end($PoSDifficulty)));
file_put_contents("$dataDir/coinsupply.json", json_encode(array_trim_end($coinSupplyNew)));
file_put_contents("$dataDir/blocktiming.json", json_encode(array_trim_end($blockTiming)));
file_put_contents("$dataDir/blockratio.json", json_encode(array_trim_end($blockRatio)));
file_put_contents("$dataDir/realtx.json", json_encode(array_trim_end($realTX)));
file_put_contents("$dataDir/realvalue.json", json_encode(array_trim_end($realVOUT)));
file_put_contents("$dataDir/mintingmining.json", json_encode(array_trim_end($MintingMining)));
file_put_contents("$dataDir/addrmintingmining.json", json_encode(array_trim_end($AddrMintingMining)));
file_put_contents("$dataDir/annualinflation.json", json_encode(array_trim_end($InflationRate)));
file_put_contents("$dataDir/powreward.json", json_encode(array_trim_end($PowReward)));
