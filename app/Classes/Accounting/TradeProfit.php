<?php
/**
 * Created by PhpStorm.
 * User: slinger
 * Date: 5/22/2019
 * Time: 5:06 PM
 */

namespace App\Classes\Accounting;
use Illuminate\Support\Facades\DB;

class TradeProfit
{
    public static function calculate($botSettings, $tradeProfit, $lastRowId){
        $lastRow =
            DB::table($botSettings['botTitle'])
                ->orderBy('id', 'desc')->take(1)
                ->get();

        DB::table($botSettings['botTitle'])
            //->where('id', $lastRow[0]->id)
            ->where('id', $lastRowId)
            ->update([
                // Calculate trade profit only if the position is open.
                // Because we reach this code on each new bar is issued when high or low price channel boundary is exceeded
                //'trade_profit' => round($tradeProfit, 4),
                'trade_profit' => $tradeProfit,
            ]);
    }
}