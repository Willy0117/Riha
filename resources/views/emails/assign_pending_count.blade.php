<x-mail::message>
{{ $admin->name }} 様

いつもお世話になっております。指導士資格更新システムです。

@if ($pendingCount > 0)
現在の未アサイン件数は **{{ $pendingCount }}件** です。

お手数ですが、システムにログインして審査員への割り振りをお願いいたします。
@else
現在、未アサインの申請は **0件** です。
@endif

よろしくお願いいたします。
</x-mail::message>
