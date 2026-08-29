<x-mail::message>
{{ $admin->name }} 様

いつもお世話になっております。指導士資格更新システムです。

@if ($pendingCount > 0)
現在の要審査件数は **{{ $pendingCount }}件** です。

お手数ですが、システムにログインしてご審査をお願いいたします。
@else
現在、要審査の申請は **0件** です。
@endif

よろしくお願いいたします。
</x-mail::message>
