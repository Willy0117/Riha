<x-mail::message>
{{ $member->name }} 様

いつもお世話になっております。<br>
日本腎臓リハビリテーション学会 指導士事務局です。

指導士更新料のお支払いをお願いいたします。以下のリンクよりお支払い手続きをお願いいたします。

- 請求番号：{{ $invoice->invoice_number }}
- 金額：{{ number_format($invoice->total_amount) }} 円
- お支払い期限：{{ \Carbon\Carbon::parse($invoice->due_date)->format('Y年n月j日') }}

<x-mail::button :url="$hostedInvoiceUrl">
お支払い手続きへ
</x-mail::button>

よろしくお願いいたします。

一般社団法人 日本腎臓リハビリテーション学会
</x-mail::message>
