<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $sortBy = in_array($request->sort_by, $sortableColumns) ? $request->sort_by : 'reviewer_assigned_at';
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

        return inertia('Admin/Reviewer/Index', [
            'cycles' => $cycles,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
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

        $uploads = $uploads->map(function (PdfUpload $upload) {
            return array_merge($upload->toArray(), [
                'credit_conference_name' => $upload->creditConference?->name ?? '',
                'role_name' => $upload->creditRole?->creditRole?->name ?? '',
                'thumbnail_url' => $this->thumbnailUrl($upload->thumbnail_path),
            ]);
        });

        return inertia('Admin/Reviewer/Show', [
            'cycle' => $cycle,
            'member' => $cycle->member,
            'uploads' => $uploads,
        ]);
    }

    // PDF本体プレビュー（署名URLへリダイレクト）
    public function view(Request $request, $id)
    {
        $upload = PdfUpload::findOrFail($id);
        $this->authorizeForUpload($upload);

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
        $this->authorizeForUpload($upload);

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

        $cycle->reviewer_judgment = $request->judgment;
        $cycle->reviewer_judged_at = now();
        $cycle->save();

        return back()->with('success', $request->judgment === 'pass' ? '合格と判定しました。' : '不合格と判定しました。');
    }

    // その書類が、ログイン中審査員にアサインされた申請に属するかチェック
    private function authorizeForUpload(PdfUpload $upload): void
    {
        $adminId = Auth::guard('admin')->id();

        $cycle = InstructorUpdateCycle::where('member_id', $upload->member_id)
            ->where('reviewer_admin_id', $adminId)
            ->where('status', 'pending')
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
