<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SyncBankcode extends Command
{
    protected $signature = 'sync:bankcode';
    protected $description = 'Fetch banks and branches from BankcodeJP';

    public function handle()
    {
        $this->info('Start fetching banks...');

        $cursor = null;

        do {

            // banks取得
            $params = [
                'apikey' => config('services.bankcodejp.api_key'),
                'limit' => 100,
            ];

            if (!empty($cursor)) {
                $params['cursor'] = $cursor;
            }

            sleep(3);

            $response = Http::timeout(60)
                ->get('https://apis.bankcode-jp.com/v3/banks', $params);

            if (!$response->successful()) {
                $this->error($response->body());
                return;
            }

            $data = $response->json();

            $banks = $data['banks'] ?? [];

            $this->info('Banks count: ' . count($banks));

            foreach ($banks as $bank) {

                // banks保存
                DB::table('banks')->updateOrInsert(
                    [
                        'bank_code' => $bank['code'],
                    ],
                    [
                        'name' => $bank['name'],
                        'name_kana' => $bank['halfWidthKana'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $this->line("Bank: {$bank['code']} {$bank['name']}");
                // branches取得
                $branchCursor = null;

            }

            $cursor = $data['nextCursor'] ?? null;

        } while (!empty($cursor));

        $this->info('Sync completed!');
    }
}
