// [今回追加] 事務局の閲覧専用詳細（既存の instructorMembers.edit と同じグループ内に追記してください）
Route::get('instructorMembers/{member}/show', [InstructorMemberController::class, 'show'])
    ->name('admin.instructorMembers.show');
