{{-- E-mail copy of a 通知 (Modules\Core\Application\Notifications\SystemNotificationMail). --}}
<x-mail::message>
{{ $name }} さん

**{{ $title }}**

@if (filled($body))
{{ $body }}
@endif

@if (filled($url))
<x-mail::button :url="$url">
レコードを開く
</x-mail::button>

<span class="break-all">[{{ $url }}]({{ $url }})</span>
@endif
</x-mail::message>
