<?php

namespace App\Http\Controllers;

use App\Support\Appearance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * حفظ تفضيل المظهر للمستخدم الحالي وحده.
 * لا يقبل معرّف مستخدم من الطلب، فلا يستطيع أحد تغيير مظهر غيره.
 */
class AppearanceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['nullable', 'string', 'max:20'],
            'appearance' => ['nullable', 'string', 'max:10'],
        ]);

        $user = $request->user();
        $changed = false;

        if (Appearance::isValidTheme($validated['theme'] ?? null)) {
            $user->theme = $validated['theme'];
            $changed = true;
        }

        if (Appearance::isValidMode($validated['appearance'] ?? null)) {
            $user->appearance = $validated['appearance'];
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }

        return response()->json([
            'ok' => $changed,
            'theme' => Appearance::themeKey($user),
            'appearance' => Appearance::mode($user),
        ]);
    }
}
