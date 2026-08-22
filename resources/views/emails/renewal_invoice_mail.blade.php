<x-mail::message>
{{ $member->name }} 様

いつもお世話になっております。<br>
日本腎臓リハビリテーション学会 指導士事務局です。

指導士更新料の請求書を送付いたします。添付のPDFをご確認の上、期日までにお振込みをお願いいたします。

- 請求番号：{{ $invoice->invoice_number }}
- 金額：{{ number_format($invoice->total_amount) }} 円
- お支払い期限：{{ \Carbon\Carbon::parse($invoice->due_date)->format('Y年n月j日') }}

振込先の詳細は、添付の請求書PDFをご確認ください。

よろしくお願いいたします。

一般社団法人 日本腎臓リハビリテーション学会
</x-mail::message>
