<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\RealEngineGuard;
use Tests\Support\RealEngineRefused;

/**
 * Child process of the real-engine tests. NOT a test and never run by PHPUnit on its own.
 *
 *   php real_engine_worker.php <barrierFile> <userId> <jsonRequest>
 *
 * It boots the application against the disposable MySQL/MariaDB database named in the environment, refuses to go
 * on unless RealEngineGuard accepts that configuration, signs in as <userId>, writes <barrierFile>.ready, waits
 * until <barrierFile> exists (so the test decides exactly when the request is sent) and then sends ONE real
 * request through the HTTP kernel, so the real controller code path and its locking are exercised.
 * It prints one JSON line.
 */
[$_, $barrier, $userId, $json] = $argv;
$request = json_decode($json, true);

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    // Same gate as the parent: a worker never touches a database the guard would not have wiped.
    RealEngineGuard::assertConfiguration(RealEngineGuard::contextFromApplication());
} catch (RealEngineRefused $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()])."\n";
    exit(3);
}

$user = User::with(['role', 'roles', 'outlet', 'outlets'])->findOrFail($userId);
$app['auth']->guard('web')->setUser($user);

touch($barrier.'.ready');

$deadline = microtime(true) + 60;
while (! file_exists($barrier) && microtime(true) < $deadline) {
    usleep(200);
}

$out = ['ok' => false];

try {
    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle(
        Request::create($request['uri'], $request['method'] ?? 'POST', $request['data'] ?? [])
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
} catch (HttpResponseException|HttpException $e) {
    $out['status'] = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 0;
} catch (Throwable $e) {
    $out['error'] = get_class($e).': '.substr($e->getMessage(), 0, 200);
}

echo json_encode($out)."\n";
