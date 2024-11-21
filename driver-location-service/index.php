<?php

/**
 * Async HTTP Server
 *
 * Req/Sec = ~8K
 *
 */

error_reporting(E_ALL);
ini_set("display_errors", 1);

require __DIR__ . '/vendor/autoload.php';

$redis = new Redis();

$redis->connect('127.0.0.1', 6379);

/**
 * Define routes
 */
$routes = include __DIR__ . '/routes.php';
include __DIR__ . '/exceptions.php';
include __DIR__ . '/request_handlers.php';

function handle_request($request, $resolve, $reject, $routes)
{
	$uri = $request->getUri();
	$path = $uri->getPath();

	//echo $path;

	if (!array_key_exists($path, $routes)) {
		echo " - ROUTE NOT FOUND", PHP_EOL;
		return $reject(new HTTPNotFoundException);
	}

	//echo " - ROUTE FOUND", PHP_EOL;
	$handler = $routes[$path];

	return $handler($request, $resolve, $reject);
}

$http_server = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) use ($routes) {

	$promise = new React\Promise\Promise(function ($resolve, $reject) use ($request, $routes) {

		handle_request($request, $resolve, $reject, $routes);

	});

	return $promise->then(function($data) {
		return React\Http\Message\Response::json($data);
	})->catch(function($error) {
		echo "Promise Reject - Error: ".$error->getStatusCode(), PHP_EOL;
		return new React\Http\Message\Response(
			$error->getStatusCode(),
			[ 'Content-Type' => 'text/plain' ],
		);
	});
});

$socket = new React\Socket\SocketServer('127.0.0.1:8001');
$http_server->listen($socket);
