Write-Host "Stopping containers..."
docker compose down

Write-Host "Rebuilding containers..."
docker compose up -d --build

Write-Host "Waiting for MySQL to start..."
Start-Sleep -Seconds 15

Write-Host "Loading CSV data..."
docker exec se_project_backend-php-1 php /var/www/html/load_data.php

Write-Host "Testing API..."
curl http://localhost:8080/
