<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Repositories\ChannelRepository;
use App\Http\Requests\StoreChannelRequest;

class ChannelController extends Controller
{
    public function __construct(ChannelRepository $channels)
    {
        $this->middleware('auth');

        $this->channels = $channels;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        throw new Exception('Not yet implemented');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        throw new Exception('Not yet implemented');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreChannelRequest $request)
    {
        $input = $request->input();

        $id = $this->channels->store($input);

        $channel = $this->channelsannels->getById($id);

        if (! empty($channel)) {
            return response()->json($channel);
        }

        return response()->json(['message' => 'Something went wrong while storing the new channel, check the logs.'], 400);
    }

    /**
     * Display the specified resource.
     *
     * @param  int                       $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $start = Carbon::now();
        $end = Carbon::now();

        return [
            'label' => 'telefonisch',
            'openinghours' => [
                [
                    'active' => true,
                    'start_date' => $start->subMonth()->toDateString(),
                    'end_date' => $end->subMonth()->addYear()->toDateString(),
                    'id' => 5
                ]
            ]
        ];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int                       $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        throw new Exception('Not yet implemented');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int                       $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $input = $request->input();

        $success = $this->channels->update($id, $input);

        if ($success) {
            return response()->json($this->channels->getById($id));
        }

        return response()->json(['message' => 'Something went wrong while updating the channel, check the logs.'], 400);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int                       $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->channels->delete($id);
    }
}
