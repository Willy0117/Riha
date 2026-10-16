<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 指導士「新規」資格認定試験の申請テーブル。
     *
     * 更新フロー（instructor_update_cycles）とは別テーブルとして新設する。
     * 更新フローと違い、期間（start_date/end_date/renewal_*）の概念は持たない。
     * 1申請 = 1レコード（受験のたびに新しいレコードを作る想定。再受験時は既存レコードを
     * submitted に戻して審査関連フィールドをリセットする運用とし、履歴として使い回さない）。
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            // ── 様式1：受験申込書（申請者本人の情報） ──
            // route: 'A' = 様式2（学会での主演者・座長経験）を提出できる
            //        'B' = 様式2を提出できず、様式4・5（自験例報告書）を提出する
            $table->enum('route', ['A', 'B']);

            $table->string('last_name');
            $table->string('first_name');
            $table->string('last_name_kana')->nullable();
            $table->string('first_name_kana')->nullable();
            $table->date('birthdate')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            // 申請者職種（医師・看護師・理学療法士 等、募集要項の選択肢を自由記述で保持）
            $table->string('occupation')->nullable();
            $table->string('workplace')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('workplace_address')->nullable();
            $table->string('email')->nullable();
            $table->string('tel')->nullable();
            // 申請時点の会員番号・入会年月日のスナップショット
            // （member側が後から変わっても、申請当時の値を審査記録として残すため）
            $table->string('member_code_snapshot')->nullable();
            $table->date('member_joined_at_snapshot')->nullable();

            // ── 審査ステータス（更新フローの instructor_update_cycles を踏襲） ──
            // draft:     入力中（未提出）
            // submitted: 提出済み・審査員アサイン待ち
            // pending:   審査員アサイン済み・審査中
            // approved:  委員長が最終承認
            // reject:    委員長が不合格判定
            // certified: 事務局が合格確定処理（新規認定期間の設定）を実行済み
            $table->enum('status', ['draft', 'submitted', 'pending', 'approved', 'reject', 'certified'])
                ->default('draft');

            $table->foreignId('reviewer_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewer_assigned_at')->nullable();

            $table->enum('reviewer_judgment', ['unreviewed', 'pass', 'fail', 're_review'])
                ->default('unreviewed');
            $table->timestamp('reviewer_judged_at')->nullable();
            // 審査員→委員長への任意メッセージ
            $table->text('reviewer_response_message')->nullable();

            // 委員長→審査員の差戻し理由
            $table->text('chief_feedback')->nullable();
            // 差戻し時に指摘した書類ID（application_documents.id / case_reports.id を混在させず、
            // {type, id} のペア配列として持たせる。例: [{"kind":"document","id":12},{"kind":"case_report","id":34}]）
            $table->json('chief_flagged_items')->nullable();

            // 不合格理由（申請者向けに表示）
            $table->text('reason')->nullable();

            // 先生が「提出する」を押した日時（更新フローの cycle.updated_at 相当の基準時刻）
            $table->timestamp('applied_at')->nullable();
            // 事務局が新規認定期間を設定した日時（＝合格確定・指導士として発足した日時）
            $table->timestamp('certified_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'reviewer_admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
