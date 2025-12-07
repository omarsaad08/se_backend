<?php
include_once 'config/Database.php';

function loadCSVData() {
    $database = new Database();
    $conn = $database->getConnection();

    if ($conn === null) {
        echo "Failed to connect to the database. Exiting.\n";
        return false;
    }
    
    try {
        // Create table if it doesn't exist
        $createTableSQL = "CREATE TABLE IF NOT EXISTS environmental_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            area_id INT,
            year INT,
            season VARCHAR(20),
            ndvi DOUBLE,
            evi DOUBLE,
            ndwi DOUBLE,
            temp DOUBLE
        )";
        $conn->exec($createTableSQL);
        echo "Table 'environmental_data' created or already exists.\n";
        
        $csvFile = __DIR__ . '/config/merged_environmental_data.csv';
        
        if (!file_exists($csvFile)) {
            echo "CSV file not found: " . $csvFile . "\n";
            return false;
        }
        
        // Clear existing data
        $conn->exec("TRUNCATE TABLE environmental_data");
        echo "Cleared existing data from environmental_data table.\n";
        
        // Read and insert CSV data
        $file = fopen($csvFile, 'r');
        if (!$file) {
            echo "Cannot open CSV file\n";
            return false;
        }
        
        $header = fgetcsv($file); // Skip header row
        echo "CSV Header: " . implode(', ', $header) . "\n";
        
        $stmt = $conn->prepare("INSERT INTO environmental_data (area_id, year, season, ndvi, evi, ndwi, temp) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $count = 0;
        while (($row = fgetcsv($file)) !== FALSE) {
            if (count($row) >= 7) {
                $stmt->execute([$row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6]]);
                $count++;
            }
        }
        
        fclose($file);
        echo "Successfully loaded $count records from CSV\n";
        return true;
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run the data loader
loadCSVData();
?>