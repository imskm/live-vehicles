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

function del_online_driver_geolocation(int $driver_id): void
{
	global $redis;

	$driver_location_key_in_ht = driver_location_key($driver_id);
	$result = $redis->zRem(DRIVER_GEOCORD_STORE_NAME, $driver_location_key_in_ht);

	echo "Removed driver from geospatial key: driver_id={$driver_id}, result={$result}";
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

function driver_json_array_to_php_array(mixed $drivers): array
{
	$result = [
		'drivers' => [],
	];

	if ($drivers === false) {
		return $result;
	}

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
		$radius = 5.0,
	);

	if (isset($params['groupby']) && $params['groupby'] === 'vehicle_type') {
		$result = group_drivers_by_vehicle_type($result['drivers']);
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

	// @NOTE There can be fullname of the lat and lng sent instead of short form
	// need to handle it.
	if (isset($driver->location->latitude)) {
		$driver->location->lat = $driver->location->latitude;
		unset($driver->location->latitude);
	}
	if (isset($driver->location->longitude)) {
		$driver->location->lng = $driver->location->longitude;
		unset($driver->location->longitude);
	}

	$key = driver_location_key($driver->id);

	/**
	 * Max TTL for driver last location is 1hr.
	 * Driver not active for continue 1hr will be marked as offline and removed.
	 */
	$redis->hSet(DRIVER_LOCATION_HT_NAME, $key, json_encode($driver));

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

function handle_drivers_remove($request, $resolve, $reject)
{
	global $redis;

	$payload = (string) $request->getBody();
	$driver = json_decode($payload);
	if ($driver === false) {
		return;
	}

	$key = driver_location_key($driver->id);

	$ret = $redis->hDel(DRIVER_LOCATION_HT_NAME, $key);
	if ($ret === false) {
		echo "handle_drivers_remove: {$key} not found\n";
	}

	// Remove the driver geolocation from geospatial data
	del_online_driver_geolocation($driver->id);

	$data = [
		'data' => null,
	];

	$resolve($data);
}

