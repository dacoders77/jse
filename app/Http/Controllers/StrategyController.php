<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Strategy;
use App\MacdSettings;
use App\PricechannelSettings;
use App\Bot;


class StrategyController extends Controller
{
    private static $PricechannelSettingsAddedId;
    private static $MacdSettingsAddedId;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Strategy::paginate();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            /* General settings */
            'name' => 'required|string|max:30',
            'strategy_type_id' => 'required|numeric',
            'memo' => 'max:50',
        ]);

        /**
         * If strategy_type_ip = 1
         * Price channel
         */
        if ($request['strategy_type_id'] == '1') {
            $this->validate($request,[
                /* Strategy settings row */
                'pricechannel_settings_id' => 'numeric|nullable',
                /* Price channel */
                /*'time_frame' => ['nullable', Rule::in(['1', '5'])],*/
                'time_frame' => 'required|numeric|max:50',
                'sma_filter_period' => 'required|numeric|max:5',
            ]);
            /* Insert settings to pricechanel_settings table */
            PricechannelSettings::create([
                'time_frame' => $request['time_frame'],
                'sma_filter_period' => $request['sma_filter_period']
            ]);
            // Get the added id
            self::$PricechannelSettingsAddedId = PricechannelSettings::orderby('id', 'desc')->take(1)->value('id');
        }

        /**
         * Macd
         */
        if ($request['strategy_type_id'] == '2') {
            $this->validate($request,[
                /* Strategy settings row */
                'macd_settings_id' => 'numeric|nullable',
                /* Macd */
                'ema_period' => 'numeric|required',
                'macd_line_period' => 'numeric|required',
                'macd_signalline_period' => 'numeric|required',
            ]);
            MacdSettings::create([
                'ema_period' => $request['ema_period'],
                'macd_line_period' => $request['macd_line_period'],
                'macd_signalline_period' => $request['macd_signalline_period']
            ]);
            self::$MacdSettingsAddedId = MacdSettings::orderby('id', 'desc')->take(1)->value('id');
        }

        Strategy::create([
            'name' => $request['name'],
            'strategy_type_id' => $request['strategy_type_id'],
            'pricechannel_settings_id' => self::$PricechannelSettingsAddedId , //
            'macd_settings_id' => self::$MacdSettingsAddedId ,
            'is_active' => $request['is_active'],
            'memo' => $request['memo']
        ]);
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return ['pricechannel_settings' => PricechannelSettings::all(), 'macd_settings' => MacdSettings::all()];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $strategy = Strategy::findOrFail($id);

        if (Bot::where('strategy_id', $id)->exists())
            if (Bot::where('strategy_id', $id)->get()[0]['status'] == 'running' )
                throw new \Exception('Update fail. This strategy is running. Id: ' .
                    Bot::where('strategy_id', $id)->get()[0]['id'] . ' Name: ' . Bot::where('strategy_id', $id)->get()[0]['name']);

        $this->validate($request,[
            /* General settings */
            'name' => 'required|string|max:30',
            'strategy_type_id' => 'required|numeric',
            'memo' => 'max:50',
        ]);

        /* Only in pricechannel_settings_is is set - it means that a price channel strategy is being edited */
        if ($request['pricechannel_settings_id']){
            $this->validate($request,[
                /* Strategy settings row */
                'pricechannel_settings_id' => 'numeric|nullable',
                /* Price channel */
                /*'time_frame' => ['nullable', Rule::in(['1', '5'])],*/
                'time_frame' => 'required|numeric|max:50',
                'sma_filter_period' => 'required|numeric|max:5',
            ]);
            $pricechannelSettings = PricechannelSettings::findOrFail($request['pricechannel_settings_id']);
        }

        if ($request['macd_settings_id']){
            $this->validate($request,[
                /* Strategy settings row */
                'macd_settings_id' => 'numeric|nullable',
                /* Macd */
                'ema_period' => 'numeric|required',
                'macd_line_period' => 'numeric|required',
                'macd_signalline_period' => 'numeric|required',
            ]);
            $macdSettings = MacdSettings::findOrFail($request['macd_settings_id']);
        }


        /*$this->validate($request,[
            'name' => 'required|string|max:20',
            'strategy_type_id' => 'required|numeric',
            'pricechannel_settings_id' => 'numeric|nullable',
            'macd_settings_id' => 'numeric|nullable',
            // Price channel
            //'time_frame' => ['nullable', Rule::in(['1', '5'])],
            'time_frame' => 'nullable|numeric|max:50',
            'sma_filter_period' => 'numeric|nullable',
            // Macd
            'ema_period' => 'numeric|nullable',
            'macd_line_period' => 'numeric|nullable',
            'macd_signalline_period' => 'numeric|nullable',
            'memo' => 'max:50'
        ]);*/

        /**
         * We send the same request to different models. It is ok.
         * Only correspondent fields will be updated.
         * $request contains all fields at the same time.
         * For example sma_filter_period is the field for PricechannelSettings model - it will be update there
         * and will not be updated in Strategy.
         */
        $strategy->update($request->all());
        if ($request['pricechannel_settings_id']) $pricechannelSettings->update($request->all());
        if ($request['macd_settings_id']) $macdSettings->update($request->all());

        return ['message' => 'Updated  strategy'];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy($id)
    {
        if (Bot::where('strategy_id', $id)->exists())
            if (Bot::where('strategy_id', $id)->get()[0]['status'] == 'running' )
                throw new \Exception('Update fail. This strategy is running. Id: ' .
                    Bot::where('strategy_id', $id)->get()[0]['id'] . ' Name: ' . Bot::where('strategy_id', $id)->get()[0]['name']);

        Strategy::findOrFail($id)->delete();
    }
}
