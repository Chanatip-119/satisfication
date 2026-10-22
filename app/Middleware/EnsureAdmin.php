<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $roleId = (int) $request->user()->role_id;

        abort_unless(in_array($roleId, [1, 2, 3], true), 403, 'เฉพาะผู้ดูแลระบบเท่านั้นที่ทำรายการนี้ได้');

        return $next($request);
    }
}