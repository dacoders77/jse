<?php
/**
 * Created by PhpStorm.
 * User: slinger
 * Date: 5/14/2018
 * Time: 9:57 PM
 */

namespace App\Classes\Trading;

use App\Classes\Accounting\AccumulatedProfit;
use App\Classes\Accounting\Commission;
use App\Classes\Accounting\NetProfit;
use App\Classes\Accounting\TradeProfit;
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
    public $trade_flag; // The value is stored in DB. This flag indicates what trade should be opened next. When there is no trades, it is set to all. When long trade has been opened, the next (closing) one must be long and vise vera.
    public $add_bar_long = true; // Count closed position on the same be the signal occurred. The problem is when the position is closed the close price of this bar goes to the next position
    public $add_bar_short = true;
    public $position; // Current position
    public $volume; // Asset amount for order opening
    public $firstPositionEver = true; // Skip the first trade record. When it occurs we ignore calculations and make accumulated_profit = 0. On the next step (next bar) there will be the link to this value
    public $firstEverTradeFlag; // True - when the bot is started and the first trade is executed. Then flag turns to false and trade volume is doubled for closing current position and opening the opposite
    public $tradeProfit;
    private $executionSymbolName;
    private $botSettings;
    private $lastRow;

    /**
     * @see Classes and backtest scheme https://drive.google.com/file/d/1IDBxR2dWDDsbFbradNapSo7QYxv36EQM/view?usp=sharing
     */
    public function __construct($executionSymbolName, $orderVolume, $botSettings)
    {
        $this->volume = $orderVolume;
        $this->executionSymbolName = $executionSymbolName;
        $this->trade_flag = 'all';
        $this->botSettings = $botSettings;
    }

    public function index($mode = null, $backTestRowId = null)
    {
        echo(__FILE__ . "\n");
        // Realtime mode. No ID of the record is sent. Get the quantity of all records.
        /** In this case we do the same request, take the last record from the DB */

        // delete
        //$lastRow = DB::table($this->botSettings['botTitle'])->orderBy('id', 'desc')->take(1)->get();

        $id = $backTestRowId;

        // DB has all bars in it.
        // Backtesting.php runs through all of them and generates id
        if ($mode == "backtest")
        {
            /**
             * @var int $recordId id of the record in DB. Generated in Backtesting.php
             * In backtest mode id is sent as a parameter. In realtime - pulled from DB.
             */
            //$recordId = $id; // In the real time mode there is no id sent. It is sent only in back test mode.
            $this->lastRow = DB::table($this->botSettings['botTitle'])->where('id', $backTestRowId)->get();
        }
        else // Realtime
        {
            /* No record id is sent in real time mode. We get the last record from the DB. */
            $this->lastRow = DB::table($this->botSettings['botTitle'])->orderBy('id', 'desc')->take(1)->get();
            //$lastRow = DB::table($this->botSettings['botTitle'])->orderBy('id', 'desc')->take(1)->get();
        }


        // just for test
        $lastRow = $this->lastRow;




        $penUltimanteRow = DB::table($this->botSettings['botTitle'])->where('id', $lastRow[0]->id - 1)->get()->first();


        //echo "cloe: " . $lastRow[0]->close . "\n";

        /**
         * Do not calculate profit if there is no open position. If do not do this check - zeros in table occu
         * $this->trade_flag != "all" if it is "all" - it means that it is a first or initial start
         * We do not store position in DB thus we use "all" check to determine a position absence
         * if "all" - no position has been opened yet
         */
        if ($this->position != null && $this->trade_flag != "all"){
            /* Get the price of the last trade */
            $lastTradePrice =
                DB::table($this->botSettings['botTitle'])
                    ->whereNotNull('trade_price')
                    ->orderBy('id', 'desc') // Form biggest to smallest values
                    ->value('trade_price');
            $this->tradeProfit = (($this->position == "long" ? ($lastRow[0]->close - $lastTradePrice) * $this->volume : ($lastTradePrice - $lastRow[0]->close) * $this->volume));
            TradeProfit::calculate($this->botSettings, $this->tradeProfit, $backTestRowId);
        }

        /**
         * $this->trade_flag == "all" is used only when the first trade occurs, then it turns to "long" or "short".
         * SMA filter is always on. SMA filter is a simple SMA with period = 2;
         */
        if (($lastRow[0]->sma1 > $penUltimanteRow->price_channel_high_value) && ($this->trade_flag == "all" || $this->trade_flag == "long")){
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
            $this->trade_flag = 'short'; $this->position = "long"; $this->add_bar_long = true;
//            \App\Classes\Accounting\TradeBar::update($this->botSettings, "buy", $lastRow[0]->close, $backTestRowId);

            dump($lastRow[0]->id);
            \App\Classes\Accounting\TradeBar::update($this->botSettings, "buy", $lastRow[0]->close, $lastRow[0]->id);
            \App\Classes\Accounting\Commission::accumulate($this->botSettings);
        }

        if (($lastRow[0]->sma1 < $penUltimanteRow->price_channel_low_value) && ($this->trade_flag == "all"  || $this->trade_flag == "short")) {
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
            $this->trade_flag = 'long'; $this->position = "short"; $this->add_bar_short = true;
            /* Update the last bar/record in the DB */

            dump($lastRow[0]->id);


            //\App\Classes\Accounting\TradeBar::update($this->botSettings, "sell", $lastRow[0]->close, $backTestRowId);
            \App\Classes\Accounting\TradeBar::update($this->botSettings, "sell", $lastRow[0]->close, $lastRow[0]->id);
            \App\Classes\Accounting\Commission::accumulate($this->botSettings);

        }

        /**
         * Do not calculate profit if there are no trades.
         * If trade_flag is set to all, it means that no trades hav been executed yet.
         */
        if ($this->trade_flag != "all") {
            AccumulatedProfit::calculate($this->botSettings, $lastRow[0]->id);
            NetProfit::calculate($this->position, $this->botSettings, $lastRow[0]->id);
        }
    }
}