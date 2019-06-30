<?php

namespace App\Console\Commands;

use App\Classes\Trading\LimitOrder;
use App\Job;
use App\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class Limit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'limit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $limitOrderObj = [
          'orderID' => null,
          'clOrdID' => 'abc-123-' . now(),
          'direction' => 'sell',
          'volume' => 287,
          'isLimitOrderPlaced' => false,
          'limitOrderPrice' => null,
          'limitOrderTimestamp' => null,
          'step' => 0 // Limit order position placement. Used for testing purpuses. If set - order will be locate deeper in the book.
        ];

        /**
         * Set cache object. It will be accesses from other classes and que workers.
         *
         *  * Contains settings and flags of a limit order.
         * These settings are read by other classes and que workers: market order que, amend order que, etc.
         * Once a flag is set, other classes can read it.
         * For example if an order is executed - we need to stop bid/ask order book subscription immediately.
         */
        Cache::put('bot_1', $limitOrderObj, now()->addMinute(30));

        /**
         * Call LimitOrder.php and start limit orders.
         * Send $this - it will allow to output colored console messages.
         */
        $limitOrder = new LimitOrder();
        $limitOrder->start($this);
    }
}