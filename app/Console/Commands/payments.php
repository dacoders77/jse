<?php

namespace App\Console\Commands;

use Faker\Provider\DateTime;
use Illuminate\Console\Command;

class payments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jse:payments';

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
        $nonce = time();
        $uri = '/api/v1/wallet/payment';
        $apiToken = $_ENV['API_TOKEN'];
        $secret = $_ENV['SECRET'];
        $btcValletAddress = $_ENV['BTC_VALET_ADDRESS'];


        // CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.livingroomofsatoshi.com/api/v1/wallet/payment");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "api-token: b950a28a-528e-454e-8359-e31612da6525"
        ));

        $response = curl_exec($ch);
        curl_close($ch);

        dump(json_decode($response));
    }
}
