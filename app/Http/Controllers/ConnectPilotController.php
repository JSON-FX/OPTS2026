<?php

namespace App\Http\Controllers;

use App\Services\ConnectPilotHost;
use App\Services\SSOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ConnectPilotController extends Controller
{
    public function launch(Request $request, ConnectPilotHost $host, SSOService $sso): JsonResponse
    {
        try {
            $data = $host->launch($request, $sso);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Connect is unavailable.'], 503)->header('Cache-Control', 'no-store');
        }

        return response()->json($data)->header('Cache-Control', 'no-store');
    }

    public function introspect(Request $request, ConnectPilotHost $host, SSOService $sso): JsonResponse
    {
        try {
            $data = $host->introspect($request, $sso);
        } catch (RuntimeException $exception) {
            return response()->json(['active' => false], $exception->getCode() === 503 ? 503 : 403)
                ->header('Cache-Control', 'no-store');
        }

        return response()->json($data)->header('Cache-Control', 'no-store');
    }
}
