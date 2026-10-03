<?php

namespace Slimpay\School\Laravel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Slimpay\School\Exceptions\InvalidSignatureException;
use Slimpay\School\Exceptions\MalformedRequestException;
use Slimpay\School\LookupHandler;
use Slimpay\School\WebhookHandler;

/**
 * Add the routes to your CSRF exclusions (they're server-to-server):
 *   $middleware->validateCsrfTokens(except: ['slimpay/*']);
 */
class SlimpayController
{
    public function student(Request $request): JsonResponse
    {
        return $this->respond(fn () => app(LookupHandler::class)->handleStudent($request->getContent(), $request->headers->all()));
    }

    public function invoice(Request $request): JsonResponse
    {
        return $this->respond(fn () => app('slimpay.invoice-lookup')->handleInvoice($request->getContent(), $request->headers->all()));
    }

    public function webhook(Request $request, WebhookHandler $webhooks): JsonResponse
    {
        return $this->respond(function () use ($request, $webhooks) {
            $webhooks->handle($request->getContent(), $request->headers->all());
            return ['received' => true];
        });
    }

    private function respond(callable $work): JsonResponse
    {
        try {
            return response()->json($work());
        } catch (InvalidSignatureException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        } catch (MalformedRequestException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
