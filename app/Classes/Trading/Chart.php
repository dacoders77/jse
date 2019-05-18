<?php
/**
 * Created by PhpStorm.
 * User: slinger
 * Date: 5/14/2018
 * Time: 9:57 PM
 */

namespace App\Classes\Trading;

use App\Classes\Accounting\Commission;
use App\Classes\LogToFile;
use App\Jobs\PlaceLimitOrder;
use App\Jobs\PlaceOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Events\eventTrigger;
use PhpParser\Node\Expr\Variable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Chart class provides collection preparation for chart drawing functionality:
 * History bars (candles)
 * Indicators and diagrams (price channel, volume, profit diagram etc.)
 * Trades (long, short, stop-loss mark)
 * DB actions (trades, profit, accumulated profit etc.)
 * Index method is called on each tick occurrence in RatchetPawlSocket class which reads the trades broadcast stream
 */
class Chart
{
    public $trade_flag; // The value is stored in DB. This flag indicates what trade should be opened next. When there is not trades, it is set to all. When long trade has been opened, the next (closing) one must be long and vise vera.
    public $add_bar_long = true; // Count closed position on the same be the signal occurred. The problem is when the position is closed the close price of this bar goes to the next position
    public $add_bar_short = true;
    public $position; // Current position
    public $volume; // Asset amount for order opening
    public $firstPositionEver = true; // Skip the first trade record. When it occurs we ignore calculations and make accumulated_profit = 0. On the next step (next bar) there will be the link to this value
    public $firstEverTradeFlag; // True - when the bot is started and the first trade is executed. Then flag turns to false and trade volume is doubled for closing current position and opening the opposite
    public $tradeProfit;
    private $executionSymbolName;
    private $botSettings;

    /**
     * Received message in RatchetPawlSocket.php is sent to this method as an argument.
     * A message is processed, bars are added to DB, profit is calculated.
     *
     * @param \Ratchet\RFC6455\Messaging\MessageInterface $socketMessage
     * @param Command Variable type for colored and formatted console messages like alert, warning, error etc.
     * @return array $messageArray Array which has OHLC of the bar, new bar flag and other parameters. The array is
     * generated on each tick (each websocket message) and then passed as an event to the browser. These messages
     * are transmitted over websocket pusher broadcast service.
     * @see https://pusher.com/
     * @see Classes and backtest scheme https://drive.google.com/file/d/1IDBxR2dWDDsbFbradNapSo7QYxv36EQM/view?usp=sharing
     */

    public function __construct($executionSymbolName, $orderVolume, $botSettings)
    {
        $this->volume = $orderVolume;
        $this->executionSymbolName = $executionSymbolName;
        $this->trade_flag = 'all';
        $this->botSettings = $botSettings;
    }

    public function index($barDate, $timeStamp)
    {
        dump(__FILE__);

        // Realtime mode. No ID of the record is sent. Get the quantity of all records.
        /** In this case we do the same request, take the last record from the DB */
        $assetRow =
            DB::table($this->botSettings['botTitle'])
                ->orderBy('id', 'desc')->take(1)
                ->get();
        $recordId = $assetRow[0]->id;

        /**
         * We do this check because sometimes, don't really understand under which circumstances, we get
         * Trying to get property of non-object error
         */
        if (!is_null(DB::table($this->botSettings['botTitle'])->where('id', $recordId - 1)->get()->first()))
        {
            // Get the penultimate row
            $penUltimanteRow =
                DB::table($this->botSettings['botTitle'])
                    ->where('id', $recordId - 1)
                    ->get() // Get row as a collection. A collection can contain may elements in it
                    ->first(); // Get the first element from the collection. In this case there is only one
        }
        else
        {
            echo "Null check. Chart.php " . __LINE__;
        }

        /**
         * Do not calculate profit if there is no open position. If do not do this check - zeros in table occu
         * $this->trade_flag != "all" if it is "all" - it means that it is a first or initial start
         * We do not store position in DB thus we use "all" check to determine a position absence
         * if "all" - no position has been opened yet
         */
        if ($this->position != null && $this->trade_flag != "all"){

            // Get the price of the last trade
            $lastTradePrice = // Last trade price
                DB::table($this->botSettings['botTitle'])
                    ->whereNotNull('trade_price') // Not null trade price value
                    //->where('time_stamp', '<', $timeStamp) // Find the last trade. This check is needed only for historical back testing.
                    ->orderBy('id', 'desc') // Form biggest to smallest values
                    ->value('trade_price'); // Get trade price value

            $this->tradeProfit =
                (($this->position == "long" ?
                    ($assetRow[0]->close - $lastTradePrice) * $this->volume :
                    ($lastTradePrice - $assetRow[0]->close) * $this->volume)
                );

            DB::table($this->botSettings['botTitle'])
                ->where('id', $recordId)
                ->update([
                    // Calculate trade profit only if the position is open.
                    // Because we reach this code on each new bar is issued when high or low price channel boundary is exceeded
                    'trade_profit' => round($this->tradeProfit, 4),
                ]);

            echo "Chart.php: " . __LINE__ . " Profit calculated:" . $this->tradeProfit . "\n";
        }

        /* $this->trade_flag == "all" is used only when the first trade occurs, then it turns to "long" or "short". */
        // SMA noise filter is ON

        //if (($this->trade_flag == "all" || $this->trade_flag == "long")) {
        if (($assetRow[0]->sma1 > $penUltimanteRow->price_channel_high_value) && ($this->trade_flag == "all" || $this->trade_flag == "long")){
            echo "####### HIGH TRADE!<br>\n";
            // Is it the first trade ever?
            if ($this->trade_flag == "all"){
                echo "---------------------- FIRST EVER TRADE<br>\n";
                PlaceOrder::dispatch('buy', $this->executionSymbolName, $this->volume, $this->botSettings);
            }
            else // Not the first trade. Close the current position and open opposite trade. vol = vol * 2
            {
                // open order buy vol = vol * 2
                echo "---------------------- NOT FIRST EVER TRADE. CLOSE + OPEN. VOL * 2\n";
                PlaceOrder::dispatch('buy', $this->executionSymbolName, $this->volume * 2, $this->botSettings);
            }

            // Trade flag. If this flag set to short -> don't enter this IF and wait for channel low crossing (IF below)
            $this->trade_flag = 'short';
            $this->position = "long";
            $this->add_bar_long = true;

            \App\Classes\Accounting\TradeBar::update($this->botSettings, $recordId, $timeStamp, $assetRow, "buy");
            \App\Classes\Accounting\Commission::accumulate($this->botSettings);

        } // BUY trade

        // If < low price channel. SELL
        //if (false){
        if (($assetRow[0]->sma1 < $penUltimanteRow->price_channel_low_value) && ($this->trade_flag == "all"  || $this->trade_flag == "short")) {
            echo "####### LOW TRADE!<br>\n";

            // Is the the first trade ever?
            if ($this->trade_flag == "all"){
                echo "---------------------- FIRST EVER TRADE<br>\n";
                PlaceOrder::dispatch('sell', $this->executionSymbolName, $this->volume, $this->botSettings);
            }
            else // Not the first trade. Close the current position and open opposite trade. vol = vol * 2
            {
                echo "---------------------- NOT FIRST EVER TRADE. CLOSE + OPEN. VOL * 2\n";
                PlaceOrder::dispatch('sell', $this->executionSymbolName, $this->volume * 2, $this->botSettings);
            }

            $this->trade_flag = 'long';
            $this->position = "short";
            $this->add_bar_short = true;

            // Add(update) trade info to the last(current) bar(record)
            \App\Classes\Accounting\TradeBar::update($this->botSettings, $recordId, $timeStamp, $assetRow, "sell");
            \App\Classes\Accounting\Commission::accumulate($this->botSettings);
        } // SELL trade


        if ($this->trade_flag != "all") {
            /** @var int $z Get the last record from the asset table */
            $z = DB::table($this->botSettings['botTitle'])
                    ->where('id', $recordId)
                    ->get();

            /** @var array $temp The revious record from asset table where trade_direction is not null */
            $temp =
                DB::table($this->botSettings['botTitle'])
                    //->where('id', $z->id - 1)
                    ->whereNotNull('trade_direction')
                    ->get();

            /* A trade is open at this bar */
            if ($z[0]->trade_direction == "buy" || $z[0]->trade_direction == "sell") {
            //if ($z->trade_direction == "buy" || $z->trade_direction == "sell") {
                DB::table($this->botSettings['botTitle'])
                    ->where('id', $recordId)
                    ->update([
                        // Do we set 0 for the row of the first trade?
                        // -2 take
                        'accumulated_profit' => (count($temp) > 1 ? $temp[count($temp) - 2]->accumulated_profit + $this->tradeProfit : 0)
                    ]);

            } else // No trade at the bar
            {
                DB::table($this->botSettings['botTitle'])
                    ->where('id', $recordId)
                    ->update([
                        // -1 take a previous record
                        'accumulated_profit' => $temp[count($temp) - 1]->accumulated_profit + $this->tradeProfit
                    ]);
            }
        }

        // NET PROFIT net_profit
        if ($this->position != null){

            $accumulatedProfit =
                DB::table($this->botSettings['botTitle'])
                    ->where('id', $recordId)
                    ->value('accumulated_profit');

            $accumulatedCommission =
                DB::table($this->botSettings['botTitle'])
                    ->whereNotNull('accumulated_commission')
                    ->orderBy('id', 'desc')
                    ->value('accumulated_commission');

            DB::table($this->botSettings['botTitle'])
                ->where('id', $recordId)
                ->update([
                    // net profit = accum_profit - last accum_commission
                    'net_profit' => round($accumulatedProfit - $accumulatedCommission, 4)
                ]);
        }
    }
}