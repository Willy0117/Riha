<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: 'ipagp', sans-serif; font-size: 12px; color: #222; }
    .header { margin-bottom: 20px; }
    .title { font-size: 20px; font-weight: bold; text-align: center; margin: 20px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #999; padding: 6px 10px; font-size: 12px; }
    th { background: #f0f0f0; }
    .no-border td { border: none; padding: 2px 0; }
    .text-right { text-align: right; }
    .total-row td { font-weight: bold; }
    .notes { margin-top: 20px; font-size: 10px; line-height: 1.6; }
</style>
</head>
<body>

<div class="header">
    〒150-0043<br>
    東京都渋谷区道玄坂1-19-11　寿道玄坂ビル2階<br>
    事務局 JSRR 様
</div>

<table class="no-border">
    <tr><td>会員番号</td><td>{{ $member->code }}</td></tr>
    <tr><td>登録番号</td><td>T3370005003165</td></tr>
    <tr><td>請求番号</td><td>{{ $invoice->invoice_number }}</td></tr>
    <tr><td>請求日</td><td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('Y年n月j日') }}</td></tr>
</table>

<div class="title">請求書</div>

<p>
    件名：{{ $invoice->invoice_name }}<br>
    お支払い期限　{{ \Carbon\Carbon::parse($invoice->due_date)->format('Y年n月j日') }}<br>
    金額　{{ number_format($invoice->total_amount) }} 円
</p>

<p style="text-align:right;">
    一般社団法人 日本腎臓リハビリテーション学会
</p>

<p>下記の通り、御請求申し上げます。</p>

<table>
    <thead>
        <tr>
            <th>品目</th>
            <th>単価</th>
            <th>数量</th>
            <th>請求金額</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>更新料</td>
            <td class="text-right">{{ number_format($invoice->renewal_fee) }} 円</td>
            <td class="text-right">1</td>
            <td class="text-right">{{ number_format($invoice->renewal_fee) }} 円</td>
        </tr>
        <tr>
            <td colspan="3">会員調整額</td>
            <td class="text-right">{{ number_format($invoice->member_adjustment) }} 円</td>
        </tr>
        <tr>
            <td colspan="3">請求調整額</td>
            <td class="text-right">{{ number_format($invoice->invoice_adjustment) }} 円</td>
        </tr>
        <tr class="total-row">
            <td colspan="3">合計</td>
            <td class="text-right">{{ number_format($invoice->total_amount) }} 円</td>
        </tr>
        <tr>
            <td colspan="3">10%対象合計</td>
            <td class="text-right">
                {{ number_format($invoice->total_amount) }} 円
                (内消費税 {{ number_format($invoice->tax_amount) }} 円)
            </td>
        </tr>
    </tbody>
</table>

<div class="notes">
    <strong>備考</strong><br>
    ☆指導士更新料の振込先口座は、【年会費の振込先口座とは異なります】ので、お間違えのないようご注意ください。<br>
    ＜更新料振込先は以下の通りです＞<br>
    【ゆうちょ銀行からお振込みの場合】<br>
    記号・番号：18170-44434721<br>
    口座名義人：日本腎臓リハビリテーション学会腎臓リハビリテーション指導士事務局<br>
    ※必ずご依頼人がわかるよう会員番号・ご氏名をご入力ください。ご入力漏れの場合、こちらで判断はできかねます。<br>
    さらに入力可能であれば、「更新料」とご入力ください。<br>
    <br>
    【他行（ゆうちょ銀行以外）からのお振込みの場合】<br>
    金融機関本（支）店名：ゆうちょ銀行　八一八店（ハチイチハチ）<br>
    普通・当座預金の別：普通（または貯蓄）※預金種目は「普通」「貯蓄」のいずれでも振込可能です。<br>
    口座番号：4443472<br>
    口座名義人：日本腎臓リハビリテーション学会腎臓リハビリテーション指導士事務局<br>
    ※必ずご依頼人がわかるよう会員番号・ご氏名をご入力ください。ご入力漏れの場合、こちらで判断はできかねます。<br>
    さらに入力可能であれば、「更新料」とご入力ください。
</div>

</body>
</html>
