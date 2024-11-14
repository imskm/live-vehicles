echo
echo "-------------------------"
echo "Driver location update:"

curl -X POST http://127.0.0.1:8000/drivers/location-update -H 'Content: application/json' -d '{"id": 1, "first_name": "Driver", "last_name": "One", "location": {"lat": 22.5726, "lng": 88.3639}}'
curl -X POST http://127.0.0.1:8000/drivers/location-update -H 'Content: application/json' -d '{"id": 2, "first_name": "Driver", "last_name": "Two", "location": {"lat": 23.6850, "lng": 86.9530}}'


echo
echo "-------------------------"
echo "Total number of drivers online:"

curl http://127.0.0.1:8000/drivers/total-live


echo
echo "-------------------------"
echo "Drivers nearby:"
# Nearby of kolkata location
# Original: (22.5726, 88.3639)
# New: (22.5567, 88.3587)
#
# Original: (23.6850, 86.9530)
# New: (23.6853, 86.9533)

curl -iv "http://127.0.0.1:8000/drivers/nearby?lat=22.5567&lng=88.3587"
curl -iv "http://127.0.0.1:8000/drivers/nearby?lat=23.6853&lng=86.9533"


#curl -X POST http://127.0.0.1:8000/set-driver-location.php -d '{"driver_id": 1, "first_name": "Driver", "last_name": "One", "location": {"lat": 22.5726, "lng": 88.3639}}'
#curl -X POST http://127.0.0.1:8000/driver/locations -d '{"driver_id": 1, "first_name": "Driver", "last_name": "One", "location": {"lat": 22.5726, "lng": 88.3639}}'
# set-dirver-location.php
