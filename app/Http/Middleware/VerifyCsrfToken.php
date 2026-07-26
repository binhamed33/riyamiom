<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class VerifyCsrfToken extends BaseVerifier
{
    protected $except = [
        'logout',
    ];

    protected function tokensMatch($request)
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return false;
        }

        $sessionToken = $request->session()->token();

        if ($sessionToken && hash_equals($sessionToken, $token)) {
            return true;
        }

        $cookieToken = $request->cookie('XSRF-TOKEN');

        if ($cookieToken && hash_equals($cookieToken, $token)) {
            return true;
        }

        return false;
    }
}
