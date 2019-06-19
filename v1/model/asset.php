<?php
// Asset Model Object

// empty AssetException class so we can catch asset errors
class AssetException extends Exception
{ }

class Asset
{
    // define private variables
    // define variable to store asset id number
    private $_id;
    // define variable to store asset label
    private $_label;
    // define variable to store asset amount
    private $_amount;
    // define variable to store asset currency
    private $_currency;
    // define variable to store asset value
    public $_value;


    // constructor to create the label object with the instance variables already set
    public function __construct($id, $label, $amount, $currency) //last was $value
    {
        $this->setID($id);
        $this->setLabel($label);
        $this->setAmount($amount);
        $this->setCurrency($currency);
        $this->setValue(); // had value in it $value
    }

    // function to return Asset ID
    public function getID()
    {
        return $this->_id;
    }

    // function to return asset label
    public function getLabel()
    {
        return $this->_label;
    }

    // function to return asset amount
    public function getAmount()
    {
        return $this->_amount;
    }

    // function to return asset currency
    public function getCurrency()
    {
        return $this->_currency;
    }

    // function to return asset value
    public function getValue()
    {
        return $this->_value;
    }

    // function to set the private asset ID
    public function setID($id)
    {
        // if passed in asset ID is not null or not numeric, is not between 0 and 9223372036854775807 (signed bigint max val - 64bit)
        // over nine quintillion rows
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new AssetException("Asset ID error");
        }
        $this->_id = $id;
    }

    // function to set the private asset label
    public function setLabel($label)
    {
        // if passed in label is not between 1 and 255 characters
        if (strlen($label) < 1 || strlen($label) > 255) {
            throw new AssetException("LAbel title error");
        }
        $this->_label = $label;
    }

    // function to set the private asset amount
    public function setAmount($amount)
    {


        $this->_amount = $amount;
    }

    // public function to set the private asset currency
    public function setCurrency($currency)
    {
        // if passed in currency is not BTC or IOTA or ETH
        if (strtoupper($currency) !== 'BTC' && strtoupper($currency) !== 'ETH' && strtoupper($currency) !== 'IOTA') {
            throw new AssetException("Asset completed is not ETH, BTC or IOTA");
        }
        $this->_currency = strtoupper($currency);
    }

    // function to set the private Asset value
    public function setValue()
    {
        if ($this->_currency === "BTC") {
            $contentOfAPI = file_get_contents("https://coinlib.io/api/v1/coin?key=757a3f298f50a150&symbol=BTC");
        } else if ($this->_currency === "ETH") {
            $contentOfAPI = file_get_contents("https://coinlib.io/api/v1/coin?key=757a3f298f50a150&symbol=ETH");
        } else if ($this->_currency === "IOTA") {
            $contentOfAPI = file_get_contents("https://coinlib.io/api/v1/coin?key=757a3f298f50a150&symbol=IOT");
        }
        $resultOfAPI  = json_decode($contentOfAPI, true);
        $this->_value = $this->_amount * $resultOfAPI['price'];
    }


    // function to return Asset object as an array for json
    public function returnAssetAsArray()
    {
        $Asset = array();
        $Asset['id'] = $this->getID();
        $Asset['label'] = $this->getLabel();
        $Asset['amount'] = $this->getAmount();
        $Asset['currency'] = $this->getCurrency();
        $Asset['value'] = $this->getValue();
        return $Asset;
    }
}