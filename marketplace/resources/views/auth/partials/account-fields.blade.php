<div class="grid gap-5 sm:grid-cols-2">
    <x-field name="first_name" :label="__('Ad')" autocomplete="given-name" required/>
    <x-field name="last_name" :label="__('Soyad')" autocomplete="family-name" required/>
</div>
<x-field name="username" :label="__('Kullanıcı adı')" autocomplete="username" required :hint="__('3–30 karakter; harf, rakam, nokta ve alt çizgi.')"/>
<x-field name="email" type="email" :label="__('E-posta')" autocomplete="email" required/>
<x-country-select :required="$countryRequired ?? false"/>
<div class="grid gap-5 sm:grid-cols-2">
    <x-field name="password" type="password" :label="__('Şifre')" autocomplete="new-password" required :hint="__('En az 8 karakter, harf ve rakam içermeli.')"/>
    <x-field name="password_confirmation" type="password" :label="__('Şifre (tekrar)')" autocomplete="new-password" required/>
</div>
