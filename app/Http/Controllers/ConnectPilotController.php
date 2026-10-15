<?php

namespace App\Http\Controllers;

use App\Exceptions\SSOUnavailableException;
use App\Services\ConnectPilotHost;
use App\Services\SSOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ConnectPilotController extends Controller
{
    public function launch(Request $request, ConnectPilotHost $host, SSOService $sso): JsonResponse
    {
        try {
            $data = $host->launch($request, $sso);
        } catch (Throwable $exception) {
            return response()->json(['message' => 'Connect is unavailable.'], 503,
                $exception instanceof SSOUnavailableException ? $exception->retryHeaders() : [])
                ->header('Cache-Control', 'no-store');
        }

        return response()->json($data)->header('Cache-Control', 'no-store');
    }

    public function introspect(Request $request, ConnectPilotHost $host, SSOService $sso): JsonResponse
    {
        try {
            $data = $host->introspect($request, $sso);
        } catch (Throwable $exception) {
            return response()->json(
                ['active' => false],
                $exception instanceof HttpException ? $exception->getStatusCode() : 503,
                $exception instanceof HttpException ? $exception->getHeaders()
                    : ($exception instanceof SSOUnavailableException ? $exception->retryHeaders() : []),
            )
                ->header('Cache-Control', 'no-store');
        }

        return response()->json($data)->header('Cache-Control', 'no-store');
    }
}
