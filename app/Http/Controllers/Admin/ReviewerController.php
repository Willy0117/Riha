<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructorCycle;
use App\Models\InstructorUpdateCycle;
use App\Models\PdfUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ReviewerController extends Controller
{
    // 合格判定の基準（単位数・学術集会参加回数）
    private const REQUIRED_POINTS = 50;
    private const REQUIRED_CONFERENCE_COUNT = 2;

    // 自分（ログイン中の審査員）にアサインされた申請の一覧
    public function index(Request $request)
    {
        $adminId = Auth::guard('admin')->id();

        // 申請日（updated_at）・アサイン日（reviewer_assigned_at）でソート可能にする
        $sortableColumns = ['updated_at', 'reviewer_assigned_at'];
        $sortBy = in_array($request->sort_by, $sortableColumns) ? $request->sort_by : 'updated_at';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        $cycles = InstructorUpdateCycle::with(['member'])
            ->where('reviewer_admin_id', $adminId)
            ->where('status', 'pending')
            ->whereIn('reviewer_judgment', ['unreviewed', 're_review'])
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->per_page ?? 20);

        $isConferenceParticipation = function ($u) {
            return $u->creditCategory?->name === '学術集会'
                && $u->creditConference?->name === '日本腎臓リハビリテーション学会'
                && $u->creditRole?->creditRole?->name === '参加';
        };

        // 各申請ごとの書類審査状況・承認済み単位数・承認済み参加回数を集計
        $cycles->getCollection()->transform(function ($cycle) use ($isConferenceParticipation) {
            $uploads = PdfUpload::with(['creditCategory', 'creditConference', 'creditRole.creditRole'])
                ->where('member_id', $cycle->member_id)
                ->whereDate('issued_date', '>=', $cycle->start_date)
                ->whereDate('issued_date', '<=', $cycle->end_date)
                ->get();

            $totalCount = $uploads->count();
            $reviewedCount = $uploads->whereIn('status', ['approved', 'rejected'])->count();
            $approvedUploads = $uploads->where('status', 'approved');

            $cycle->document_review_status = "{$reviewedCount} / {$totalCount}";
            $cycle->approved_points_total = $approvedUploads->sum('points');
            $cycle->approved_conference_count = $approvedUploads->filter($isConferenceParticipation)->count();

            return $cycle;
        });

        $periods = \App\Support\ApplicationPeriod::list();
        \App\Support\ApplicationPeriod::annotate($cycles->getCollection(), $periods);

        return inertia('Admin/Reviewer/Index', [
            'cycles' => $cycles,
            'periods' => $periods,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    /**
     * [今回追加] 自分（ログイン中の審査員）が既に判定済みの申請の一覧（審査済画面）。
     * 未審査（unreviewed）・差し戻し（re_review）以外、つまり pass/fail の判定済みを対象にする。
     * 委員長の承認後（status が approved/reject）も一覧に残す。
     * 検索：氏名、第N回（認定期間）、委員長承認日の from-to。
     * 再判定はさせない閲覧用の一覧のため、showの操作ボタンはVue側で非表示にする。
     */
    public function reviewed(Request $request)
    {
        $adminId = Auth::guard('admin')->id();

        $sortableColumns = ['updated_at', 'reviewer_assigned_at', 'reviewer_judged_at'];
        $sortBy = in_array($request->sort_by, $sortableColumns) ? $request->sort_by : 'reviewer_judged_at';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        $name = trim((string) $request->name);
        $roundId = $request->round_id;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $query = InstructorUpdateCycle::with(['member'])
            ->where('reviewer_admin_id', $adminId)
            ->whereIn('reviewer_judgment', ['pass', 'fail']);

        // 氏名（姓名・かな。空白は無視して部分一致）
        if ($name !== '') {
            $keyword = str_replace([' ', '　'], '', $name);
            $query->whereHas('member', function ($q) use ($keyword) {
                $q->whereRaw("REPLACE(CONCAT(IFNULL(last_name,''), IFNULL(first_name,'')), ' ', '') LIKE ?", ["%{$keyword}%"])
                  ->orWhereRaw("REPLACE(CONCAT(IFNULL(last_name_kana,''), IFNULL(first_name_kana,'')), ' ', '') LIKE ?", ["%{$keyword}%"]);
            });
        }

        // 第N回：その回の認定期間（instructor_cycles.start_date）に紐づく申請に絞る
        if ($roundId) {
            $round = InstructorCycle::find($roundId);
            if ($round) {
                $query->whereDate('start_date', $round->start_date);
            }
        }

        // 委員長承認日：承認・却下済みの申請の updated_at（承認時に承認日で上書きされる）
        if ($dateFrom || $dateTo) {
            $query->whereIn('status', ['approved', 'reject']);
            if ($dateFrom) {
                $query->whereDate('updated_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('updated_at', '<=', $dateTo);
            }
        }

        $cycles = $query->orderBy($sortBy, $sortDir)
            ->paginate($request->per_page ?? 20);

        $isConferenceParticipation = function ($u) {
            return $u->creditCategory?->name === '学術集会'
                && $u->creditConference?->name === '日本腎臓リハビリテーション学会'
                && $u->creditRole?->creditRole?->name === '参加';
        };

        $cycles->getCollection()->transform(function ($cycle) use ($isConferenceParticipation) {
            $uploads = PdfUpload::with(['creditCategory', 'creditConference', 'creditRole.creditRole'])
                ->where('member_id', $cycle->member_id)
                ->whereDate('issued_date', '>=', $cycle->start_date)
                ->whereDate('issued_date', '<=', $cycle->end_date)
                ->get();

            $totalCount = $uploads->count();
            $reviewedCount = $uploads->whereIn('status', ['approved', 'rejected'])->count();
            $approvedUploads = $uploads->where('status', 'approved');

            $cycle->document_review_status = "{$reviewedCount} / {$totalCount}";
            $cycle->approved_points_total = $approvedUploads->sum('points');
            $cycle->approved_conference_count = $approvedUploads->filter($isConferenceParticipation)->count();

            return $cycle;
        });

        return inertia('Admin/Reviewer/Reviewed', [
            'cycles' => $cycles,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            // 検索用：第N回（指導士認定期間）の一覧
            'rounds' => InstructorCycle::orderBy('exam_round')
                ->get(['id', 'exam_round', 'start_date', 'end_date']),
            'filters' => [
                'name' => $name,
                'round_id' => $roundId ? (int) $roundId : null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    // アサインされた1申請の書類一覧（承認/差し戻し）
    public function show(Request $request, InstructorUpdateCycle $cycle)
    {
        $adminId = Auth::guard('admin')->id();

        abort_unless($cycle->reviewer_admin_id === $adminId, 403, 'この申請の担当審査員ではありません。');

        $cycle->load('member');

        $uploads = PdfUpload::with(['creditCategory', 'creditConference', 'creditRole.creditRole'])
            ->where('member_id', $cycle->member_id)
            // issued_date に時刻が付いていても end_date の当日いっぱいを含めるよう、日単位で比較する
            ->whereDate('issued_date', '>=', $cycle->start_date)
            ->whereDate('issued_date', '<=', $cycle->end_date)
            ->latest()
            ->get();

        $approvedUploads = $uploads->where('status', 'approved');
        // 却下済み以外（承認済み＋未審査）＝ 残り全部承認された場合に到達しうる最大値の算出対象
        $possibleUploads = $uploads->where('status', '!=', 'rejected');

        $isConferenceParticipation = function ($u) {
            return $u->creditCategory?->name === '学術集会'
                && $u->creditConference?->name === '日本腎臓リハビリテーション学会'
                && $u->creditRole?->creditRole?->name === '参加';
        };

        // 合計単位（承認済みのみ）
        $cycle->total_points = $approvedUploads->sum('points');

        // 学術集会参加回数（承認済み・区分「学術集会」・学会「日本腎臓リハビリテーション学会」・種別「参加」のみ）
        $cycle->conference_count = $approvedUploads->filter($isConferenceParticipation)->count();

        // 残り全て承認された場合に到達しうる最大値（合格ボタン／不合格ボタンの活性判定に使用）
        $maxPossiblePoints = $possibleUploads->sum('points');
        $maxPossibleConferenceCount = $possibleUploads->filter($isConferenceParticipation)->count();

        $cycle->required_points = self::REQUIRED_POINTS;
        $cycle->required_conference_count = self::REQUIRED_CONFERENCE_COUNT;

        // 現時点で基準を満たしている → 合格ボタンを押せる
        $cycle->can_pass = $cycle->total_points >= self::REQUIRED_POINTS
            && $cycle->conference_count >= self::REQUIRED_CONFERENCE_COUNT;

        // 残り全部承認しても、単位・参加回数のどちらかが基準に届かない → 不合格ボタンを押せる
        $cycle->can_fail = $maxPossiblePoints < self::REQUIRED_POINTS
            || $maxPossibleConferenceCount < self::REQUIRED_CONFERENCE_COUNT;

        $cycle->reviewer_judgment = $cycle->reviewer_judgment ?? 'unreviewed';

        $flaggedIds = $cycle->chief_flagged_upload_ids ?? [];

        $uploads = $uploads->map(function (PdfUpload $upload) use ($flaggedIds) {
            return array_merge($upload->toArray(), [
                'credit_conference_name' => $upload->creditConference?->name ?? '',
                'role_name' => $upload->creditRole?->creditRole?->name ?? '',
                'thumbnail_url' => $this->thumbnailUrl($upload->thumbnail_path),
                // 委員長が差し戻し時に指摘した書類かどうか
                'chief_flagged' => in_array($upload->id, $flaggedIds),
                // プレビューダイアログで img / iframe を出し分けるためのフラグ
                'is_image' => in_array(strtolower(pathinfo($upload->file_path ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']),
                // [今回追加] 学術講習会インポートで作成された書類（添付資料なし）かどうか
                'is_imported' => empty($upload->file_path),
            ]);
        });

        return inertia('Admin/Reviewer/Show', [
            'cycle' => $cycle,
            'member' => $cycle->member,
            'uploads' => $uploads,
        ]);
    }

    /**
     * [今回追加] 審査確認一覧（審査済画面）から開く閲覧専用の詳細画面（Show）。
     * 審査画面（show）とは別画面で、編集・判定の操作は一切持たない。
     * 委員長の承認後（status が approved/reject）でも閲覧できる。
     */
    public function reviewedShow(Request $request, InstructorUpdateCycle $cycle)
    {
        $adminId = Auth::guard('admin')->id();

        abort_unless($cycle->reviewer_admin_id === $adminId, 403, 'この申請の担当審査員ではありません。');

        $cycle->load('member');

        $uploads = PdfUpload::with(['creditCategory', 'creditConference', 'creditRole.creditRole'])
            ->where('member_id', $cycle->member_id)
            ->whereDate('issued_date', '>=', $cycle->start_date)
            ->whereDate('issued_date', '<=', $cycle->end_date)
            ->latest()
            ->get();

        $approvedUploads = $uploads->where('status', 'approved');

        $cycle->total_points = $approvedUploads->sum('points');
        $cycle->conference_count = $approvedUploads->filter(function ($u) {
            return $u->creditCategory?->name === '学術集会'
                && $u->creditConference?->name === '日本腎臓リハビリテーション学会'
                && $u->creditRole?->creditRole?->name === '参加';
        })->count();

        $uploads = $uploads->map(function (PdfUpload $upload) {
            return array_merge($upload->toArray(), [
                'credit_conference_name' => $upload->creditConference?->name ?? '',
                'category_name' => $upload->creditCategory?->name ?? '',
                'role_name' => $upload->creditRole?->creditRole?->name ?? '',
                'is_image' => in_array(strtolower(pathinfo($upload->file_path ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']),
                'is_imported' => empty($upload->file_path),
            ]);
        });

        return inertia('Admin/Reviewer/ReviewedShow', [
            'cycle' => $cycle,
            'member' => $cycle->member,
            'uploads' => $uploads,
        ]);
    }

    // PDF本体プレビュー（署名URLへリダイレクト）
    public function view(Request $request, $id)
    {
        $upload = PdfUpload::findOrFail($id);
        $this->authorizeForUpload($upload, false);

        if (!$upload->file_path || !$this->disk()->exists($upload->file_path)) {
            abort(404);
        }

        return redirect(
            $this->disk()->temporaryUrl($upload->file_path, now()->addMinutes(10))
        );
    }

    // サムネイル（署名URLへリダイレクト）
    public function thumbnail(Request $request, $id)
    {
        $upload = PdfUpload::findOrFail($id);
        $this->authorizeForUpload($upload, false);

        if (!$upload->thumbnail_path || !$this->disk()->exists($upload->thumbnail_path)) {
            abort(404);
        }

        return redirect(
            $this->disk()->temporaryUrl($upload->thumbnail_path, now()->addMinutes(10))
        );
    }

    // 個別書類の承認
    public function approve(Request $request, $id)
    {
        $upload = PdfUpload::findOrFail($id);
        $this->authorizeForUpload($upload);

        $upload->status = 'approved';
        $upload->rejection_message = null;
        $upload->save();

        return back()->with('success', 'PDF approved.');
    }

    // 個別書類の差し戻し
    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_message' => 'required|string|max:1000',
        ]);

        $upload = PdfUpload::findOrFail($id);
        $this->authorizeForUpload($upload);

        $upload->status = 'rejected';
        $upload->rejection_message = $request->rejection_message;
        $upload->save();

        return back()->with('success', 'PDF rejected.');
    }

    // 審査員による合格/不合格の判定を確定する
    public function judge(Request $request, InstructorUpdateCycle $cycle)
    {
        $adminId = Auth::guard('admin')->id();

        abort_unless($cycle->reviewer_admin_id === $adminId, 403, 'この申請の担当審査員ではありません。');

        $request->validate([
            'judgment' => 'required|in:pass,fail',
            // [今回変更] 審査員から更新者へのメッセージ（却下理由）入力欄は廃止したため、
            // 常に任意（不合格時も空文字で送られてくる想定）
            'message' => 'nullable|string|max:1000',
        ]);

        // サーバー側でも基準を再計算し、クライアント側の表示と実データがズレていないか確認する
        $uploads = PdfUpload::with(['creditCategory', 'creditConference', 'creditRole.creditRole'])
            ->where('member_id', $cycle->member_id)
            ->whereDate('issued_date', '>=', $cycle->start_date)
            ->whereDate('issued_date', '<=', $cycle->end_date)
            ->get();

        $isConferenceParticipation = function ($u) {
            return $u->creditCategory?->name === '学術集会'
                && $u->creditConference?->name === '日本腎臓リハビリテーション学会'
                && $u->creditRole?->creditRole?->name === '参加';
        };

        $approvedUploads = $uploads->where('status', 'approved');
        $possibleUploads = $uploads->where('status', '!=', 'rejected');

        $totalPoints = $approvedUploads->sum('points');
        $conferenceCount = $approvedUploads->filter($isConferenceParticipation)->count();
        $maxPossiblePoints = $possibleUploads->sum('points');
        $maxPossibleConferenceCount = $possibleUploads->filter($isConferenceParticipation)->count();

        if ($request->judgment === 'pass') {
            $canPass = $totalPoints >= self::REQUIRED_POINTS
                && $conferenceCount >= self::REQUIRED_CONFERENCE_COUNT;

            abort_unless($canPass, 422, '現時点では合格の基準（単位数・学術集会参加回数）を満たしていません。');
        }

        if ($request->judgment === 'fail') {
            $canFail = $maxPossiblePoints < self::REQUIRED_POINTS
                || $maxPossibleConferenceCount < self::REQUIRED_CONFERENCE_COUNT;

            abort_unless($canFail, 422, '残りの書類が全て承認された場合、基準を満たす可能性があるため不合格にはできません。');
        }

        // [今回修正] statusの変更は審査員には行わせない（委員長の承認操作で確定する）。
        // 判定結果（合格・不合格）に関わらず、委員長宛メッセージは常に reviewer_response_message に保存する。
        $cycle->reviewer_judgment = $request->judgment;
        $cycle->reviewer_judged_at = now();
        $cycle->reviewer_response_message = $request->message ?: null;
        // [今回追加] 審査員の操作では updated_at を更新しない（委員長の最終承認時のみ更新される仕様）
        $cycle->timestamps = false;
        $cycle->save();

        return redirect()->route('admin.reviewer.index')
            ->with('success', $request->judgment === 'pass' ? '合格と判定しました。' : '不合格と判定しました。');
    }

    // その書類が、ログイン中審査員にアサインされた申請に属するかチェック
    private function authorizeForUpload(PdfUpload $upload, bool $requirePending = true): void
    {
        $adminId = Auth::guard('admin')->id();

        $cycle = InstructorUpdateCycle::where('member_id', $upload->member_id)
            ->where('reviewer_admin_id', $adminId)
            // 審査操作（承認/差し戻し）は審査中のみ。閲覧（view/thumbnail）は状態を問わない
            ->when($requirePending, fn ($q) => $q->where('status', 'pending'))
            ->whereDate('start_date', '<=', $upload->issued_date)
            ->whereDate('end_date', '>=', $upload->issued_date)
            ->first();

        abort_unless($cycle, 403, 'この書類の担当審査員ではありません。');
    }

    // ファイルを保存しているディスク（S3運用時は 'local' に固定せず、必ずこの共通メソッド経由で取得する）
    private function disk()
    {
        return Storage::disk(config('filesystems.default'));
    }

    // 一覧・詳細で使うサムネイルURL（S3の場合は有効期限付きの署名URLになる）
    private function thumbnailUrl(?string $thumbnailPath): ?string
    {
        if (!$thumbnailPath) return null;

        return $this->disk()->temporaryUrl($thumbnailPath, now()->addMinutes(30));
    }
}
