<?php
/**
 * Created by PhpStorm.
 * User: slinger
 * Date: 4/25/2019
 * Time: 6:45 PM
 */

namespace App\Classes\Trading;
use ccxt\bitmex;
use Mockery\Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Market order execution.
 * Volume is sent as a separate parameter.
 * When a position is flipped - the volume is doubled. This is set in Chart.php
 *
 * Class Exchange
 * @package App\Classes\Trading
 */
class Exchange
{
    private static $response;
    public static function placeMarketBuyOrder($botSettings, $volume){
        echo __FILE__ . " line: " . __LINE__ . "\n";
        $exchange = new bitmex();

        if($botSettings['isTestnet'] == 1){
            $exchange->urls['api'] = $exchange->urls['test']; // Testnet or live. test or api
        } else {
            $exchange->urls['api'] = $exchange->urls['api']; // Testnet or live. test or api
        }

        $exchange->apiKey = $botSettings['api'];
        $exchange->secret = $botSettings['apiSecret'];

        try{
            echo "API path. test or api: " . $exchange->urls['api'] . "\n";
            echo "Symbol: " . $botSettings['executionSymbolName'] . " in Exchnage.php \n";
            self::$response = $exchange->createMarketBuyOrder($botSettings['executionSymbolName'], $volume, []); // BTC/USD ETH/USD
            echo "Execution response: \n";
            dump(self::$response);
        }
        catch (\Exception $e)
        {
            dump('--------- in exception line 40');
            self::$response = $e->getMessage();

        }
        self::checkResponse();
    }

    public static function placeMarketSellOrder($botSettings, $volume){
        echo __FILE__ . " line: " . __LINE__ . "\n";
        $exchange = new bitmex();

        if($botSettings['isTestnet'] == 1){
            $exchange->urls['api'] = $exchange->urls['test']; // Testnet or live. test or api
        } else {
            $exchange->urls['api'] = $exchange->urls['api'];
        }

        $exchange->apiKey = $botSettings['api'];
        $exchange->secret = $botSettings['apiSecret'];

        try{
            echo "API path. test or api:" . $exchange->urls['api'] . "\n";
            self::$response = $exchange->createMarketSellOrder($botSettings['executionSymbolName'], $volume, []); // BTC/USD ETH/USD
            echo "Execution response: \n";
            dump(self::$response);
        }
        catch (\Exception $e)
        {
            self::$response = $e->getMessage();
        }
        self::checkResponse();
    }

    public static function placeLimitSellOrder($botSettings, $price, $volume, $limitOrderObj){

        echo __FILE__ . " line: " . __LINE__ . "\n";
        $exchange = new bitmex();

        if($botSettings['isTestnet'] == 1){
            $exchange->urls['api'] = $exchange->urls['test']; // Testnet or live. test or api
        } else {
            $exchange->urls['api'] = $exchange->urls['api']; // Testnet or live. test or api
        }

        $exchange->apiKey = $botSettings['api'];
        $exchange->secret = $botSettings['apiSecret'];

        try{
            echo "API path. test or api: " . $exchange->urls['api'] . "\n";
            echo "Symbol: " . $botSettings['executionSymbolName'] . " in Exchnage.php \n";

            self::$response = $exchange->createLimitSellOrder($botSettings['executionSymbolName'], $volume, $price, array('clOrdID' => $limitOrderObj['clOrdID'])
            );

            echo "Limit order placement response: \n";
            dump(self::$response);
        }
        catch (\Exception $e)
        {
            dump('--------- in exception line 102');
            self::$response = $e->getMessage();
        }

        self::checkResponse($limitOrderObj);
    }

    public static function amendOrder($newPrice, $orderID, $botSettings){
        dump('****   AMEND ORDER ****');
        echo __FILE__ . " line: " . __LINE__ . "\n";
        $exchange = new bitmex();

        if($botSettings['isTestnet'] == 1){
            $exchange->urls['api'] = $exchange->urls['test']; // Testnet or live. test or api
        } else {
            $exchange->urls['api'] = $exchange->urls['api']; // Testnet or live. test or api
        }

        $exchange->apiKey = $botSettings['api'];
        $exchange->secret = $botSettings['apiSecret'];

        try{
            // self::$response = $exchange->createLimitSellOrder($botSettings['executionSymbolName'], $volume, $price, array('clOrdID' => $limitOrderObj['clOrdID'])
            self::$response = $exchange->privatePutOrder(array('orderID' => $orderID, 'price' => $newPrice));
            echo "Amend order placement response: \n";
            dump(self::$response);
        }
        catch (\Exception $e)
        {
            dump('--------- in exception line (Exchange.php): ' . __LINE__);
            self::$response = $e->getMessage();
        }

        //dump(self::$response);
        self::checkResponse();
    }

    private static function checkResponse($limitOrderObj = null){
        if (gettype(self::$response) == 'array'){

            /**
             * Be careful!
             * Do not execute this code for market orders!
             * Market order have orderId as well and this will mess everything up!
             */
            $limitOrderObj['limitOrderTimestamp'] = 12345;
            $limitOrderObj['orderID'] = self::$response['info']['orderID'];
            Cache::put('bot_1', $limitOrderObj, now()->addMinute(30));
            dump(self::$response);
        }

        if (gettype(self::$response) == 'string'){
            echo "Error string line 120: " . self::$response . "\n";
            switch(false){
                case !strpos(self::$response, 'Account has insufficient');
                    $error = 'Account has insufficient funds. Die.';
                    Log::notice($error);
                    die(__FILE__ . ' ' . __LINE__);

                case !strpos(self::$response, 'does not have market symbol'); // bitmex does not have market symbol
                    $error = 'Bitmex does not have market symbol. Execution is not possible';
                    throw new \Exception($error);
                    break;
                /* @see: https://www.bitmex.com/app/restAPI#Overload */
                case !strpos(self::$response, 'overloaded');
                    // The system is currently overloaded. Please try again later
                    throw new \Exception('Exchange overloaded');
                    break;
                /* Full error text: bitmex {"error":{"message":"Invalid ordStatus","name":"HTTPError"}} */
                case !strpos(self::$response, 'ordStatus');
                    Log::notice('Invalid ordStatus. Usually it happens when trying to amend and order which is already filled');
                    dump('Die on order amend');
                    die(__FILE__ . ' ' . __LINE__);

            }
        }
    }
}