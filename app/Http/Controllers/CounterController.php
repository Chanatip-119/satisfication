<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterSub;
use Illuminate\Http\Request;

class CounterController extends Controller
{
    public function index()
    {
        $counters = Counter::with('countersubs')->get();
        return view('counter.index', compact('counters'));
    }

    public function create()
    {
        return view('counter.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'counter_id' => 'required|integer',
            'counter_location' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        Counter::create($validated);

        return redirect()->route('counter.index')->with('success', 'เพิ่มข้อมูลเคาน์เตอร์สำเร็จ');
    }

    public function storeSub(Request $request, $counterId)
    {
        $validated = $request->validate([
            'counter_sub_id' => 'required|integer',
            'counter_id' => 'required|integer|exists:counter,counter_id',
            'is_active' => 'required|boolean',
        ]);

        $counter = Counter::findOrFail($counterId);
        $counter->countersubs()->create($validated);

        return redirect()->route('counter.index')->with('success', 'เพิ่มข้อมูลเคาน์เตอร์ย่อยสำเร็จ');
        }
    }
