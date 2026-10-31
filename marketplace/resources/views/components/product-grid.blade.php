@props(['products'])
<div {{ $attributes->class(['grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-3 lg:grid-cols-4']) }}>
    @foreach ($products as $product)
        <x-product-card :product="$product"/>
    @endforeach
</div>
