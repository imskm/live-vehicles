#!/bin/bash

wrk -t12 -c400 -d30s -s benchmark.lua http://127.0.0.1:8001/drivers/location-update
