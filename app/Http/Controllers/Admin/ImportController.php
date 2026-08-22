<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\MemberImport;
use App\Imports\InvoiceImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Import/Index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'type' => ['required', 'in:member,invoice'],
        ]);
        // ファイル名で種別を判定
        $fileName = $request->file('file')->getClientOriginalName();

        $expectedPrefix = match ($request->type) {
            'member'  => 'jsrr_member',
            'invoice' => 'jsrr_invoice',
        };

        if (!str_contains($fileName, $expectedPrefix)) {
            $typeLabel = $request->type === 'member' ? '会員情報' : '請求情報';
            return back()->withErrors([
                'file' => "ファイルが正しくありません。{$typeLabel}用のファイルを選択してください。",
            ]);
        }

        $import = match ($request->type) {
            'member'  => new MemberImport(),
            'invoice' => new InvoiceImport(),
        };

        Excel::import($import, $request->file('file'));

        return back()->with('result', [
            'type'   => $request->type,
            'insert' => $import->insertCount,
            'update' => $import->updateCount,
            'skip'   => $import->skipCount,
            'errors' => $import->errors,
        ]);
    }
}