<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InstructorUpdateCycle;

class InstructorUpdateCycleController extends Controller
{
    public function updateStatus(Request $request)
    {
        $request->validate([
            'id' => ['required', 'exists:instructor_update_cycles,id'],
            'status' => ['required', 'in:updated,before_update,no_update,pending'],
        ]);

        $cycle = InstructorUpdateCycle::findOrFail($request->id);

        $cycle->status = $request->status;
        $cycle->save();

        $message = match ($request->status) {
            'no_update' => 'キャンセルを受け付けました。',
            default => '更新申請を送信しました。',
        };
        
        return back()->with('success', $message);
    }
}