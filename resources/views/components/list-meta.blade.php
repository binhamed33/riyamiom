@props(['label'])
<div class="min-w-0">
    <dt class="text-gray-400 font-medium">{{ $label }}</dt>
    <dd class="text-gray-700 font-semibold truncate">{{ $slot }}</dd>
</div>
