<?php
/**
 * Created by PhpStorm.
 * User: slinger
 * Date: 5/18/2018
 * Time: 3:15 PM
 */

namespace App\Classes\Indicators;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


/**
 * Class PriceChannel calculates price channel high and low values based on data read from DB.
 * Also SMA indicator is calculated which is used as a filter.
 * Trades are opened not when bar close value is higher (lower) the price channel but when the value of calculated SMA
 * is exceeds the price channel.
 * Values are recorded (updated) in DB when calculated.
 * This class is called in 3 cases:
 * 1. On the first start of the application when the DB is empty and contains no historical data.
 * 2. When a new bar is issued.
 *
 * @package App\Classes
 * @return void
 */
class PriceChannel
{
    public static function calculate($priceChannelPeriod, $tableName, $isInitialCalculation)
    {
        /**
         * @var int elementIndex Loop index. If the price channel period is 5 the loop will go from 0 to 4.
         * The loop is started on each candle while running through all candles in the array.
         */
        $elementIndex = 0;

        /* @var int $priceChannelHighValue Initial value for high value search*/
        $priceChannelHighValue = 0;

        /* @var int $priceChannelLowValue Initial value for low value search. Really big value is needed at the beginning.
        Then we compare current value with 999999. It is, $priceChannelLowValue = current value*/
        $priceChannelLowValue = 999999;

        /**
         * desc - from big values to small. asc - from small to big
         * in this case: desc. [0] element is the last record in DB.
         * Its id - quantity of records.
         */
        $records = DB::table($tableName)
            ->orderBy('time_stamp', 'desc')
            ->get();

        /* @var int $quantityOfBars The quantity of bars for which the price channel will be calculated */
        if ($isInitialCalculation){
            $quantityOfBars = (DB::table($tableName)
                    ->orderBy('id', 'desc')
                    ->first())->id - $priceChannelPeriod - 1;
        } else {
            $quantityOfBars = $priceChannelPeriod;
        }

        /**
         * Calculate price channel max, min.
         * First element in the array is the oldest. Accordingly to the chart - we start from the right end.
         * Start from the oldest element in the array which is on the right at the chart.
         * We go from right to the left.
         */
        foreach ($records as $record) {

            /**
             * Indexes go like this 0,1,2,3,4,5,6 from left to the right
             * We must stop before $requestBars reaches the end of the array
             */
            if ($elementIndex <= $quantityOfBars)
            {
                /* Go from right to left (from present to last bar). Records in DB are encasing */
                for ($i = $elementIndex ; $i < $elementIndex + $priceChannelPeriod; $i++)
                {
                    /* Find max value in interval */
                    if ($records[$i]->high > $priceChannelHighValue)
                        $priceChannelHighValue = $records[$i]->high;

                    /* Find low value in interval */
                    if ($records[$i]->low < $priceChannelLowValue)
                        $priceChannelLowValue = $records[$i]->low;
                }

                /* Calculate SMA */
                //\App\Classes\Indicators\Sma::calculate('close', 2, 'sma1', $tableName);


                /* Update high and low values, sma values in DB */
                DB::table($tableName)
                    ->where('time_stamp', $records[$elementIndex]->time_stamp)
                    ->update([
                        'price_channel_high_value' => $priceChannelHighValue,
                        'price_channel_low_value' => $priceChannelLowValue
                        //'sma1' => $sma / $smaPeriod,
                    ]);

                /* Reset high, low price channel values */
                $priceChannelHighValue = 0;
                $priceChannelLowValue = 999999;

            }
            elseif (false)
            {
                /**
                 * @todo 21.05.19 execute this code only when back testing mode is calculated
                 *
                 *  Update high and low values in DB for bars which were not used in calculation
                 *  There is a case when first price channel with period 5 is calculated
                 *  Then next price channel is calculated with period 6. This causes that calculated values from period 5
                 *  remain in DB and spoil the chart. The price channel lines start to contain both values in the same series.
                 *  In order to prevent this, for those bars that were not used for computation, price channel values are set to null
                 */
                DB::table($tableName)
                    ->where('time_stamp', $records[$elementIndex]->time_stamp)
                    ->update([
                        'price_channel_high_value' => null,
                        'price_channel_low_value' => null,
                        'sma1' => null
                    ]);
            }
            $elementIndex++;
        }

        /**
         * Make the last value of the calculated price channel equal to the value form the previous row.
         * This is needed to not let the price channel to squeeze.
         * If to disable this code, on each new bar both high and low price channel values will be equal to bar open.
         */
        DB::table($tableName)
            ->where('id', DB::table($tableName)->orderBy('time_stamp', 'desc')->take(1)->value('id'))
            ->update([
                'price_channel_high_value' => DB::table($tableName)->orderBy('time_stamp', 'desc')->skip(1)->take(1)->value('price_channel_high_value'),
                'price_channel_low_value' => DB::table($tableName)->orderBy('time_stamp', 'desc')->skip(1)->take(1)->value('price_channel_low_value'),
            ]);
    }
}

