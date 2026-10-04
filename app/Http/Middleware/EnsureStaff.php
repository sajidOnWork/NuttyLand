<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Market staff screens: staff and owner roles only (report §13.1 role-based access). */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canUseStaffScreens(), 403, 'Staff access only.');

        return $next($request);
    }
}
