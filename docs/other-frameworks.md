# Other frameworks and plain PHP

The core classes only need two things from your framework: the **raw request body** and the **request headers**.

## Reading headers without `getallheaders()`

```php
function request_headers(): array {
    if (function_exists('getallheaders')) {
        return getallheaders();
    }
    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with($k, 'HTTP_')) {
            $headers[str_replace('_', '-', substr($k, 5))] = $v;
        }
    }
    return $headers;
}
```

Header names are matched case-insensitively, so any casing works.

## Symfony

```php
#[Route('/slimpay/student-lookup', methods: ['POST'])]
public function student(Request $request, LookupHandler $handler): JsonResponse
{
    try {
        return $this->json($handler->handleStudent($request->getContent(), $request->headers->all()));
    } catch (InvalidSignatureException) {
        return $this->json(['error' => 'unauthorized'], 401);
    } catch (MalformedRequestException) {
        return $this->json(['error' => 'bad request'], 400);
    }
}
```

Register `LookupHandler` as a service with your secret and lookup implementation as arguments.

## Slim / PSR-7

```php
$app->post('/slimpay/webhook', function ($request, $response) use ($webhooks) {
    try {
        $webhooks->handle((string) $request->getBody(), $request->getHeaders());
        $response->getBody()->write('{"received":true}');
        return $response->withHeader('Content-Type', 'application/json');
    } catch (InvalidSignatureException) {
        return $response->withStatus(401);
    } catch (MalformedRequestException) {
        return $response->withStatus(400);
    }
});
```

`getHeaders()` returns arrays of values; the SDK accepts both strings and arrays.

## CodeIgniter 4

```php
$body    = $this->request->getBody();
$headers = array_map(fn ($h) => $h->getValue(), $this->request->headers());
```

## WordPress / plugins

Use `file_get_contents('php://input')` inside a `register_rest_route` callback (not the parsed params), and read headers from `$request->get_headers()`.

## Rules for any framework

- Pass the **raw** body, not a re-encoded array. Re-encoding changes bytes and breaks the signature.
- Return JSON with `Content-Type: application/json` from lookup endpoints.
- Don't put these endpoints behind login, CSRF or CORS middleware; they're server-to-server.
