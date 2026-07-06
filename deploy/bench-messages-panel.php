<?php
$base = '/home/gekymedia/web/chat.gekychat.com/public_html';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$slug = $argv[1] ?? 'chat-4euslnqa';
$conv = App\Models\Conversation::where('slug', $slug)->firstOrFail();

// Use first participant as viewer
$userId = (int) DB::table('conversation_user')->where('conversation_id', $conv->id)->value('user_id');
Auth::loginUsingId($userId);

$controller = app(App\Http\Controllers\ChatController::class);
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('loadMessagesPage');
$method->setAccessible(true);

DB::enableQueryLog();
$t0 = microtime(true);
$result = $method->invoke($controller, $conv, null, 30, true);
$queryMs = round((microtime(true) - $t0) * 1000);
$queries = count(DB::getQueryLog());

$t1 = microtime(true);
$html = App\Support\MessagePanelHtmlBuilder::render($conv, $result['messages']);
$renderMs = round((microtime(true) - $t1) * 1000);

echo "slug={$slug} user={$userId}\n";
echo "query={$queryMs}ms queries={$queries} messages={$result['messages']->count()}\n";
echo "render={$renderMs}ms htmlBytes=" . strlen($html) . "\n";
echo "total=" . ($queryMs + $renderMs) . "ms\n";
