<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use TCPDF_FONTS;
use setasign\Fpdi\Tcpdf\Fpdi;
use Carbon\Carbon;
use App\Models\Application;
use Imagick;
use Illuminate\Validation\Rule;
use Illuminate\Http\UploadedFile;
use App\Mail\MemberRegistrationCompleted;
use App\Mail\AgentRegistrationCompleted;
use App\Mail\PreRegisterMail;

class ApplicationController extends Controller
{
    public function index(Request $request) 
    {
        $user = Auth::user();

        $name = trim((string) $request->input('name'));
        $applicationDate = $request->input('application_date');
        $deliveryDate = $request->input('delivery_date');

        $allowedSorts = 
        [
            'created_at', 'gender', 'order_code', 'id', 'name', 'age_at_death', 'delivery_date', 'deceased_furigana', 'status'
        ];

        $sortBy  = in_array($request->input('sort_by'), $allowedSorts)
            ? $request->input('sort_by')
            : 'created_at';

        $sortDir = $request->input('sort_dir') === 'asc' ? 'asc' : 'desc';

        $per_page = $request->input('per_page') ?? 20;

        $query = Application::where('organization_id', $user->organization_id);

        if ($name !== '') {
            $keywords = preg_split('/\s+/', $name);

            foreach ($keywords as $word) {
                $query->where(function ($sub) use ($word) {
                    $sub->where('last_name', 'like', "%{$word}%")
                        ->orWhere('first_name', 'like', "%{$word}%");
                });
            }
        }
        
        if ($sortBy === 'name') {
            $query->orderBy('last_name', $sortDir)
                ->orderBy('first_name', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $applications = $query
            ->when($request->filled('application_date'), fn($q) =>
                $q->whereDate('application_date', $applicationDate)
            )
            ->when($request->filled('delivery_date'), fn($q) =>
                $q->whereDate('delivery_date', $deliveryDate)
            )
            ->paginate($per_page)
            ->withQueryString();
            
        return Inertia::render('Applications/Index', [
            'user' => $user,
            'applications' => $applications,
            'filter' => [
                'per_page'      => $per_page,
                'name'          => $name,
                'delivery_date' => $deliveryDate,
                'application_date' => $applicationDate,
            ],
        ]);

    }


    public function create(Request $request)
    {
        $user = Auth::user();
        $now = Carbon::now();

        // 納期計算
        if ($now->hour < 15) {
            $delivery = $now->copy()->addHours(3);
        } else {
            $delivery = $now->copy()->addDay()->setTime(12, 0);
        }

        // 30分丸め
        $minute = $delivery->minute;

        if ($minute > 0 && $minute <= 30) {
            $delivery->setMinute(30);
        } elseif ($minute > 30) {
            $delivery->addHour()->setMinute(0);
        }

        $delivery->setSecond(0);

        $defaultFuneral = Carbon::today()
            ->addDays(2)
            ->setTime(12, 0);

        return Inertia::render('Applications/Create', [
            'defaultFuneralDatetime' => $defaultFuneral->format('Y-m-d\TH:i'),
            'minFuneralDatetime' => Carbon::today()
                ->addDays(2)
                ->setTime(0, 0)
                ->format('Y-m-d\TH:i'),
            'application_date' => $now->format('Y-m-d H:i'),
            'delivery_date' => $delivery->format('Y-m-d H:i'),
            'user' => [
                'hall_name' => $user->hall_name,
                'tel' => $user->tel,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'last_name'     => 'required|string',
            'first_name'     => 'required|string',
            'deceased_furigana' => 'required|string',
            'gender'            => 'required|string',
            'chief_mourner_name'=> 'nullable|string',
            'age_at_death'      => 'nullable|integer',
            'relationship_to_deceased' => 'nullable|string',
            'delivery_date'     => 'required|string',
            'funeral_datetime'  => 'required|string',
            'spouse_status'     => 'nullable|string',
            'children_count'    => 'nullable|integer',
            'grandchildren_count'=> 'nullable|integer',
            'staff_name'        => 'required|string',
            'bg_color'          => 'nullable|string',
            'text_color'        => 'nullable|string',
            'traits'            => 'nullable|array',
            'note'              => 'nullable|string',
        ]);

        $data['organization_id'] = auth()->user()->organization_id;

        Application::create($data);

        return redirect()->route('applications.create')
            ->with('success', '申込を受け付けました。今、しばらくお待ちください。');
    }

    public function edit(Request $request)
    {

    }

    public function show(Request $request)
    {

    }
    
    protected function sendCompletedMails(Member $member, $preUser, $corp, $agent = null): void
    {

        $toUser = filter_var($preUser->email, FILTER_VALIDATE_EMAIL)
            ? $preUser->email
            : null;

        $toCorp = filter_var($corp['email'] ?? null, FILTER_VALIDATE_EMAIL)
            ? $corp['email']
            : null;

        // 本人／代理人宛
        if ($toUser) {
            try {
                $data = $agent ?? $corp; // 代理人がいれば agent、それ以外は member
                Mail::to($toUser)
                    ->send(new MemberRegistrationCompleted($data));

                $member->user_mail_sent_at = now();

            } catch (\Throwable $e) {
                Log::error('SES user mail send failed', [
                    'member_id' => $member->id ?? null,
                    'to' => $toUser,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // corp 宛（agent の場合のみ or mail がある場合）
        if ($toCorp) {
            try {
                Mail::to($toCorp)
                    ->send(new AgentRegistrationCompleted($corp));

                $member->corp_mail_sent_at = now();

            } catch (\Throwable $e) {
                Log::error('SES corp mail send failed', [
                    'member_id' => $member->id ?? null,
                    'to' => $toCorp,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        //メール送信結果をDBへ保存    
        $member->save();
    }

 
    // Apuls Pdf Create
    public function pdfCreate()
    {
        $form = session('member_form', [
            'company_furigana' => '',
            'representative_furigana' => '',
            'company_name' => '',
            'representative' => '',
            'address_zip' => '',
            'address' => '',
            'tel' => '',
            'bank_name' => '',
            'branch_name' => '',
            'account_type' => '普通',
            'account_no' => '',
            'account_kana' => '',
            'account_name' => '',
        ]);

        return Inertia::render('Members/PdfCreate', [
            'form' => $form
        ]);
    }
    // Apuls Pdf Generate
    public function pdfGenerate(Request $request, string $token)
    {
        $rules = [

            // ===== 基本情報 =====
            'type' => 'required|string',
            'company_kana' => 'required|string',
            'rep_last_kana' => 'required|string',
            'rep_first_kana' => 'required|string',
            'company_type_prefix' => 'required|string',
            'company_name' => 'required|string',
            'company_type_suffix' => 'nullable|string',
            'rep_last_name' => 'required|string',
            'rep_first_name' => 'required|string',
            'same_as_corp' => 'boolean',
            'is_agent' => 'boolean',

            // ===== 法人（corp）=====
            'corp' => 'required|array',
            'corp.type' => 'required|integer',
            'corp.postal_code' => 'required|string',
            'corp.address1' => 'required|string',
            'corp.address2' => 'required|string',
            'corp.address3' => 'nullable|string',
            'corp.tel' => 'required|string',
            'corp.fax' => 'nullable|string',
            'corp.mobile' => 'nullable|string',
            'corp.position' => 'required|string',
            'corp.last_name' => 'required|string',
            'corp.first_name' => 'required|string',

            // ===== 郵送先（mail）=====
            'mail' => 'required|array',
            'mail.type' => 'required|integer',
            'mail.postal_code' => 'nullable|string',
            'mail.address1' => 'nullable|string',
            'mail.address2' => 'nullable|string',
            'mail.address3' => 'nullable|string',
            'mail.tel' => 'nullable|string',
            'mail.fax' => 'nullable|string',
            'mail.mobile' => 'nullable|string',
            'mail.email' => 'nullable|email',
            'mail.position' => 'nullable|string',
            'mail.last_name' => 'nullable|string',
            'mail.first_name' => 'nullable|string',

            // ===== 銀行 =====
            'bank_type' => 'required|string',
            'bank_name' => 'required|string',
            'bank_code' => 'required|string',
            'branch_code' => 'required|string',
            'account_type' => 'required|string',
            'account_no' => 'required|string',
            'account_kana' => 'required|string',
            'account_name' => 'required|string',
        ];

        if ($request->bank_code !== '9900') {
            $rules['branch_name'] = 'required|string';
        }
        if ($request->boolean('is_agent')) {
            $rules = array_merge($rules, [
                'corp.email' => 'required|email',
                'agent' => 'required|array',
                'agent.type' => 'required|integer',
                'agent.company_name' => 'required|string',
                'agent.postal_code' => 'required|string',
                'agent.address1' => 'required|string',
                'agent.address2' => 'nullable|string',
                'agent.address3' => 'nullable|string',
                'agent.tel' => 'required|string',
                'agent.fax' => 'nullable|string',
                'agent.mobile' => 'nullable|string',
                'agent.position' => 'required|string',
                'agent.last_name' => 'required|string',
                'agent.first_name' => 'required|string',
            ]);
        }
        // 法人：履歴事項全部証明書
        $rules = array_merge($rules, [
            'history_certificate' => [
                'nullable',
                'file',
                'mimes:pdf',
                function ($attr, $value, $fail) use ($request) {
                    if (
                        $request->input('type') === 'corporation'
                        && !$request->file('history_certificate')
                        && !$request->input('history_certificate_path')
                    ) {
                    }
                },
            ],
        ]);


        // 郵送先が別：郵送先確認資料
        $rules = array_merge($rules, [
            'mail_address_certificate' => [
                'nullable',
                'file',
                'mimes:pdf',
                function ($attr, $value, $fail) use ($request) {
                    if (
                        !$request->boolean('same_as_corp')
                        && !$request->file('mail_address_certificate')
                        && !$request->input('mail_address_certificate_path')
                    ) {
                    }
                },
            ],
        ]);     

        $request->validate($rules);
// ---- validation ここまで　ーーーー//
        // 仮登録ユーザー取得（email 用）
        $preUser = PreUser::where('token', $token)->firstOrFail();

        $files = session('member_files', []);

        $form = $request->except([
            'history_certificate',
            'mail_address_certificate',
        ]);
        if ($request->hasFile('history_certificate')) {
            [$historyPath, $historyThumb] =
                $this->storePdfWithThumbnail(
                    $request->file('history_certificate'),
                    'members/history_certificates'
                );
        } else {
            $historyPath  = $form['history_certificate_path'] ?? null;
            $historyThumb = $form['history_certificate_thumbnail'] ?? null;
        }

        $form['history_certificate_path'] = $historyPath;
        $form['history_certificate_thumbnail'] = $historyThumb;

        if ($request->hasFile('mail_address_certificate')) {
            [$mailPath, $mailThumb] =
                $this->storePdfWithThumbnail(
                    $request->file('mail_address_certificate'),
                    'members/mail_address_certificates'
                );
        } else {
            $mailPath  = $form['mail_address_certificate_path'] ?? null;
            $mailThumb = $form['mail_address_certificate_thumbnail'] ?? null;
        }

        $form['mail_address_certificate_path'] = $mailPath;
        $form['mail_address_certificate_thumbnail'] = $mailThumb;


        // session に保存
        session([
            'member_form' => $form,
        ]);

                // FPDI + TCPDF
        $pdf = new Fpdi();
        // ページ追加
        $pdf->AddPage();

        // 既存PDFテンプレート読み込み
        $templatePath = storage_path('app/templates/order_sheet/poem.pdf');
        $pageCount = $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);
        $pdf->useTemplate($tpl);
        //$pdf->useTemplate($tpl, 0, 0, 0, 0, true);


        // TCPDF同梱の日本語フォント
        $pdf->AddFont('kozminproregular', '', 'kozminproregular.php', true);
        $pdf->SetFont('kozminproregular', '', 12);

        // ---- 1) 契約者名（フリガナ）
        $pdf->SetXY(50, 65);
        $pdf->Write(8, $form['company_kana']??'');

        // ---- 2) 契約者名（漢字）
        $pdf->SetXY(50, 73);
        $pdf->Write(8, ($form['company_type_prefix']??'') . ($form['company_name']) . ($form['company_type_suffix']??''));

        $pdf->SetXY(50, 80);
        $pdf->Write(8, $form['corp']['position'] ?? '');
        $pdf->SetXY(80, 80);
        $pdf->Write(8, ($form['rep_last_name']??'') . ($form['rep_first_name']??''));

        // ---- 3) zip code
        $pdf->SetXY(50, 90);
        $pdf->Write(7, $form['corp']['postal_code'] ?? null);

        $address = ($form['corp']['address1']??'') . ($form['corp']['address2']??'') . ($form['corp']['address3']??'');
        // ---- 3) 住所
        $pdf->SetXY(50, 95);
        $pdf->Write(8, $address);

        // ---- 4) 電話番号
        $tel = $form['corp']['tel'] ?? ''; // 例: 03-1234-5678
        if ($tel != '') {
            $parts = explode('-', $tel); // '-' で分割

            // 開始座標
            $x = 139;
            $y = 99;
            $widths = [15, 15, 15];
            $height = 8;

            $pdf->SetY($y);
            for ($i = 0; $i < count($parts); $i++) {
                $pdf->SetX($x);
                $pdf->Cell($widths[$i], $height, $parts[$i], 0, 0, 'C'); // 枠付き中央寄せ
                $x += $widths[$i] + 2; // 次の枠との間隔 2mm
            }
        }




        // ゆうちょ銀行の場合
        if ($form['bank_code'] == '9900') {
            // ---- ゆうちょ記号
            $x = 26;
            $y = 161;
            $width = 17; // 枠幅
            $code = $form['branch_code'];

            if (strlen($code) === 5) {
                $chars = substr($code, 1, 3);
            } else {
                $chars = $code;
            }
            $chars = str_split($chars);
            $cellWidth = $width / count($chars);

            $pdf->SetXY($x, $y);
            foreach ($chars as $c) {
                $pdf->Cell($cellWidth, 10, $c, 0, 0, 'C'); // 'C'で文字中央に
            }

            // 口座番号
            $x = 55;
            $y = 161;
            $width = 45; // 枠幅
            $chars = str_split($form['account_no']);
            $cellWidth = $width / count($chars);

            $pdf->SetXY($x, $y);
            foreach ($chars as $c) {
                $pdf->Cell($cellWidth, 10, $c, 0, 0, 'C'); // 'C'で文字中央に
            }

        } else {
            // ---- 5) 銀行コード
            $x = 125;
            $y = 135;
            $width = 23; // 枠幅
            $chars = str_split($form['bank_code']);
            $cellWidth = $width / count($chars);

            $pdf->SetXY($x, $y);
            foreach ($chars as $c) {
                $pdf->Cell($cellWidth, 10, $c, 0, 0, 'C'); // 'C'で文字中央に
            }        

            // ---- 6) 支店コード
            $x = 174;
            $y = 135;
            $width = 17; // 枠幅
            $chars = str_split($form['branch_code']);
            $cellWidth = $width / count($chars);

            $pdf->SetXY($x, $y);
            foreach ($chars as $c) {
                $pdf->Cell($cellWidth, 10, $c, 0, 0, 'C'); // 'C'で文字中央に
            }        

            // ---- 5) 銀行名
            $pdf->SetXY(105, 145);
            $pdf->Write(8, $form['bank_name']);

            // ---- 6) 支店名
            $pdf->SetXY(150, 145);
            $pdf->Write(8, $form['branch_name']);

            // ---- 7) 預金種目（普通 / 当座 → マル）
            if ($form['account_type'] === '普通') {
                $pdf->SetXY(103, 160);
            } else {
                $pdf->SetXY(128, 160);
            }
            $pdf->Write(8, '〇');
            // 口座番号    
            $x = 149;
            $y = 161;
            $width = 43; // 枠幅
            $chars = str_split($form['account_no']);
            $cellWidth = $width / count($chars);

            $pdf->SetXY($x, $y);
            foreach ($chars as $c) {
                $pdf->Cell($cellWidth, 10, $c, 0, 0, 'C'); // 'C'で文字中央に
            }
            
        }

        // ---- 9) 口座名義（フリガナ）
        $pdf->SetXY(35, 170);
        $pdf->Write(8, $form['account_kana']);

        // ---- 10) 口座名義（漢字）
        $pdf->SetXY(35, 190);
        $pdf->Write(8, $form['account_name']);

        if ($form['is_agent']) {

            // 2ページ目を追加
            $pdf->AddPage();

            $templatePath2 = storage_path('app/templates/entry.pdf');
            $pageCount2 = $pdf->setSourceFile($templatePath2);
            $tpl2 = $pdf->importPage(1);
            $pdf->useTemplate($tpl2);

            // ---- 1) 契約者名（フリガナ）
            $pdf->SetXY(50, 76);
            $pdf->Write(8, $form['company_kana']??'');

            // ---- 2) 契約者名（漢字）
            $pdf->SetXY(50, 88);
            $pdf->Write(8, ($form['company_type_prefix']??'') . ($form['company_name']) . ($form['company_type_suffix']??''));
            $pdf->SetXY(125, 210);
            $pdf->Write(8, ($form['company_type_prefix']??'') . ($form['company_name']) . ($form['company_type_suffix']??''));
            $pdf->SetXY(125, 215);
            $pdf->Write(8, $form['corp']['position'] ?? '');
            $pdf->SetXY(150, 215);
            $pdf->Write(8, ($form['rep_last_name']??'') . ($form['rep_first_name']??''));

    //        $pdf->SetXY(50, 80);
    //        $pdf->Write(8, $form['corp']['position'] ?? '');
            $pdf->SetXY(150, 76);
            $pdf->Write(8, ($form['rep_last_kana']??'') . ($form['rep_first_kana']??''));
            $pdf->SetXY(150, 88);
            $pdf->Write(8, ($form['rep_last_name']??'') . ($form['rep_first_name']??''));

            // ---- 3) zip code
            $pdf->SetXY(50, 102);
            $pdf->Write(7, $form['corp']['postal_code'] ?? null);

            $address = ($form['corp']['address1']??'') . ($form['corp']['address2']??'') . ($form['corp']['address3']??'');
            // ---- 3) 住所
            $pdf->SetXY(50, 113);
            $pdf->Write(8, $address);
            // ここから郵送先
            // ---- 3) zip code
            $pdf->SetXY(50, 123);
            $pdf->Write(7, $form['mail']['postal_code'] ?? null);

            $address = ($form['mail']['address1']??'') . ($form['mail']['address2']??'') . ($form['mail']['address3']??'');
            // ---- 3) 住所
            $pdf->SetXY(50, 133);
            $pdf->Write(8, $address);

            // ---- 4) 電話番号
            $tel = $form['mail']['tel'] ?? ''; // 例: 03-1234-5678
            $pdf->SetXY(50, 145);
            $pdf->Write(8, $tel);
            // ---- 4) 電話番号
            $fax = $form['mail']['fax'] ?? ''; // 例: 03-1234-5678
            $pdf->SetXY(130, 145);
            $pdf->Write(8, $fax);

            $pdf->SetXY(50, 157);
            $pdf->Write(8, ($form['mail']['last_name']??'') . ($form['mail']['first_name']??''));
            $pdf->SetXY(130, 159);
            $pdf->Write(8, ($form['mail']['mobile']??'') );
            // Agent部
            $pdf->SetXY(45, 202);
            $pdf->Write(3, $form['agent']['company_name'] ?? '');
            $pdf->SetFontSize(7); 
            $pdf->SetXY(45, 206);
            $pdf->Write(7, $form['agent']['postal_code'] ?? '');
            $pdf->SetFontSize(10); 
            $address = ($form['agent']['address1']??'') . ($form['agent']['address2']??'');
            // ---- 3) 住所
            $pdf->SetXY(45, 208);
            $pdf->Write(10, $address);
            // ---- 3) 住所
            $pdf->SetXY(45, 212);
            $pdf->Write(10, $form['agent']['address3']??'');
            // ---- 4) 電話番号
            $pdf->SetFontSize(8); 
            $tel = ($form['agent']['tel'] ?? '') . '・' . ($form['agent']['fax'] ?? ''); // 例: 03-1234-5678
            $pdf->SetXY(45, 223);
            $pdf->Write(7, $tel);

            $pdf->SetXY(45, 218);
            $pdf->Write(7, ($form['agent']['last_name']??'') . ($form['agent']['first_name']??''));
        }

        // 保存先ファイル名
        $output = 'generated/entry-sheet-' . time() . '.pdf';
        $file_path = storage_path('app/public/' . $output);

        // ディレクトリが存在しない場合は作成
        if (!file_exists(dirname($file_path))) {
            mkdir(dirname($file_path), 0775, true);
        }

        try {
            $pdf->Output($file_path, 'F');

            if (!file_exists($file_path)) {
                throw new \RuntimeException('PDF file not created');
            }

            return \Inertia\Inertia::location(
                route('members.pdf.preview', [
                    'token'  => $token,
                    'pdfUrl' => Storage::url($output),
                ])
            );

        } catch (\Throwable $e) {
            \Log::error('PDF生成エラー', [
                'message' => $e->getMessage(),
                'path'    => $file_path,
            ]);

            // Inertia の validation / error 用
            return back()->withErrors([
                'pdf' => 'PDFの作成に失敗しました',
            ]);
        }
 /*
        try {
            $pdf->Output($file_path, 'F');

            if (!file_exists($file_path)) {
                throw new \RuntimeException('PDF file not created');
            }

            return response()->json([
                'url' => Storage::url($output),
            ]);

        } catch (\Throwable $e) {
            \Log::error('PDF生成エラー', [
                'message' => $e->getMessage(),
                'path'    => $file_path,
            ]);

            return response()->json([
                'message' => 'PDFの作成に失敗しました'
            ], 422);
        }
        /*
       // PDFを直接ファイルに書き込む
        $pdf->Output($file_path, 'F');

        // JSONでURL返却
        return response()->json([
            'url' => Storage::url($output)
        ]); 
        */
    }

    public function pdfPreview(Request $request,$token)
    {
        return Inertia::render('Members/PdfPreview', [
            'token'  => $token,
            'pdfUrl' => $request->query('pdfUrl'),
        ]);
    }

    public function showRejectedMessage($token)
    {
        return Inertia::render('Members/Rejected', [
            'token' => $token,
            'message' => '大変申し訳ありませんが、当団体への加盟はお受け出来かねます。',
        ]);
    }

    public function resend()
    {
        return Inertia::render('Members/Resend', [
            'message' => "このURLは24時間以上経過しており、有効期限が切れています。\n大変申し訳ありませんが、もう一度メール送信から入会申込をやり直してください。\n\nどうぞよろしくお願い致します。",
        ]);
    }

    public function bank()
    {
        return Inertia::render('Members/Bank');
    }
    
    public function pdf()
    {
        $data = [
            'company_furigana'    => 'クーネット',
            'company_name'    => '株式会社クーネット',
            'address_zip'=> '224-0021',
            'address'    => '横浜市都筑区北山田２丁目３番３号',   
            'tel'    => '０４５−５９０−００９０',   
            'bank_name'    => '横浜',
            'branch_name'  => 'センター',
            'account_type' => '当座',
            'account_no'   => '１２３４５６７',
            'account_kana'   => 'クーネット',
            'account_name' => '株式会社クーネット　代表取締役　雲田敏広',
        ];
            // FPDI + TCPDF
    $pdf = new Fpdi();

    // ページ追加
    $pdf->AddPage();

    // 既存PDFテンプレート読み込み
    $templatePath = storage_path('app/templates/aplus.pdf');
    $pageCount = $pdf->setSourceFile($templatePath);
    $tpl = $pdf->importPage(1);
    $pdf->useTemplate($tpl);


    // TCPDF同梱の日本語フォント
    $pdf->SetFont('kozminproregular', '', 12); // もしくは cid0jp

// ---- 1) 契約者名（フリガナ）
$pdf->SetXY(50, 65);
$pdf->Write(8, $data['company_furigana']);

// ---- 2) 契約者名（漢字）
$pdf->SetXY(50, 75);
$pdf->Write(8, $data['company_name']);
        // ---- 3) zip code
        $pdf->SetXY(50, 85);
        $pdf->Write(8, $data['address_zip']);

// ---- 3) 住所
$pdf->SetXY(50, 95);
$pdf->Write(8, $data['address']);

// ---- 4) 電話番号
$pdf->SetXY(140, 100);
$pdf->Write(8, $data['tel']);

// ---- 5) 銀行名
$pdf->SetXY(105, 145);
$pdf->Write(8, $data['bank_name']);

// ---- 6) 支店名
$pdf->SetXY(150, 145);
$pdf->Write(8, $data['branch_name']);

// ---- 7) 預金種目（普通 / 当座 → マル）
if ($data['account_type'] === '普通') {
    $pdf->SetXY(103, 160);
} else {
    $pdf->SetXY(128, 160);
}
$pdf->Write(8, '〇');

// ---- 8) 口座番号（記号）
$pdf->SetXY(150, 162);
$pdf->Write(8, $data['account_no']);

// ---- 9) 口座名義（フリガナ）
$pdf->SetXY(35, 170);
$pdf->Write(8, $data['account_kana']);

// ---- 10) 口座名義（漢字）
$pdf->SetXY(35, 190);
$pdf->Write(8, $data['account_name']);
// 保存先ファイルパス（storage/app/public 内など）
$file_path = storage_path('app/public/generated/bank-info-' . time() . '.pdf');

// 'F' はファイルに直接保存する
$pdf->Output($file_path, 'F');

// ブラウザで表示したい場合は、保存したファイルを読み込む
return response()->file($file_path, [
    'Content-Type' => 'application/pdf'
]);
    }

    // pdf upload＋thumbnail(png)作成関数    
    private function storePdfWithThumbnail(
        ?UploadedFile $file,
        string $baseDir
    ): array {


        if (!$file) {
            return [null, null];
        }

        // PDF 保存（public）
        $pdfRelativePath = $file->store($baseDir, 'public');
        $pdfFullPath = storage_path('app/public/' . $pdfRelativePath);

        // thumbnail 保存先
        $thumbDir = $baseDir . '/thumbnails';
        if (!Storage::disk('public')->exists($thumbDir)) {
            Storage::disk('public')->makeDirectory($thumbDir);
        }

        $thumbnailRelativePath =
            $thumbDir . '/' . pathinfo($pdfRelativePath, PATHINFO_FILENAME) . '.png';
        $thumbnailFullPath = storage_path('app/public/' . $thumbnailRelativePath);

        // thumbnail 生成
        $imagick = new \Imagick();
        $imagick->setResolution(150, 150);
        $imagick->readImage($pdfFullPath . '[0]');
        $imagick->setImageFormat('png');
        $imagick->writeImage($thumbnailFullPath);
        $imagick->clear();
        $imagick->destroy();

        return [$pdfRelativePath, $thumbnailRelativePath];
    }
  
}


