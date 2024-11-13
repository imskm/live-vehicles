<?php

require __DIR__ . '/vendor/autoload.php';

$redis = new Redis();

$redis->connect('127.0.0.1', 6379);

$http_server = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) use ($redis) {
	//$payload = file_get_contents("php://input");
	$payload = (string) $request->getBody();
	var_dump($payload);
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
	var_dump($driver);

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

	echo "Recieved: {$ret}\n";

	return new React\Http\Message\Response(
		React\Http\Message\Response::STATUS_OK,
		[ 'Content-Type' => 'text/plain' ],
	);
});

$socket = new React\Socket\SocketServer('127.0.0.1:8000');
$http_server->listen($socket);
