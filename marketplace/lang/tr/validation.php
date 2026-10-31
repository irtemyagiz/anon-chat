<?php

/*
 | Türkçe doğrulama mesajları. Alan adları form isteklerindeki attributes()
 | metotlarından veya aşağıdaki "attributes" dizisinden gelir.
 */

return [
    'accepted' => ':Attribute kabul edilmelidir.',
    'alpha' => ':Attribute yalnızca harf içerebilir.',
    'array' => ':Attribute bir liste olmalıdır.',
    'boolean' => ':Attribute alanı doğru veya yanlış olmalıdır.',
    'confirmed' => ':Attribute tekrarı eşleşmiyor.',
    'date' => ':Attribute geçerli bir tarih olmalıdır.',
    'email' => ':Attribute geçerli bir e-posta adresi olmalıdır.',
    'exists' => 'Seçilen :attribute geçersiz.',
    'file' => ':Attribute bir dosya olmalıdır.',
    'image' => ':Attribute bir görsel olmalıdır.',
    'in' => 'Seçilen :attribute geçersiz.',
    'integer' => ':Attribute tam sayı olmalıdır.',
    'max' => [
        'numeric' => ':Attribute en fazla :max olabilir.',
        'file' => ':Attribute en fazla :max kilobayt olabilir.',
        'string' => ':Attribute en fazla :max karakter olabilir.',
        'array' => ':Attribute en fazla :max öğe içerebilir.',
    ],
    'mimes' => ':Attribute şu türlerden biri olmalıdır: :values.',
    'min' => [
        'numeric' => ':Attribute en az :min olmalıdır.',
        'file' => ':Attribute en az :min kilobayt olmalıdır.',
        'string' => ':Attribute en az :min karakter olmalıdır.',
        'array' => ':Attribute en az :min öğe içermelidir.',
    ],
    'numeric' => ':Attribute sayı olmalıdır.',
    'password' => [
        'letters' => ':Attribute en az bir harf içermelidir.',
        'mixed' => ':Attribute en az bir büyük ve bir küçük harf içermelidir.',
        'numbers' => ':Attribute en az bir rakam içermelidir.',
        'symbols' => ':Attribute en az bir sembol içermelidir.',
        'uncompromised' => 'Bu :attribute bir veri sızıntısında yer almış. Lütfen farklı bir :attribute seçin.',
    ],
    'regex' => ':Attribute biçimi geçersiz.',
    'required' => ':Attribute alanı zorunludur.',
    'size' => [
        'numeric' => ':Attribute :size olmalıdır.',
        'string' => ':Attribute :size karakter olmalıdır.',
    ],
    'string' => ':Attribute metin olmalıdır.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'url' => ':Attribute geçerli bir adres olmalıdır.',
    'uuid' => ':Attribute geçerli bir UUID olmalıdır.',

    'attributes' => [
        'email' => 'e-posta',
        'password' => 'şifre',
        'currency' => 'para birimi',
        'locale' => 'dil',
        'theme' => 'tema',
        'package' => 'puan paketi',
        'provider' => 'ödeme yöntemi',
    ],
];
