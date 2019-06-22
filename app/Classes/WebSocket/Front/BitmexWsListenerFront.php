<?php
/**
 * Created by PhpStorm.
 * User: slinger
 * Date: 4/17/2019
 * Time: 9:15 PM
 */

namespace App\Classes\WebSocket\Front;
use App\Bot;
use App\Classes\Trading\CandleMaker;
use App\Classes\Trading\Chart;
use App\Strategy;
use App\Symbol;
use App\Account;
use App\Exchange;
use App\PricechannelSettings;
use App\MacdSettings;

class BitmexWsListenerFront
{
    public static $console;
    public static $candleMaker;
    public static $chart;
    private static $symbol;
    private static $priceChannelPeriod;
    private static $smaFilterPeriod;
    private static $macdSettings;
    private static $connection;
    private static $isHistoryLoaded = true;
    private static $isUnsubscribed = false;
    private static $botId;
    private static $execution_symbol_name;
    private static $apiPath;
    private static $api;
    private static $apiSecret;
    private static $commission;
    private static $isTestnet;
    private static $isCreateClasses = true;

    private static $strategiesSettingsObject;
    private static $accountSettingsObject;

    public static function subscribe($connector, $loop, $console, $botId){

        self::$console = $console;
        self::$botId = $botId;



        /* For static methods call inside an anonymous function */
        $self = get_called_class();

        $loop->addPeriodicTimer(1, function() use($loop, $botId, $self) {

            echo (Bot::where('id', $botId)->value('status') == 'running' ? 'running' : 'idle') . "\n";

            /* Get strategies settings object*/
            self::$strategiesSettingsObject = \App\Classes\WebSocket\Front\Strategies::getSettings($botId);
            /* Get account settings object */
            self::$accountSettingsObject = \App\Classes\WebSocket\Front\TradingAccount::getSettings($botId);
            self::trace();

            /* Create Chart and Candle maker classes here. ONCE! Create again after STOP! */
            if (self::$isCreateClasses) {
                self::$candleMaker = new \App\Classes\Trading\CandleMaker('priceChannel', self::$accountSettingsObject);

                self::$chart = new \App\Classes\Trading\Chart(self::$accountSettingsObject);
                self::$isCreateClasses = false;
            }

            /* Start the bot */
            if (Bot::where('id', $botId)->value('status') == 'running'){
                // if strategy == price channel
                self::startPriceChannelBot($botId);
                // if == macd
                // start macd
            }

            /* Stop the bot */
            if (Bot::where('id', $botId)->value('status') == 'idle'){
                // if price channel
                self::stopPriceChannelBot();
                // if macd
                // stop macd
            }
        });

        /**
         * Pick up the right websocket endpoint accordingly to the exchange
         */
        $exchangeWebSocketEndPoint = "wss://www.bitmex.com/realtime";
        $connector($exchangeWebSocketEndPoint, [], ['Origin' => 'http://localhost'])
            ->then(function(\Ratchet\Client\WebSocket $conn) use ($loop) {
                self::$connection = $conn;
                $conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $socketMessage) use ($conn, $loop) {
                    $jsonMessage = json_decode($socketMessage->getPayload(), true);
                    if (array_key_exists('data', $jsonMessage)){
                        if (array_key_exists('lastPrice', $jsonMessage['data'][0])){
                            self::messageParse($jsonMessage);
                        }
                    }
                });

                $conn->on('close', function($code = null, $reason = null) use ($loop) {
                    echo "Connection closed ({$code} - {$reason})\n";
                    self::$console->info("Connection closed. " . __LINE__);
                    self::$console->error("Reconnecting back!");
                    sleep(5); // Wait 5 seconds before next connection try will attempt
                    self::$console->handle(); // Call the main method of this class
                });

            }, function(\Exception $e) use ($loop) {
                $errorString = "RatchetPawlSocket.php Could not connect. Reconnect in 5 sec. \n Reason: {$e->getMessage()} \n";
                echo $errorString;
                sleep(5); // Wait 5 seconds before next connection try will attempt
                //$this->handle(); // Call the main method of this class
                self::subscribe();
                //$loop->stop();
            });
        $loop->run();
    }

    private static function reloadChart ($botSettings){
        $pusherApiMessage = new \App\Classes\WebSocket\PusherApiMessage();
        $pusherApiMessage->clientId = $botSettings['frontEndId'];
        $pusherApiMessage->messageType = 'reloadChartAfterHistoryLoaded';
        try{
            event(new \App\Events\jseevent($pusherApiMessage->toArray()));
        } catch (\Exception $e)
        {
            echo __FILE__ . " " . __LINE__ . "\n";
            dump($e);
        }
    }

    private static function trace(){
        /* Trace: */
        dump(self::$accountSettingsObject);
        dump(self::$strategiesSettingsObject);
    }

    private static function startPriceChannelBot($botId){
        if (self::$isHistoryLoaded){
            \App\Classes\Trading\History::loadPeriod(self::$accountSettingsObject);
            dump('History loaded');

            /* Initial indicators calculation and chart reload*/
            \App\Classes\Indicators\PriceChannel::calculate(
                self::$strategiesSettingsObject['priceChannel']['priceChannelPeriod'],
                Bot::where('id', $botId)->value('db_table_name'),
                true);

            \App\Classes\Indicators\Sma::calculate(
                'close',
                self::$strategiesSettingsObject['priceChannel']['smaFilterPeriod'],
                'sma1',
                Bot::where('id', $botId)->value('db_table_name'),
                true);

            /* Reload chart */
            self::reloadChart(['frontEndId' => self::$accountSettingsObject['frontEndId']]);

            /* Manual subscription object */
            $requestObject = json_encode([
                "op" => "subscribe",
                "args" => "instrument:" . self::$accountSettingsObject['historySymbolName']
            ]);
            self::$connection->send($requestObject);
            self::$isHistoryLoaded = false;
            self::$isUnsubscribed = true;
        }
    }

    private static function stopPriceChannelBot(){
        /**
         * Refresh settings.
         * New settings are loaded on stop. On play they picked up again
         * JSE-117. Trade flag doesn't reset on stop
         */
        self::$chart->botSettings = self::$accountSettingsObject;

        /* reset history flag */
        self::$isHistoryLoaded = true;

        if(self::$isUnsubscribed){
            /* Manual UNsubscription object */
            $requestObject = json_encode([
                "op" => "unsubscribe",
                "args" => "instrument:" . self::$accountSettingsObject['historySymbolName']
            ]);
            self::$connection->send($requestObject);
            /* Unsubscribed. Then do nothing. Wait for the next bot start */
            self::$isUnsubscribed = false;
            self::$isCreateClasses; // Chart and CandleMaker will be freshly created
        }
    }

    /**
     * Accordingly to the active strategy we pass different parameters.
     * @todo 22.06.19 Pass the whole strategies object and strategy index.
     *
     * @param $jsonMessage
     * @return void
     */
    private static  function messageParse($jsonMessage){
        \App\Classes\WebSocket\ConsoleWebSocket::messageParse(
            $jsonMessage,
            self::$console,
            self::$candleMaker,
            self::$chart,
            (array_key_exists('priceChannel', self::$strategiesSettingsObject) ? self::$strategiesSettingsObject['priceChannel'] : null),
            (array_key_exists('macd', self::$strategiesSettingsObject) ? self::$strategiesSettingsObject['macd'] : null)
            //self::$strategiesSettingsObject['priceChannel'], // if price channel. if macd = null
            //self::$strategiesSettingsObject['macd'] // if macd. if price channel = null
        );
    }
}