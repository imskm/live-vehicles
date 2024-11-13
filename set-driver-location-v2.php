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

$routes = [
	'/drivers/total-live' 		=> "handle_total_live_drivers",
	'/drivers/location-update' 	=> "handle_driver_location_update",
];

class HttpNotFoundException extends \Exception
{
	public function getStatusCode()
	{
		return React\Http\Message\Response::STATUS_NOT_FOUND;
	}
}

function handle_total_live_drivers($request, $resolve, $reject)
{
	global $redis;
	echo "handle_total_live_drivers\n";

	$data = [
		'data' => [
			'total_count' => 1,
		],
	];

	$resolve($data);
}

function handle_driver_location_update($request, $resolve, $reject)
{
	global $redis;

	//$payload = file_get_contents("php://input");
	$payload = (string) $request->getBody();
	//var_dump($payload);
	// Driver structure
	//$driver = [
	//	'id' 			=> (int) $_POST['id'],
	//	'first_name' 	=> $_POST['first_name'],
	//	'last_name' 	=> $_POST['last_name'],
	//	'location'		=> [
	//		'lat' 		=> 22.5726,
	//		'lng' 		=> 88.3639,
	//	],
	//];
	$driver = json_decode($payload);
	//var_dump($driver);

	// If payload is invalid then exit
	if ($driver === false) {
		return;
	}

	// key          value
	// drivers.ID = ...
	//
	$key = 'driver_locations.'.$driver->id;
	/**
	 * Max TTL for driver last location is 1hr.
	 * Driver not active for continue 1hr will be marked as offline and removed.
	 */
	$redis->set($key, $payload, 360);

	$ret = $redis->get($key);

	//echo "Recieved: {$ret}\n";

	$data = [
		'data' => null,
	];

	// It requires exactly 1 arg
	$resolve($data);
}

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

$socket = new React\Socket\SocketServer('127.0.0.1:8000');
$http_server->listen($socket);
