<?php

/*
 | Türkçe doğrulama mesajları. Alan adları form isteklerindeki attributes()
 | metotlarından veya aşağıdaki "attributes" dizisinden gelir.
 */

return [
    'accepted' => ':attribute kabul edilmelidir.',
    'alpha' => ':attribute yalnızca harf içerebilir.',
    'array' => ':attribute bir liste olmalıdır.',
    'boolean' => ':attribute alanı doğru veya yanlış olmalıdır.',
    'confirmed' => ':attribute tekrarı eşleşmiyor.',
    'date' => ':attribute geçerli bir tarih olmalıdır.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'exists' => 'Seçilen :attribute geçersiz.',
    'file' => ':attribute bir dosya olmalıdır.',
    'image' => ':attribute bir görsel olmalıdır.',
    'in' => 'Seçilen :attribute geçersiz.',
    'integer' => ':attribute tam sayı olmalıdır.',
    'max' => [
        'numeric' => ':attribute en fazla :max olabilir.',
        'file' => ':attribute en fazla :max kilobayt olabilir.',
        'string' => ':attribute en fazla :max karakter olabilir.',
        'array' => ':attribute en fazla :max öğe içerebilir.',
    ],
    'mimes' => ':attribute şu türlerden biri olmalıdır: :values.',
    'min' => [
        'numeric' => ':attribute en az :min olmalıdır.',
        'file' => ':attribute en az :min kilobayt olmalıdır.',
        'string' => ':attribute en az :min karakter olmalıdır.',
        'array' => ':attribute en az :min öğe içermelidir.',
    ],
    'numeric' => ':attribute sayı olmalıdır.',
    'password' => [
        'letters' => ':attribute en az bir harf içermelidir.',
        'mixed' => ':attribute en az bir büyük ve bir küçük harf içermelidir.',
        'numbers' => ':attribute en az bir rakam içermelidir.',
        'symbols' => ':attribute en az bir sembol içermelidir.',
        'uncompromised' => 'Bu :attribute bir veri sızıntısında yer almış. Lütfen farklı bir :attribute seçin.',
    ],
    'regex' => ':attribute biçimi geçersiz.',
    'required' => ':attribute alanı zorunludur.',
    'size' => [
        'numeric' => ':attribute :size olmalıdır.',
        'string' => ':attribute :size karakter olmalıdır.',
    ],
    'string' => ':attribute metin olmalıdır.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'url' => ':attribute geçerli bir adres olmalıdır.',
    'uuid' => ':attribute geçerli bir UUID olmalıdır.',

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
