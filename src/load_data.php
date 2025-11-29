<?php
include_once 'config/Database.php';

function loadCSVData() {
    $database = new Database();
    $conn = $database->getConnection();
    
    $csvFile = __DIR__ . '/config/NDVI_processed4.csv';
    
    if (!file_exists($csvFile)) {
        echo "CSV file not found: " . $csvFile . "\n";
        return false;
    }
    
    try {
        // Clear existing data
        $conn->exec("TRUNCATE TABLE ndvi_data");
        
        // Read and insert CSV data
        $file = fopen($csvFile, 'r');
        if (!$file) {
            echo "Cannot open CSV file\n";
            return false;
        }
        
        $header = fgetcsv($file); // Skip header row
        echo "CSV Header: " . implode(', ', $header) . "\n";
        
        $stmt = $conn->prepare("INSERT INTO ndvi_data (avg_ndvi, season, year, area_id) VALUES (?, ?, ?, ?)");
        
        $count = 0;
        while (($row = fgetcsv($file)) !== FALSE) {
            if (count($row) >= 4) {
                $stmt->execute([$row[0], $row[1], $row[2], $row[3]]);
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