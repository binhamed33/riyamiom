@php
    $userPermissions = isset($user) ? $user->permissionNames() : [];
    $allPermissions = config('permissions.groups');
@endphp

<input type="hidden" name="_permissions" value="1">
<div class="p-6 rounded-2xl glass-card">
    <h3 class="text-base font-semibold text-gold mb-4 border-b border-ivory/10 pb-3">الصلاحيات الإضافية</h3>
    <p class="text-xs text-ivory/50 mb-4">حدد صلاحيات إضافية للمستخدم فوق صلاحيات دوره الأساسي</p>

    <div class="space-y-6">
        @foreach($allPermissions as $groupKey => $group)
            <div>
                <h4 class="text-sm font-medium text-ivory/70 mb-2">{{ __($group['label']) }}</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    @foreach($group['permissions'] as $permKey => $permLabel)
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 transition cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                                {{ in_array($permKey, old('permissions', $userPermissions)) ? 'checked' : '' }}
                                class="rounded border-white/20 bg-white/10 text-[#C9A55A] focus:ring-[#C9A55A] focus:ring-1">
                            <span class="text-xs text-ivory/70">{{ $permLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
