#!/bin/bash
echo "Stopping containers..."
sudo docker compose down

echo "Rebuilding containers..."
sudo docker compose up -d --build

echo "Waiting for MySQL to start..."
sleep 15

echo "Loading CSV data..."
sudo docker exec se_project_backend-php-1 php /var/www/html/load_data.php

echo "Testing API..."
curl -X GET http://localhost:8080/