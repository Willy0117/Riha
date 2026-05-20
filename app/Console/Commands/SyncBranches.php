<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SyncBranches extends Command
{
    protected $signature = 'sync:branches';
    protected $description = 'Fetch branches from BankcodeJP';

    public function handle()
    {
        $this->info('Start fetching branches...');

        $banks = DB::table('banks')
            ->where('bank_code', '>=', '9332') // 途中で取得制限の場合の取り直しスタートコードを入れる
            ->orderBy('bank_code')
            ->get();

        foreach ($banks as $bank) {

            $this->line("Bank: {$bank->bank_code} {$bank->name}");

            $branchCursor = null;

            do {

                sleep(3);

                $params = [
                    'apikey' => config('services.bankcodejp.api_key'),
                    'limit' => 100,
                ];

                if (!empty($branchCursor)) {
                    $params['cursor'] = $branchCursor;
                }

                $response = Http::timeout(60)
                    ->get(
                        "https://apis.bankcode-jp.com/v3/banks/{$bank->bank_code}/branches",
                        $params
                    );

                if (!$response->successful()) {

                    $this->error($response->body());

                    // レート制限時
                    if ($response->status() === 429) {
                        sleep(10);
                    }

                    break;
                }

                $data = $response->json();

                $branches = $data['branches'] ?? [];

                $this->line('  Branches: ' . count($branches));

                foreach ($branches as $branch) {

                    DB::table('branches')->updateOrInsert(
                        [
                            'bank_code' => $bank->bank_code,
                            'branch_code' => $branch['code'],
                        ],
                        [
                            'name' => $branch['name'],
                            'name_kana' => $branch['halfWidthKana'] ?? null,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }

                $branchCursor = $data['nextCursor'] ?? null;

            } while (!empty($branchCursor));
        }

        $this->info('Sync completed!');
    }
}
