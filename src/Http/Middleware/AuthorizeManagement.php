<?php

namespace Coding1688\Files\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeManagement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user(config('files.management.guard'));
        abort_unless($user, 401);
        $gate = config('files.management.gate', 'manage-files');
        abort_unless(is_string($gate) && $gate !== '', 403);
        Gate::forUser($user)->authorize($gate);

        return $next($request);
    }
}
