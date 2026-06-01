<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnnualFeeSeeder extends Seeder
{
    public function run(): void
    {
        $cycles = DB::table('instructor_update_cycles')->get();

        foreach ($cycles as $cycle) {
            $startYear = (int) date('Y', strtotime($cycle->start_date));
            $endYear   = (int) date('Y', strtotime($cycle->end_date));

            for ($year = $startYear; $year <= $endYear; $year++) {
                DB::table('annual_fees')->insert([
                    'member_id'      => $cycle->member_id,
                    'fiscal_year'    => $year,
                    'annual_fee'     => 10000,
                    'renewal_fee'    => 0,
                    'payment_amount' => 10000,
                    'payment_date'   => $year . '-02-01',
                    'status'         => 'paid',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }
}