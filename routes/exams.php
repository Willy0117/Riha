// [今回修正] Route::resource('exams', ExamController::class) を廃止し、
// 実際に存在するアクションだけを明示的に定義する。
// create/store/edit/update/destroy は ExamController から削除済みのため登録しない。

Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
Route::get('exams/{exam}', [ExamController::class, 'show'])->name('exams.show');

Route::post('exams/{exam}/info', [ExamController::class, 'updateInfo'])
    ->name('exams.updateInfo');

Route::post('exams/{exam}/documents', [ExamController::class, 'uploadDocument'])
    ->name('exams.uploadDocument');

Route::delete('exams/{exam}/documents/{document}', [ExamController::class, 'deleteDocument'])
    ->name('exams.deleteDocument');

Route::put('exams/{exam}/case-reports/{caseReport}', [ExamController::class, 'updateCaseReport'])
    ->name('exams.updateCaseReport');

Route::post('exams/{exam}/submit', [ExamController::class, 'submit'])
    ->name('exams.submit');
