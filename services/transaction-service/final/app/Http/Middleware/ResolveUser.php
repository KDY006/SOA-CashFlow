<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResolveUser
{
    public function handle(Request $request, Closure $next)
    {
        // Gateway must authenticate the token and overwrite this header.
        $id = $request->header('X-User-Id');
        abort_unless(filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0, 401, 'Missing or invalid X-User-Id');
        $request->attributes->set('user_id', (int) $id);

        return $next($request);
    }
}
