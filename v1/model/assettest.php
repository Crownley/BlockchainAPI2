<?php

require_once('asset.php');

try {
    $asset = new Asset(100, "Blockchain", 3, "BTC");
    header('Content-type: application/json;charset=UTF-8');
    echo json_encode($asset->returnAssetAsArray());
} catch (AssetException $ex) {
    echo "Error: " . $ex->getMessage();
}