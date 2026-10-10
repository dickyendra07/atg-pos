<?php

/**
 * Child process of RealEngineConcurrencyTest. NOT a test and never run by PHPUnit on its own.
 *
 *   php real_engine_worker.php <barrierFile> <userId> <jsonRequest>
 *
 * It boots the application against the disposable MySQL/MariaDB database named in the environment, signs in
 * as <userId>, waits until <barrierFile> exists (so every worker fires at the same instant) and then sends ONE
 * real request through the HTTP kernel, so the real controller code path and its locking are exercised.
 * It prints one JSON line.
 */

[$_, $barrier, $userId, $json] = $argv;
$request = json_decode($json, true);

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::with(['role', 'roles', 'outlet', 'outlets'])->findOrFail($userId);
$app['auth']->guard('web')->setUser($user);

$deadline = microtime(true) + 30;
while (! file_exists($barrier) && microtime(true) < $deadline) {
    usleep(200);
}

// Optional head start for the other requests, so a test can sweep the interleaving of two operations.
if (! empty($request['delay_ms'])) {
    usleep((int) $request['delay_ms'] * 1000);
}

$out = ['ok' => false];

try {
    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle(
        Illuminate\Http\Request::create($request['uri'], $request['method'] ?? 'POST', $request['data'] ?? [])
    );
    $session = $app['session.store'];
    $errors = $session->get('errors');

    $out['status'] = $response->getStatusCode();
    if (isset($response->exception)) {
        $out['error'] = get_class($response->exception).': '.substr($response->exception->getMessage(), 0, 240);
    }
    $out['validation_failed'] = $errors && $errors->any();
    $out['flash_error'] = $session->get('error');
    $out['ok'] = $response->getStatusCode() < 400 && ! $out['validation_failed'] && ! $out['flash_error'];
} catch (Illuminate\Http\Exceptions\HttpResponseException|Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $out['status'] = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 0;
} catch (Throwable $e) {
    $out['error'] = get_class($e).': '.substr($e->getMessage(), 0, 200);
}

echo json_encode($out)."\n";
