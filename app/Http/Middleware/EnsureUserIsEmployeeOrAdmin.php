<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureUserIsEmployeeOrAdmin
{
    /**
     * Allow only employees and admins through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isEmployee() && ! $user->isAdmin())) {
            throw new AccessDeniedHttpException('You are not authorized to perform this action.');
        }

        return $next($request);
    }
}
