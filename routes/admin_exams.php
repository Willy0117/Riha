// [今回追加] 新規指導士申請フロー：管理画面側のルート一式
// 既存の admin プレフィックス・admin. 名前空間のグループ内に追記すること
// （Route::prefix('admin')->name('admin.')->middleware(['auth:admin'])->group(function () { ... }) の中）

use App\Http\Controllers\Admin\ExamAssignmentController;
use App\Http\Controllers\Admin\ExamReviewerController;
use App\Http\Controllers\Admin\ExamChiefReviewController;
use App\Http\Controllers\Admin\ExamOfficeController;

// ── 審査員アサイン ──────────────────────────────
Route::prefix('exam-assignment')->name('examAssignment.')->group(function () {
    Route::get('/', [ExamAssignmentController::class, 'index'])->name('index');
    Route::post('/{exam}/assign', [ExamAssignmentController::class, 'assign'])->name('assign');
    Route::post('/bulk-assign', [ExamAssignmentController::class, 'bulkAssign'])->name('bulkAssign');
    Route::post('/auto-assign', [ExamAssignmentController::class, 'autoAssign'])->name('autoAssign');
});

// ── 審査員 ──────────────────────────────────
Route::prefix('exam-reviewer')->name('examReviewer.')->group(function () {
    Route::get('/', [ExamReviewerController::class, 'index'])->name('index');
    Route::get('/{exam}', [ExamReviewerController::class, 'show'])->name('show');
    Route::get('/documents/{id}/view', [ExamReviewerController::class, 'view'])->name('view');
    Route::get('/documents/{id}/thumbnail', [ExamReviewerController::class, 'thumbnail'])->name('thumbnail');
    Route::post('/{exam}/judge', [ExamReviewerController::class, 'judge'])->name('judge');
});

// ── 委員長 ──────────────────────────────────
Route::prefix('exam-chief')->name('examChief.')->group(function () {
    Route::get('/', [ExamChiefReviewController::class, 'index'])->name('index');
    Route::get('/{exam}', [ExamChiefReviewController::class, 'show'])->name('show');
    Route::get('/documents/{id}/view', [ExamChiefReviewController::class, 'view'])->name('view');
    Route::get('/documents/{id}/thumbnail', [ExamChiefReviewController::class, 'thumbnail'])->name('thumbnail');
    Route::post('/{exam}/review', [ExamChiefReviewController::class, 'review'])->name('review');
    Route::post('/{exam}/send-back', [ExamChiefReviewController::class, 'sendBackToReviewer'])->name('sendBackToReviewer');
    Route::post('/bulk-review', [ExamChiefReviewController::class, 'bulkReview'])->name('bulkReview');
});

// ── 事務局 ──────────────────────────────────
Route::prefix('exam-office')->name('examOffice.')->group(function () {
    Route::get('/', [ExamOfficeController::class, 'index'])->name('index');
    Route::get('/{exam}', [ExamOfficeController::class, 'show'])->name('show');
    Route::get('/documents/{id}/view', [ExamOfficeController::class, 'view'])->name('view');
    Route::get('/documents/{id}/thumbnail', [ExamOfficeController::class, 'thumbnail'])->name('thumbnail');
    Route::post('/{exam}/record-exam-result', [ExamOfficeController::class, 'recordExamResult'])->name('recordExamResult');
    Route::post('/{exam}/certify', [ExamOfficeController::class, 'certify'])->name('certify');
});

// ── 指導士回数マスター（既存Controllerの更新のみ。ルート自体が既に無ければ追加） ──
// Route::resource('instructor-cycles', \App\Http\Controllers\Admin\InstructorCycleController::class)
//     ->only(['index', 'store', 'update'])->names('instructorCycles');
