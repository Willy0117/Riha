<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InsuranceSimulationController extends Controller
{
    // ★★★ 仮定する係数・料率（実際はDBや設定ファイルから取得することが多い） ★★★
    private const BASE_PREMIUM_PER_MILLION_YEN = 0.05; // 完工高100万円あたりの基本料率

    private const INDUSTRY_RISK_FACTORS = [
        'low' => 0.9,  // 軽微な改修、内装など
        'medium' => 1.1, // 一般建築、土木、電気工事など
        'high' => 1.3, // 大規模高所作業、特殊解体など
    ];

    private const LIABILITY_LIMIT_FACTORS = [
        '100M_100M' => 0.9,   // 対人1億/対物1億
        '300M_100M' => 1.0,   // 対人3億/対物1億
        '500M_300M' => 1.15,  // 対人5億/対物3億
        'unlimited_500M' => 1.3, // 対人無制限/対物5億
    ];

    private const DEDUCTIBLE_DISCOUNTS = [
        'none' => 1.0, // 免責なし
        '100k' => 0.95, // 免責10万円
        '300k' => 0.90, // 免責30万円
    ];

    private const SPECIAL_COVERAGE_ADDONS = [
        'entrusted_property' => 10000, // 受託物賠償特約の追加料金
        // 他の特約があればここに追加
    ];
    // ★★★ 仮定する係数・料率 ここまで ★★★

    public function calculate(Request $request)
    {
        try {
            // 入力値のバリデーション
            $validated = $request->validate([
                'completion_amount' => 'required|numeric|min:0', // 完工高（万円）
                'industry_risk' => 'required|in:low,medium,high', // 業種リスク
                'liability_limit' => 'required|in:100M_100M,300M_100M,500M_300M,unlimited_500M', // 支払限度額
                'deductible' => 'required|in:none,100k,300k', // 免責金額
                'entrusted_property_coverage' => 'boolean', // 受託物賠償特約の有無
            ]);

            $completionAmount = $validated['completion_amount'];
            $industryRisk = $validated['industry_risk'];
            $liabilityLimit = $validated['liability_limit'];
            $deductible = $validated['deductible'];
            $entrustedPropertyCoverage = $validated['entrusted_property_coverage'] ?? false;

            // 基本保険料の計算（完工高100万円あたりの料率）
            $basePremium = $completionAmount * self::BASE_PREMIUM_PER_MILLION_YEN;

            // 業種リスクによる調整
            $adjustedPremium = $basePremium * self::INDUSTRY_RISK_FACTORS[$industryRisk];

            // 支払限度額による調整
            $adjustedPremium = $adjustedPremium * self::LIABILITY_LIMIT_FACTORS[$liabilityLimit];

            // 免責金額による割引
            $adjustedPremium = $adjustedPremium * self::DEDUCTIBLE_DISCOUNTS[$deductible];

            // 特約の追加料金
            if ($entrustedPropertyCoverage) {
                $adjustedPremium += self::SPECIAL_COVERAGE_ADDONS['entrusted_property'];
            }

            // 小数点以下を四捨五入して整数にする
            $finalPremium = round($adjustedPremium);

            return response()->json([
                'premium' => (int) $finalPremium, // 整数として返す
                'message' => 'Insurance premium calculated successfully.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation Failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
