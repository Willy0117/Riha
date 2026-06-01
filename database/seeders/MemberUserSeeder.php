<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberUserSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['code' => '122350', 'name' => '阿部 慎太郎', 'start' => 2025, 'end' => 2030],
            ['code' => '117731', 'name' => '伊藤 誠',     'start' => 2025, 'end' => 2030],
            ['code' => '123105', 'name' => '井上 結衣',   'start' => 2025, 'end' => 2030],
            ['code' => '110926', 'name' => '加藤 真由美', 'start' => 2019, 'end' => 2027],
            ['code' => '116143', 'name' => '吉田 哲也',   'start' => 2019, 'end' => 2026],
            ['code' => '120429', 'name' => '橋本 達也',   'start' => 2020, 'end' => 2026],
            ['code' => '119150', 'name' => '高橋 浩二',   'start' => 2020, 'end' => 2027],
            ['code' => '114038', 'name' => '佐々木 淳',   'start' => 2020, 'end' => 2026],
            ['code' => '114829', 'name' => '佐藤 健一',   'start' => 2020, 'end' => 2026],
            ['code' => '125519', 'name' => '斎藤 翼',     'start' => 2020, 'end' => 2026],
            ['code' => '121892', 'name' => '山下 俊介',   'start' => 2022, 'end' => 2027],
            ['code' => '118890', 'name' => '山口 智子',   'start' => 2022, 'end' => 2027],
            ['code' => '128036', 'name' => '山崎 大輔',   'start' => 2022, 'end' => 2029],
            ['code' => '112579', 'name' => '山田 舞',     'start' => 2022, 'end' => 2027],
            ['code' => '115984', 'name' => '山本 拓也',   'start' => 2022, 'end' => 2027],
            ['code' => '129314', 'name' => '小川 優子',   'start' => 2023, 'end' => 2028],
            ['code' => '118412', 'name' => '小林 亮太',   'start' => 2023, 'end' => 2028],
            ['code' => '115214', 'name' => '松本 剛',     'start' => 2023, 'end' => 2028],
            ['code' => '124971', 'name' => '森 雅代',     'start' => 2023, 'end' => 2028],
            ['code' => '121247', 'name' => '清水 奈々',   'start' => 2023, 'end' => 2029],
            ['code' => '123758', 'name' => '石井 貴大',   'start' => 2024, 'end' => 2029],
            ['code' => '126605', 'name' => '石川 里帆',   'start' => 2024, 'end' => 2029],
            ['code' => '127184', 'name' => '池田 彩香',   'start' => 2024, 'end' => 2029],
            ['code' => '113067', 'name' => '中村 恵',     'start' => 2024, 'end' => 2029],
            ['code' => '125061', 'name' => '長谷川 葵',   'start' => 2024, 'end' => 2029],
            ['code' => '112648', 'name' => '田中 裕子',   'start' => 2025, 'end' => 2030],
            ['code' => '111205', 'name' => '渡辺 陽子',   'start' => 2025, 'end' => 2030],
            ['code' => '129482', 'name' => '木村 健太',   'start' => 2025, 'end' => 2030],
            ['code' => '120763', 'name' => '林 佳代子',   'start' => 2025, 'end' => 2030],
            ['code' => '110372', 'name' => '鈴木 美咲',   'start' => 2025, 'end' => 2030],
            ['code' => '125001', 'name' => '渡辺 麻衣',   'start' => 2020, 'end' => 2028],
            ['code' => '115002', 'name' => '上村 あゆみ', 'start' => 2020, 'end' => 2026],
            ['code' => '125002', 'name' => '竹内 智彦',   'start' => 2023, 'end' => 2028],
            ['code' => '115003', 'name' => '林 柏均',     'start' => 2024, 'end' => 2029],
        ];

        foreach ($data as $item) {
            $examRound = $item['start'] - 2018;

            // member作成
            $memberId = DB::table('members')->insertGetId([
                'name'             => $item['name'],
                'name_kana'        => $item['name'],
                'instructor_code'  => $item['code'],
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // user作成
            DB::table('users')->insert([
                'tenant_id'  => 1,
                'member_id'  => $memberId,
                'username'   => $item['code'],
                'name'       => $item['name'],
                'email'      => 'member-' . $item['code'] . '@example.com',
                'password'   => Hash::make('1234'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // instructor_update_cycles作成
            DB::table('instructor_update_cycles')->insert([
                'member_id'        => $memberId,
                'exam_round'       => $examRound,
                'instructor_no'    => $item['code'],
                'start_date'       => $item['start'] . '-01-01',
                'end_date'         => $item['end'] . '-12-31',
                'total_points'     => 0,
                'conference_count' => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }
}
