@php
    $userPermissions = isset($user) ? $user->permissionNames() : [];
    $allPermissions = config('permissions.groups');
@endphp

<input type="hidden" name="_permissions" value="1">
<div class="p-6 rounded-2xl glass-card">
    <h3 class="text-base font-semibold text-gold-dark mb-4 border-b border-gray-200 pb-3">الصلاحيات الإضافية</h3>
    <p class="text-xs text-gray-500 mb-4">حدد صلاحيات إضافية للمستخدم فوق صلاحيات دوره الأساسي</p>

    <div class="space-y-6">
        @foreach($allPermissions as $groupKey => $group)
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __($group['label']) }}</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    @foreach($group['permissions'] as $permKey => $permLabel)
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                                {{ in_array($permKey, old('permissions', $userPermissions)) ? 'checked' : '' }}
                                class="rounded border-gray-200 bg-white text-gold-dark focus:ring-gold-dark focus:ring-1">
                            <span class="text-xs text-gray-700">{{ $permLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
