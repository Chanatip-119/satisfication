<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index()
    {
        $staffs = Staff::all();
        return view('staff.index', compact('staffs'));
    }

    public function create()
    {
        return view('staff.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|integer',
            'staff_name' => 'required|string|max:255',
            'staff_email' => 'required|string|max:255',
            'staff_pincode' => 'required|string|max:255',
            'role_id' => 'required|integer'
        ]);

        Staff::create($request->all());

        return redirect()->route('staff.index')->with('success', 'เพิ่มข้อมูลบุคลากรสำเร็จ');
    }
}
