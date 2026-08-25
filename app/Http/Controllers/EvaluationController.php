<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use app\Models\CounterSub;
use App\Models\Checkin;
use App\Models\Evaluation;
use App\Models\Staff;
use app\Models\Qr_Code;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EvaluationController extends Controller
{
    public function index()
    {
        $evaluations = Evaluation::all();
        return view('evaluations.index', compact('evaluations'));
    }

    public function create()
    {
        return view('evaluations.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:255',
            'checkin_id' => 'required|integer',
        ]);

        Evaluation::create($request->all());

        return redirect()->route('evaluation.create')->with('success', 'เพิ่มข้อมูลการประเมินสำเร็จ');
    }
}
