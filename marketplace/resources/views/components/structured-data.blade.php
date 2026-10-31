@props(['data'])
{{-- JSON-LD. JSON_HEX_TAG ile "<" ve ">" kaçırılır; kullanıcı içeriği </script> ile etiketi kapatamaz. --}}
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
