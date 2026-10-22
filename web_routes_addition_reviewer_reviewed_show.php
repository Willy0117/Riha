// [今回追加] 審査確認一覧から開く閲覧専用の詳細画面（既存のreviewer関連ルートと同じグループ内に追記してください）
Route::get('reviewer/reviewed/{cycle}', [ReviewerController::class, 'reviewedShow'])
    ->name('reviewer.reviewed.show');
