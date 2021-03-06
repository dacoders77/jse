<?php
/**
 * Created by PhpStorm.
 * User: slinger
 * Date: 6/30/2019
 * Time: 11:27 PM
 */

namespace App\Classes\DB;
use App\Classes\Trading\ProfitSignal;
use Illuminate\Support\Facades\DB;

class SignalTable extends ProfitSignal
{
    /* ProfitSignal.php vars */
    protected $lastRow;
    protected $penUltimanteRow;
    protected $botId;

    /**
     * Add execution records to DB.
     * One limit order can have many executions.
     * Once a first record is added - change signal status from new to pending.
     *
     * @param $orderExecutionResponse
     */
    public static function insertRecord($orderExecutionResponse, $botId){
        DB::table('signal_' . $botId)->insert([
            //'symbol' => $orderExecutionResponse['symbol'],
            'order_type' => $orderExecutionResponse['ordType'],
            'direction' => strtolower($orderExecutionResponse['side']), // to lower case
            'volume' => $orderExecutionResponse['lastQty'],
            'time_stamp' => strtotime($orderExecutionResponse['timestamp']) * 1000, // 13 digits
            'trade_date' => gmdate("Y-m-d G:i:s", strtotime($orderExecutionResponse['timestamp'])), // mysql date format
            'avg_fill_price' => $orderExecutionResponse['avgPx'], // Exec price
            'order_price' => $orderExecutionResponse['price'], // In case of amend-market order, will be the price which goes to opposite side of order book
            'trade_commission_percent' => $orderExecutionResponse['commission'],
            'volume_reminder' => $orderExecutionResponse['leavesQty'],
            'type' => $orderExecutionResponse['execType'],
            'order_id' => $orderExecutionResponse['orderID']
        ]);
    }

    /**
     * Once a limit order is fully filled change its status from pending to closed.
     * There can be a case when there is no pending status assigned - change to close as well.
     * This happens when an order gets filled immediately.
     * @param $orderExecutionResponse
     */
    public static function updateSignalStatusToClose($botId, $orderExecutionResponse){
        
        DB::table('signal_' . $botId)
            ->where('type', 'signal')
            ->where('status', 'pending')
            ->orwhere('status', 'new')
            ->update([
                'status' => 'closed'
            ]);

        // When closed -> prepare the row with price
        // Now we take avg price from last execution - later we should calculate it
        // Add avg_fill_price to avg_fill_price where type = signal, status = closed
        DB::table('signal_' . $botId)
            ->where('type', 'signal')
            ->where('status', 'closed')
            ->where('order_id', $orderExecutionResponse['orderID'])
            ->update([
                'avg_fill_price' => $orderExecutionResponse['avgPx'], // Exec price
                'trade_commission_percent' => $orderExecutionResponse['commission']
            ]);

        /* Profit calculation */
        \App\Classes\Trading\ProfitSignal::calc($botId, $orderExecutionResponse);
    }

    /**
     * Update signal's status to close + update average fill price and trade commission %.
     * IMOPRTANT!
     * Commission percent value is taken from a trade. It can be -0.00025 or -0.00075. Rebate/Fee correspondingly.
     * What a force trade is activated, Bitmex respond with no execution information and we make it ourselves in
     * LimitOrderMessage.php forceSignalFinish()
     * When trades are added to the table. One signal can have multiple trades. The commission value is copied to the signal!
     * That's why we have all these updates!
     *
     * @param $botId
     * @param $orderExecutionResponse
     *
     */
    public static function signalFinish($botId, $orderExecutionResponse){

        DB::table('signal_' . $botId)
            ->where('type', 'signal')
            ->where('status', 'pending')
            ->orwhere('status', 'new')
            ->update([
                'status' => 'closed',
                'avg_fill_price' => $orderExecutionResponse['avgPx'], // Exec price
                'trade_commission_percent' => $orderExecutionResponse['commission']
            ]);

        /* Calculate profit */
        \App\Classes\Trading\ProfitSignal::calc($botId, $orderExecutionResponse);
    }

    /**
     * Once a limit order is placed.
     *
     * @param $botId
     * @param $response
     */
    public static  function updateSignalInfo($botId, $response){
        DB::table('signal_' . $botId)
            ->where('type', 'signal')
            ->where('status', 'pending')
            ->update([
                'date' => gmdate("Y-m-d G:i:s", $response['timestamp'] / 1000), // mysql date format
                'time_stamp' => $response['timestamp'],
                'order_id' => $response['info']['orderID'],
                'signal_price' => $response['info']['price']
            ]);
    }

    /**
     * Once a limit order is placed - status chnages to pending.
     * When it gets filled, order is updated. Status remains the same.
     * Status changes to close on when full volume is filled.
     *
     * Once a limit order is placed - we add to the signal:
     * time stamp, commission % and avereage fill prcie.
     * All these values are taken from the order book. Later this values will be overwritten with information from
     * limit order placement responce.
     * The reason that we put these initial values is that no information about order placement may be recieved.
     * In this case all profit calculation is broken. As well as chart, because there is a point with null value in time stamp
     * which goes right in the begging of the chart.
     *
     * @param $botId
     * @param $message
     */
    public static function updateSignalStatus($botId, $message){
        DB::table('signal_' . $botId)
            ->where('type', 'signal')
            ->where('status', 'new')
            ->update([
                'order_id' => 'initial_order_id',
                'status' => 'pending',
                'time_stamp' => $message['timestamp'],
                'trade_commission_percent' => -0.000123,
                'avg_fill_price' => $message['avgFillPrice'],
                'order_type' => 'initial_insertion'
            ]);
    }
}