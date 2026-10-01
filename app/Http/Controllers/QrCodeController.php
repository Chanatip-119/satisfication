<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CounterSub;
use App\Models\Schedule;

class QrCodeController extends Controller
{
    public function index()
    {
        // Fetch all counter subs with their related counter
        $counterSubs = CounterSub::with('counter')->get();
        
        // Fetch the latest schedule for each sub to potentially show the time
        $schedules = Schedule::whereIn('counter_sub_id', $counterSubs->pluck('counter_sub_id'))
            ->orderBy('start_time', 'asc')
            ->get()
            ->groupBy('counter_sub_id');

        return view('qrcode.index', compact('counterSubs', 'schedules'));
    }
}
