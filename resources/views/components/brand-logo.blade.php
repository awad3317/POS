@props(['size' => 'md', 'rounded' => 'rounded-xl'])
@php
    $sizes = ['sm' => 'h-9 w-9 p-1', 'md' => 'h-10 w-10 p-1', 'lg' => 'h-12 w-12 p-1.5', 'xl' => 'h-14 w-14 p-1.5'];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp
<div {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center border border-gray-200 bg-white shadow-xs overflow-hidden {$rounded} {$sizeClass}"]) }}>
    <img src="{{ asset('icons/icon-192.png') }}?v=2" alt="شعار تيار بلس" class="h-full w-full object-contain">
</div>
