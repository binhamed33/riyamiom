<?php

namespace App\Support;

/**
 * هوية مُداوَلة كما يراها المكتب.
 *
 * النطاق كان مكتوباً بيده في عشرة قوالب. تغييرُ واحدٍ منها كان يعني
 * مطاردتها جميعاً — ونسيانَ واحد. هنا موضعٌ واحد لا غير.
 */
class Mudawala
{
    /** رابط مُداوَلة، بشرطة مائلة في آخره دائماً. */
    public static function url(): string
    {
        return (string) config('mudawala.url');
    }
}
