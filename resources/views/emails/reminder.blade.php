@component('mail::message')
# {{ $title ?? 'Home ERP reminder' }}

{{ $message }}

@if (! empty($data['action_url']))
@component('mail::button', ['url' => $data['action_url']])
Open Home ERP
@endcomponent
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
