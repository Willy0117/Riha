<x-mail::message>
{{ $admin->name }} 様

いつもお世話になっております。指導士資格更新システムです。

{{ $schedule->period_name }}の「{{ $phaseName }}期間」が、まもなく開始します。

- 対象期間：{{ $schedule->period_name }}
- {{ $phaseName }}期間：{{ $startDate->format('Y年n月j日') }} 〜

開始までにご準備をお願いいたします。

よろしくお願いいたします。
</x-mail::message>
