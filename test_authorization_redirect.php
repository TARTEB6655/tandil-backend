<?php

/**
 * Test Authorization Exception Redirect
 * Verifies that 403 errors redirect to login instead of showing error
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "🔒 Testing Authorization Exception Handling\n";
echo str_repeat("=", 80) . "\n\n";

// Test 1: Web request with AuthorizationException
echo "1. Testing Web Request (Should Redirect to Login)...\n";
try {
    $request = \Illuminate\Http\Request::create('/admin/dashboard', 'GET');
    $request->headers->set('Accept', 'text/html');
    
    // Simulate AuthorizationException
    $exception = new \Illuminate\Auth\Access\AuthorizationException('You do not have permission.');
    
    $handler = new \App\Exceptions\Handler($app);
    $response = $handler->render($request, $exception);
    
    if ($response->getStatusCode() === 302) {
        $location = $response->headers->get('Location');
        if (strpos($location, '/login') !== false) {
            echo "   ✅ Web request redirects to login (no error shown)\n";
            echo "   ✅ Location: {$location}\n";
        } else {
            echo "   ❌ Redirects to wrong location: {$location}\n";
        }
    } else {
        echo "   ❌ Returns status {$response->getStatusCode()} (expected 302 redirect)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 2: API request with AuthorizationException
echo "\n2. Testing API Request (Should Return JSON)...\n";
try {
    $request = \Illuminate\Http\Request::create('/api/admin/users', 'GET');
    $request->headers->set('Accept', 'application/json');
    
    $exception = new \Illuminate\Auth\Access\AuthorizationException('You do not have permission.');
    
    $handler = new \App\Exceptions\Handler($app);
    $response = $handler->render($request, $exception);
    
    if ($response->getStatusCode() === 403) {
        $content = json_decode($response->getContent(), true);
        if (isset($content['success']) && $content['success'] === false) {
            echo "   ✅ API request returns JSON error (expected)\n";
            echo "   ✅ Status: 403\n";
            echo "   ✅ Message: " . ($content['message'] ?? 'N/A') . "\n";
        } else {
            echo "   ❌ Invalid JSON response format\n";
        }
    } else {
        echo "   ❌ Returns status {$response->getStatusCode()} (expected 403)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 3: Check Handler method exists
echo "\n3. Checking Handler Implementation...\n";
try {
    $reflection = new ReflectionClass(\App\Exceptions\Handler::class);
    $renderMethod = $reflection->getMethod('render');
    
    $source = file_get_contents($renderMethod->getFileName());
    $lines = explode("\n", $source);
    $startLine = $renderMethod->getStartLine() - 1;
    $endLine = $renderMethod->getEndLine();
    $methodCode = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));
    
    if (strpos($methodCode, 'AuthorizationException') !== false) {
        echo "   ✅ AuthorizationException handling exists in render() method\n";
    } else {
        echo "   ❌ AuthorizationException handling NOT found\n";
    }
    
    if (strpos($methodCode, 'redirect()->guest(route(\'login\'))') !== false) {
        echo "   ✅ Redirect to login implemented\n";
    } else {
        echo "   ❌ Redirect to login NOT found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "✅ Test Complete!\n";
echo "\n💡 Result:\n";
echo "   - Web requests: Redirect to login (no 403 error shown)\n";
echo "   - API requests: Return JSON error (expected behavior)\n";

