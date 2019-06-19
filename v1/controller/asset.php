<?php

require_once('db.php');
require_once('../model/asset.php');
require_once('../model/response.php');

// attempt to set up connections to read and write db connections
try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    // log connection error for troubleshooting and return a json error response
    error_log("Connection Error: " . $ex, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database connection error");
    $response->send();
    exit;
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////
// BEGIN OF AUTH SCRIPT
// Authenticate user with access token
// check to see if access token is provided in the HTTP Authorization header and that the value is longer than 0 chars
// don't forget the Apache fix in .htaccess file
if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
    (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
    $response->send();
    exit;
}

// get supplied access token from authorisation header - used for delete (log out) and patch (refresh)
$accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

// attempt to query the database to check token details - use write connection as it needs to be synchronous for token
try {
    // create db query to check access token is equal to the one provided
    $query = $writeDB->prepare('select userid, accesstokenexpiry, useractive, loginattempts from tblsessions, tblusers where tblsessions.userid = tblusers.id and accesstoken = :accesstoken');
    $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
    $query->execute();

    // get row count
    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        // set up response for unsuccessful log out response
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("Invalid access token");
        $response->send();
        exit;
    }

    // get returned row
    $row = $query->fetch(PDO::FETCH_ASSOC);

    // save returned details into variables
    $returned_userid = $row['userid'];
    $returned_accesstokenexpiry = $row['accesstokenexpiry'];
    $returned_useractive = $row['useractive'];
    $returned_loginattempts = $row['loginattempts'];

    // check if account is active
    if ($returned_useractive != 'Y') {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("User account is not active");
        $response->send();
        exit;
    }

    // check if account is locked out
    if ($returned_loginattempts >= 3) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("User account is currently locked out");
        $response->send();
        exit;
    }

    // check if access token has expired
    if (strtotime($returned_accesstokenexpiry) < time()) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("Access token has expired");
        $response->send();
        exit;
    }
} catch (PDOException $ex) {
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("There was an issue authenticating - please try again");
    $response->send();
    exit;
}

// END OF AUTH SCRIPT
///////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////
// check if walletid is in the url e.g. /assets/1
if (array_key_exists("walletid", $_GET)) {
    // get asset id from query string
    $walletid = $_GET['walletid'];

    //check to see if wallet id in query string is not empty and is number, if not return json error
    if ($walletid == '' || !is_numeric($walletid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Wallet ID cannot be blank or must be numeric");
        $response->send();
        exit;
    }

    // if request is a GET, e.g. get wallet
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // attempt to query the database
        try {
            // create db query
            $query = $readDB->prepare('SELECT walletid, label, amount, currency, value from assets where walletid = :walletid and userid = :userid');
            $query->bindParam(':walletid', $walletid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // create asset array to store returned asset
            $assetArray = array();

            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Asset not found");
                $response->send();
                exit;
            }

            // for each row returned
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new asset object for each row
                $asset = new Asset($row['walletid'], $row['label'], $row['amount'], $row['currency'], $row['value']);

                // create asset and store in array for return in json data
                $assetArray[] = $asset->returnAssetAsArray();
            }

            // bundle assets and rows returned into an array to return in the json data
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['assets'] = $assetArray;

            // set up response for successful return
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        }
        // if error with sql query return a json error
        catch (AssetException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database Query Error: " . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get asset");
            $response->send();
            exit;
        }
    }
    // else if request if a DELETE e.g. delete asset
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // attempt to query the database
        try {
            // create db query which would let us delete the asset by its ID.
            $query = $writeDB->prepare('delete from assets where walletid = :walletid and userid = :userid');
            $query->bindParam(':walletid', $walletid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Asset not found");
                $response->send();
                exit;
            }
            // set up response for successful return
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Asset deleted");
            $response->send();
            exit;
        }
        // if error with sql query return a json error
        catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to delete asset");
            $response->send();
            exit;
        }
    }
    // handle updating asset
    elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // update asset
        try {
            // check request's content type header is JSON
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                // set up response for unsuccessful request
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content Type header not set to JSON");
                $response->send();
                exit;
            }

            // get PATCH request body as the PATCHed data will be JSON format
            $rawPatchData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPatchData)) {
                // set up response for unsuccessful request
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit;
            }

            // set asset field updated to false initially
            $label_updated = false;
            $amount_updated = false;
            $currency_updated = false;
            $value_updated = false;

            // create blank query fields string to append each field to
            $queryFields = "";

            // check if label exists in PATCH
            if (isset($jsonData->label)) {
                // set label field updated to true
                $label_updated = true;
                $value_updated = true;
                // add label field to query field string
                $queryFields .= "label = :label, ";
            }

            // check if amount exists in PATCH
            if (isset($jsonData->amount)) {
                // set amount field updated to true
                $amount_updated = true;
                $value_updated = true;
                // add amount field to query field string
                $queryFields .= "amount = :amount, ";
            }

            // check if currency exists in PATCH
            if (isset($jsonData->currency)) {
                // set currency field updated to true
                $currency_updated = true;
                $value_updated = true;
                // add currency field to query field string
                $queryFields .= "currency = :currency, ";
            }

            // check if value exists in PATCH
            if ($value_updated = true || isset($jsonData->value)) {
                // add value field to query field string
                $value_updated = true;
                $queryFields .= "value = :value, ";
            }

            // remove the right hand comma and trailing space
            $queryFields = rtrim($queryFields, ", ");

            // check if any asset fields supplied in JSON
            if ($label_updated === false && $amount_updated === false && $currency_updated === false && $value_updated === false) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No asset fields provided");
                $response->send();
                exit;
            }

            // create db query to get asset from database to update - use master db
            $query = $writeDB->prepare('SELECT walletid, label, amount, currency, value from assets where walletid = :walletid and userid = :userid');
            $query->bindParam(':walletid', $walletid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // make sure that the asset exists for a given asset id
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No asset found to update");
                $response->send();
                exit;
            }

            // for each row returned - should be just one
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new asset object
                $asset = new Asset($row['walletid'], $row['label'], $row['amount'], $row['currency'], $row['value']);
            }

            // create the query string including any query fields
            $queryString = "UPDATE assets set " . $queryFields . " where walletid = :walletid and userid = :userid";
            // prepare the query
            $query = $writeDB->prepare($queryString);

            // if label has been provided
            if ($label_updated === true) {
                // set asset object label to given value (checks for valid input)
                $asset->setLabel($jsonData->label);
                // get the value back as the object could be handling the return of the value differently to
                // what was provided
                $up_label = $asset->getLabel();
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':label', $up_label, PDO::PARAM_STR);
            }

            // if amount has been provided
            if ($amount_updated === true) {
                // set asset object amount to given value (checks for valid input)
                $asset->setAmount($jsonData->amount);
                // get the value back as the object could be handling the return of the value differently to
                // what was provided
                $up_amount = $asset->getAmount();
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':amount', $up_amount, PDO::PARAM_INT);
            }

            // if currency has been provided
            if ($currency_updated === true) {
                // set asset object currency to given value (checks for valid input)
                $asset->setCurrency($jsonData->currency);
                // get the value back as the object could be handling the return of the value differently to
                // what was provided
                $up_currency = $asset->getCurrency();
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':currency', $up_currency, PDO::PARAM_STR);
            }

            // if value has been provided
            if ($value_updated === true) {
                // set asset object value to given value (checks for valid input)
                $asset->setValue();
                // get the value back as the object could be handling the return of the value differently to
                // what was provided
                $up_value = $asset->getValue();
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':value', $up_value, PDO::PARAM_INT);
            }

            // bind the asset id provided in the query string
            $query->bindParam(':walletid', $walletid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            // run the query
            $query->execute();

            // get affected row count
            $rowCount = $query->rowCount();

            // check if row was actually updated, could be that the given values are the same as the stored values
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("asset not updated - given values may be the same as the stored values");
                $response->send();
                exit;
            }

            // create db query to return the newly edited asset - connect to master database
            $query = $writeDB->prepare('SELECT walletid, label, amount, currency, value from assets where walletid = :walletid and userid = :userid');
            $query->bindParam(':walletid', $walletid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // check if asset was found
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No asset found");
                $response->send();
                exit;
            }
            // create asset array to store returned assets
            $assetArray = array();

            // for each row returned
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new asset object for each row returned
                $asset = new Asset($row['walletid'], $row['label'], $row['amount'], $row['currency'], $row['value']);

                // create asset and store in array for return in json data
                $assetArray[] = $asset->returnAssetAsArray();
            }
            // bundle assets and rows returned into an array to return in the json data
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['assets'] = $assetArray;

            // set up response for successful return
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Asset updated");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (AssetException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        }
        // if error with sql query return a json error
        catch (PDOException $ex) {
            error_log("Database Query Error: " . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to update asset - check your data for errors");
            $response->send();
            exit;
        }
    }
    // if any other request method apart from GET, PATCH, DELETE is used then return 405 method not allowed
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
}
// handle getting all assets or creating a new one
elseif (empty($_GET)) {
    // if request is a GET e.g. get assets
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // attempt to query the database
        try {
            // create db query
            $query = $readDB->prepare('SELECT walletid, label, amount, currency, value from assets where userid = :userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // create asset array to store returned assets
            $assetArray = array();
            $sum = 0;
            // for each row returned
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new asset object for each row
                $asset = new Asset($row['walletid'], $row['label'], $row['amount'], $row['currency'], $row['value']);
                if ($row['currency'] === "BTC") {
                    $contentOfAPI = file_get_contents("https://coinlib.io/api/v1/coin?key=757a3f298f50a150&symbol=BTC");
                } else if ($row['currency'] === "ETH") {
                    $contentOfAPI = file_get_contents("https://coinlib.io/api/v1/coin?key=757a3f298f50a150&symbol=ETH");
                } else if ($row['currency'] === "IOTA") {
                    $contentOfAPI = file_get_contents("https://coinlib.io/api/v1/coin?key=757a3f298f50a150&symbol=IOT");
                };
                $resultOfAPI  = json_decode($contentOfAPI, true);
                $valueData = $row['amount'] * $resultOfAPI['price'];
                $sum = $sum + $valueData;
                // create asset and store in array for return in json data
                $assetArray[] = $asset->returnAssetAsArray();
                // $sum = $sum + $assetArray['value'];
            }

            // bundle assets and rows returned into an array to return in the json data
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_value_usd'] = $sum;
            $returnData['assets'] = $assetArray;

            // set up response for successful return
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        }
        // if error with sql query return a json error
        catch (AssetException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database Query Error: " . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get assets");
            $response->send();
            exit;
        }
    }
    // else if request is a POST e.g. create asset
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // create asset
        try {
            // check request's content type header is JSON
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                // set up response for unsuccessful request
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content Type header not set to JSON");
                $response->send();
                exit;
            }

            // get POST request body as the POSTed data will be JSON format
            $rawPostData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPostData)) {
                // set up response for unsuccessful request
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit;
            }

            // check if post request contains label, currency and amount data in body as these are mandatory
            if (!isset($jsonData->label) || !isset($jsonData->currency) || !isset($jsonData->amount)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                (!isset($jsonData->label) ? $response->addMessage("Label field is mandatory and must be provided") : false);
                (!isset($jsonData->currency) ? $response->addMessage("Currency field is mandatory and must be provided") : false);
                (!isset($jsonData->amount) ? $response->addMessage("Amount field is mandatory and must be provided") : false);
                $response->send();
                exit;
            }

            // create new asset with data, if non mandatory fields not provided then set to null
            $newAsset = new Asset(null, $jsonData->label, $jsonData->amount, $jsonData->currency);
            $label = $newAsset->getLabel();
            $amount = $newAsset->getAmount();
            $currency = $newAsset->getCurrency();
            $value = $newAsset->getValue();

            // create db query
            $query = $writeDB->prepare('insert into assets (label, amount, currency, value, userid) values (:label, :amount, :currency, :value, :userid)');
            $query->bindParam(':label', $label, PDO::PARAM_STR);
            $query->bindParam(':amount', $amount, PDO::PARAM_INT);
            $query->bindParam(':currency', $currency, PDO::PARAM_STR);
            $query->bindParam(':value', $value, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // check if row was actually inserted, PDO exception should have caught it if not.
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to create Asset");
                $response->send();
                exit;
            }

            // get last asset id so we can return the Asset in the json
            $lastAssetID = $writeDB->lastInsertId();
            // create db query to get newly created asset - get from master db not read slave as replication may be too slow for successful read
            $query = $writeDB->prepare('SELECT walletid, label, amount, currency, value from assets where walletid = :walletid and userid = :userid');
            $query->bindParam(':walletid', $lastAssetID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // make sure that the new asset was returned
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to retrieve asset after creation");
                $response->send();
                exit;
            }

            // create empty array to store assets
            $assetArray = array();

            // for each row returned - should be just one
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new asset object
                $asset = new Asset($row['walletid'], $row['label'], $row['amount'], $row['currency'], $row['value']);

                // create asset and store in array for return in json data
                $assetArray[] = $asset->returnAssetAsArray();
            }
            // bundle assets and rows returned into an array to return in the json data
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['assets'] = $assetArray;

            //set up response for successful return
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("asset created");
            $response->setData($returnData);
            $response->send();
            exit;
        }
        // if asset fails to create due to data types, missing fields or invalid data then send error json
        catch (AssetException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        }
        // if error with sql query return a json error
        catch (PDOException $ex) {
            error_log("Database Query Error: " . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to insert asset into database - check submitted data for errors");
            $response->send();
            exit;
        }
    }
    // if any other request method apart from GET or POST is used then return 405 method not allowed
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
}
// return 404 error if endpoint not available
else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Endpoint not found");
    $response->send();
    exit;
}
