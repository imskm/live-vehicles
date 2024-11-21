<?php

define("DRIVER_LOCATION_HT_NAME", "driver_locations");
define("DRIVER_GEOCORD_STORE_NAME", "driver_geolocatoins");

function driver_location_key(int $id): string
{
	// key          value
	// drivers.ID = ...
	//
	return "drivers.{$id}";
}

function get_online_driver_count(): int
{
	global $redis;

	$count = $redis->hLen(DRIVER_LOCATION_HT_NAME);

	return $count;
}

function set_online_driver_geolocation(float $lat, float $lng, int $driver_id): void
{
	global $redis;

	// @TODO Need to delete the previous location of the a driver whose geolocation
	// is already recorded in redis cache and now he has updated location so need to
	// update the previous location to new location and not set new location of the
	// same driver.

	$driver_location_key_in_ht = driver_location_key($driver_id);
	$result = $redis->geoAdd(DRIVER_GEOCORD_STORE_NAME, $lng, $lat, $driver_location_key_in_ht);

	// Number of elements added in geospatial key
	echo "Number of elements added in geospatial key: {$result}\n";
}

function get_online_nearby_drivers_geolocation(float $lat, float $lng, float $radius = 2.0, string $unit = 'km'): array
{
	global $redis;

	$result = $redis->geoRadius(DRIVER_GEOCORD_STORE_NAME, $lng, $lat, $radius, $unit);

	$drivers = $redis->hMGet(DRIVER_LOCATION_HT_NAME, $result);

	echo "NEARBY DRIVERS FOUND: EXTRACTED FROM HASH TABLE\n";
	var_dump($drivers);

	$nearby_drivers = driver_json_array_to_php_array($drivers);

	return $nearby_drivers;
}

function driver_json_array_to_php_array(array $drivers): array
{
	$result = [];
	foreach ($drivers as $key => $json) {
		$result['drivers'][] = json_decode($json);
	}

	return $result;
}

/**
 * @return Assoc array of drivers keyed by vehicle_type_id
 */
function group_drivers_by_vehicle_type(array $drivers)
{
	$result = [];
	foreach ($drivers as $d) {
		$result[$d->vehicle_type_id][] = $d;
	}

	return $result;
}




/* -------------------------------------------------------------
 *  Request handlers
 * -------------------------------------------------------------
 *
 *
 *
 */

function handle_drivers_nearby($request, $resolve, $reject)
{
	$params = $request->getQueryParams();

	$result = get_online_nearby_drivers_geolocation(
		(float) $params['lat'],
		(float) $params['lng'],
	);

	if (isset($params['groupby']) && $params['groupby'] === 'vehicle_type') {
		$result = group_drivers_by_vehicle_type($result['drivers']);
		echo "--------------------\n";
		var_dump($result);
	}

	$data = [
		'data' => $result,
	];

	$resolve($data);
}

function handle_all_online_drivers($request, $resolve, $reject)
{
	global $redis;

	$drivers = $redis->hGetAll(DRIVER_LOCATION_HT_NAME);

	var_dump($drivers);
	$result = driver_json_array_to_php_array($drivers);

	$data = [
		'data' => $result,
	];

	$resolve($data);
}

function handle_total_live_drivers($request, $resolve, $reject)
{
	global $redis;
	echo "handle_total_live_drivers\n";

	$data = [
		'data' => [
			'total_count' => get_online_driver_count(),
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

	$key = driver_location_key($driver->id);

	/**
	 * Max TTL for driver last location is 1hr.
	 * Driver not active for continue 1hr will be marked as offline and removed.
	 */
	$redis->hSet(DRIVER_LOCATION_HT_NAME, $key, $payload);

	$ret = $redis->hGet(DRIVER_LOCATION_HT_NAME, $key);

	// Set the driver location in geocoding
	set_online_driver_geolocation($driver->location->lat, $driver->location->lng, $driver->id);

	echo "Recieved: {$ret}\n";

	$data = [
		'data' => null,
	];

	// It requires exactly 1 arg
	$resolve($data);
}

