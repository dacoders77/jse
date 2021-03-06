<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);

        /* Seed users */
        DB::table('users')->insert([
            'name' => 'slinger',
            'email' => 'nextbb@yandex.ru',
            'password' => bcrypt('659111')
        ]);

        DB::table('users')->insert([
            'name' => 'Jesse',
            'email' => 'Jesse@ravencapital.co',
            'password' => bcrypt('$1Raven1$')
        ]);

        DB::table('users')->insert([
            'name' => 'Alex_dze',
            'email' => 'aleksey.kirushin2015@yandex.ru',
            'password' => bcrypt('12345')
        ]);

        DB::table('users')->insert([
            'name' => 'nastya',
            'email' => 'art@nastya.com',
            'password' => bcrypt('nastya')
        ]);

        /* Seed bots */
        DB::table('bots')->insert([
            'created_at' => now(),
            'name' => 'Bot_01',
            'db_table_name' => 'bot_1',
            'account_id' => 1,
            'symbol_id' => 1,
            'offset' => 0,
            'execution_time' => 10,
            'time_range' => 40,
            'time_frame' => 1,
            'place_as_market' => true,
            'bars_to_load' => 50,
            'volume' => 1,
            'front_end_id' => '12345',
            'rate_limit' => 4,
            'status' => 'idle',
            'memo' => 'First big bot'
        ]);

        DB::table('bots')->insert([
            'created_at' => now(),
            'name' => 'Bot_02',
            'db_table_name' => 'bot_2',
            'account_id' => 2,
            'symbol_id' => 2,
            'offset' => -10,
            'execution_time' => 10,
            'time_range' => 180,
            'time_frame' => 5,
            'place_as_market' => true,
            'bars_to_load' => 125,
            'volume' => 45,
            'front_end_id' => '12346',
            'rate_limit' => 4,
            'status' => 'idle',
            'memo' => 'Another bot'
        ]);

        DB::table('bots')->insert([
            'created_at' => now(),
            'name' => 'Bot_slow',
            'db_table_name' => 'bot_3',
            'account_id' => 3,
            'symbol_id' => 1,
            'offset' => -10,
            'execution_time' => 10,
            'time_range' => 180,
            'time_frame' => 5,
            'place_as_market' => true,
            'bars_to_load' => 11,
            'volume' => 177,
            'front_end_id' => '12347',
            'rate_limit' => 4,
            'status' => 'idle',
            'memo' => "Obama's bot"
        ]);

        DB::table('bots')->insert([
            'created_at' => now(),
            'name' => 'Bot_fast',
            'db_table_name' => 'bot_4',
            'account_id' => 4,
            'symbol_id' => 2,
            'offset' => -10,
            'execution_time' => 10,
            'time_range' => 180,
            'time_frame' => 5,
            'place_as_market' => true,
            'bars_to_load' => 400,
            'volume' => 1200,
            'front_end_id' => '12348',
            'rate_limit' => 7,
            'status' => 'idle',
            'memo' => "Putin's bot"
        ]);

        /* Seed exchanges */
        DB::table('exchanges')->insert([
            //'name' => Str::random(10),
            'created_at' => now(),
            'name' => 'Bitmex',
            'url' => 'http://www.bitmex.com',
            'live_api_path' => 'http://api.bitmex.com',
            'testnet_api_path' => 'http://testnet.bitmex.com',
            'status' => 'online',
            'memo' => 'Main'
        ]);

        DB::table('exchanges')->insert([
            'created_at' => now(),
            'name' => 'Kraken',
            'url' => 'http://www.kraken.com',
            'live_api_path' => 'http://api.kraken.com',
            'testnet_api_path' => 'http://test.kraken.com',
            'status' => 'offline',
            'memo' => 'Main 2'
        ]);

        DB::table('exchanges')->insert([
            'created_at' => now(),
            'name' => 'Derebit',
            'url' => 'http://www.derebit.com',
            'live_api_path' => 'http://derebit.com',
            'testnet_api_path' => 'http://derebit.com',
            'status' => 'offline',
            'memo' => 'For testing'
        ]);

        /* Seed accounts */
        DB::table('accounts')->insert([
            'created_at' => now(),
            'name' => 'Alexkir-working-testnet-account',
            'exchange_id' => 1,
            'bot_id' => 1,
            'api' => 'wb89vufuY6R2zBHGYkduz_bi',
            'api_secret' => 'ZJ3B5lK0hhya0fM-YqR2pa7CfqPGeib-9ZKN_MynoJfaCn3R',
            'status' => 'ok',
            'is_testnet' => true,
            'memo' => 'Alexkir testnet acc. cool.kaku2012@yandex.ru / asldueDkd87'
        ]);

        DB::table('accounts')->insert([
            'created_at' => now(),
            'name' => 'No money',
            'exchange_id' => 1,
            'bot_id' => 2,
            'api' => 'AdpGKvlnElQmowv-SgKu9kiF',
            'api_secret' => 'KrcRtZ8SfAx_4xOSEm1DHon1gPF2wcSHPVZkyJ7SmOmCX0j1',
            'status' => 'ok',
            'is_testnet' => false,
            'memo' => 'live account'
        ]);

        DB::table('accounts')->insert([
            'created_at' => now(),
            'name' => 'Putin',
            'exchange_id' => 1,
            'bot_id' => 3,
            'api' => '123',
            'api_secret' => '456',
            'status' => 'ok',
            'is_testnet' => true,
            'memo' => 'Good is good'
        ]);

        DB::table('accounts')->insert([
            'created_at' => now(),
            'name' => 'Obama',
            'exchange_id' => 2,
            'bot_id' => 4,
            'api' => '123',
            'api_secret' => '456',
            'status' => 'ok',
            'is_testnet' => true,
            'memo' => 'Good is good'
        ]);

        DB::table('accounts')->insert([
            'created_at' => now(),
            'name' => 'JSE super test demo acc',
            'exchange_id' => 1,
            'bot_id' => 1,
            'api' => 'ikeCK-6ZRWtItOkqvqo8F6wO',
            'api_secret' => 'JfmMTXx3YruSP3OSBKQvULTg4sgQJKZkFI2Zy7TZXniOUbeK',
            'status' => 'ok',
            'is_testnet' => true,
            'memo' => 'This is a test net. This name can be pretty long. What are we gonna do with such length?'
        ]);

        /* Seed symbols */
        DB::table('symbols')->insert([
            'created_at' => now(),
            'exchange_id' => 1,
            'execution_symbol_name' => 'BTC/USD',
            'history_symbol_name' => 'XBTUSD',
            'commission' => -0.0075,
            'is_active' => true,
            'memo' => 'Execution and history symbol names are different'
        ]);

        DB::table('symbols')->insert([
            'created_at' => now(),
            'exchange_id' => 1,
            'execution_symbol_name' => 'ETH/USD',
            'history_symbol_name' => 'ETHUSD',
            'commission' => -0.0075,
            'is_active' => true,
            'memo' => 'Name is the same'
        ]);

        DB::table('symbols')->insert([
            'created_at' => now(),
            'exchange_id' => 2,
            'execution_symbol_name' => 'ADAM19',
            'history_symbol_name' => 'ADAM19',
            'commission' => 0.025,
            'is_active' => false,
            'memo' => 'a futures'
        ]);

        /* Seed strategy types */
        DB::table('strategy_types')->insert([
            'created_at' => now(),
            'name' => 'Price channel',
            'memo' => 'This is a price channel strategy'
        ]);

        DB::table('strategy_types')->insert([
            'created_at' => now(),
            'name' => 'MACD',
            'memo' => 'MACD strategy'
        ]);

        /* Seed pricechannel settings */
        DB::table('pricechannel_settings')->insert([
            'created_at' => now(),
            'time_frame' => 1,
            'sma_filter_period' => 1,
            'memo' => 'Price channel settings memo goes here'
        ]);

        DB::table('pricechannel_settings')->insert([
            'created_at' => now(),
            'time_frame' => 1,
            'sma_filter_period' => 2,
            'memo' => 'Price channel settings memo goes here'
        ]);

        /* Seed MACD settings */
        DB::table('macd_settings')->insert([
            'created_at' => now(),
            'ema_period' => 2,
            'macd_line_period' => 2,
            'macd_signalline_period' => 5,
            'memo' => 'Macd settings memo goes here'
        ]);

        DB::table('macd_settings')->insert([
            'created_at' => now(),
            'ema_period' => 3,
            'macd_line_period' => 3,
            'macd_signalline_period' => 10,
            'memo' => 'In macd we trust!'
        ]);

        DB::table('macd_settings')->insert([
            'created_at' => now(),
            'ema_period' => 5,
            'macd_line_period' => 4,
            'macd_signalline_period' => 23,
            'memo' => 'What do you think about macd?'
        ]);


        /* Seed strategies */
        DB::table('strategies')->insert([
            'created_at' => now(),
            'name' => 'PC strategy long',
            'strategy_type_id' => 1, // 1 - price channel, 2 - macd
            'is_active' => true,
            'pricechannel_settings_id' => 1,
            'macd_settings_id' => null,
            'memo' => 'Memo about PC strategy #1',
        ]);

        DB::table('strategies')->insert([
            'created_at' => now(),
            'name' => 'PC very long ',
            'strategy_type_id' => 1,
            'is_active' => true,
            'pricechannel_settings_id' => 2,
            'macd_settings_id' => null,
            'memo' => 'Memo about PC strategy #2',
        ]);

        DB::table('strategies')->insert([
            'created_at' => now(),
            'name' => 'MACD id=1',
            'strategy_type_id' => 2,
            'is_active' => true,
            'pricechannel_settings_id' => null,
            'macd_settings_id' => 1,
            'memo' => 'Memo about MACD strategy #1',
        ]);

        DB::table('strategies')->insert([
            'created_at' => now(),
            'name' => 'Good old MACD',
            'strategy_type_id' => 2,
            'is_active' => true,
            'pricechannel_settings_id' => null,
            'macd_settings_id' => 2,
            'memo' => 'Memo about MACD strategy #2',
        ]);

        DB::table('strategies')->insert([
            'created_at' => now(),
            'name' => 'In MACD we trust',
            'strategy_type_id' => 2,
            'is_active' => true,
            'pricechannel_settings_id' => null,
            'macd_settings_id' => 3,
            'memo' => 'Macd memo #3',
        ]);

        /* Update strategy_id in Bots */
        DB::table('bots')
            ->where('id', 1)
            ->update([
            'strategy_id' => 1
        ]);

        DB::table('bots')
            ->where('id', 2)
            ->update([
                'strategy_id' => 2
            ]);

        DB::table('bots')
            ->where('id', 3)
            ->update([
                'strategy_id' => 3
            ]);

        DB::table('bots')
            ->where('id', 4)
            ->update([
                'strategy_id' => 4
            ]);

        /* Seed logo */
        DB::table('settings')->insert([
            'key' => 'logo',
            'value' => 'vue-logo.png'
        ]);

        /* Seed app name */
        DB::table('settings')->insert([
            'key' => 'app_name',
            'value' => 'JSEBOT'
        ]);

        /* Seed allow bots */
        DB::table('settings')->insert([
            'key' => 'allow_bots',
            'value' => true
        ]);

        /* Seed allow back tester */
        DB::table('settings')->insert([
            'key' => 'allow_backtester',
            'value' => true
        ]);


        /* Seed historical bars */
        $this->call([
            HistoryBarsSeeder1::class
        ]);

        $this->call([
            HistoryBarsSeeder2::class
        ]);

        $this->call([
            HistoryBarsSeeder3::class
        ]);

        $this->call([
            HistoryBarsSeeder4::class
        ]);

        $this->call([
            SignalsSeeder::class
        ]);

        $this->call([
            SignalSeeder1::class
        ]);

        $this->call([
            SignalSeeder2::class
        ]);
    }
}
