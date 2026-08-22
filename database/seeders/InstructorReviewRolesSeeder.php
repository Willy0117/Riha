<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class InstructorReviewRolesSeeder extends Seeder
{
    /**
     * 指導士更新審査まわりの4ロールを作成する。
     * tenant_id は既存ロール（id=8「指導士更新」など）に合わせて 1 を想定。
     * 環境によって tenant_id が異なる場合は、実行前に書き換えること。
     */
    public function run(): void
    {
        $tenantId = 1;
        $guard = 'admin';

        $roles = ['審査員', '審査委員長', 'サブリーダー', '事務局員'];

        foreach ($roles as $name) {
            Role::firstOrCreate(
                [
                    'name' => $name,
                    'guard_name' => $guard,
                ],
                [
                    'tenant_id' => $tenantId,
                ]
            );
        }
    }
}
