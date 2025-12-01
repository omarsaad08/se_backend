<?php
class ChartsController {
    private $db;
    private $envData;
    private $flaskUrl = "http://host.docker.internal:5000";

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->envData = new EnvironmentalData($this->db);
    }

    // GET - Route chart data requests
    public function handleGet() {
        if(isset($_GET['chart'])) {
            // ?chart=1&startYear=2000&endYear=2040&areaId=all&season=all&metric=ndvi
            $this->getChartData(
                $_GET['startYear'] ?? null,
                $_GET['endYear'] ?? null,
                $_GET['areaId'] ?? 'all',
                $_GET['season'] ?? 'all',
                $_GET['metric'] ?? 'ndvi'
            );
        } else if(isset($_GET['areas'])) {
            // Get all available areas
            $this->getAllAreas();
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Invalid chart request. Use ?chart=1&startYear=2000&endYear=2040&areaId=all&season=all&metric=ndvi"));
        }
    }

    /**
     * Get all unique areas from the database
     */
    private function getAllAreas() {
        try {
            $stmt = $this->db->prepare("SELECT DISTINCT area_id FROM environmental_data ORDER BY area_id");
            $stmt->execute();
            $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            http_response_code(200);
            echo json_encode(array(
                "areas" => array_map('intval', $areas),
                "count" => count($areas)
            ));
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(array(
                "message" => "Error fetching areas",
                "error" => $e->getMessage()
            ));
        }
    }

    /**
     * Get chart data with merged historical and predicted data
     * startYear: 1984 to 2050
     * endYear: 1984 to 2050
     * areaId: 'all' or specific area ID
     * season: 'all', 'winter', 'spring', 'summer', 'autumn'
     * metric: 'ndvi', 'evi', 'ndwi', 'temp'
     */
    private function getChartData($startYear, $endYear, $areaId, $season, $metric) {
        try {
            // Validate parameters
            if($startYear === null || $endYear === null) {
                http_response_code(400);
                echo json_encode(array("message" => "Missing startYear or endYear parameters"));
                return;
            }

            $startYear = intval($startYear);
            $endYear = intval($endYear);
            $areaId = $areaId === 'all' ? 'all' : intval($areaId);
            $season = strtolower($season);
            $metric = strtolower($metric);

            // Validate year range
            if($startYear < 1984 || $endYear > 2050 || $startYear > $endYear) {
                http_response_code(400);
                echo json_encode(array("message" => "Invalid year range. Years must be between 1984 and 2050, and startYear <= endYear"));
                return;
            }

            // Validate season
            $valid_seasons = array("all", "winter", "spring", "summer", "autumn");
            if(!in_array($season, $valid_seasons)) {
                http_response_code(400);
                echo json_encode(array("message" => "Invalid season. Valid options: " . implode(", ", $valid_seasons)));
                return;
            }

            // Validate metric
            $valid_metrics = array('ndvi', 'evi', 'ndwi', 'temp');
            if(!in_array($metric, $valid_metrics)) {
                http_response_code(400);
                echo json_encode(array("message" => "Invalid metric. Valid options: " . implode(", ", $valid_metrics)));
                return;
            }

            // Restrict weird chart combinations
            // If areaId is 'all', they can choose any season
            // If areaId is specific, they can choose any season
            // Always allow the combination

            $result = array(
                "startYear" => $startYear,
                "endYear" => $endYear,
                "areaId" => $areaId,
                "season" => $season,
                "metric" => $metric,
                "data" => array(),
                "metadata" => array(
                    "historical_years" => array(),
                    "predicted_years" => array(),
                    "total_data_points" => 0
                )
            );

            // Split years into historical (1984-2024) and predicted (2025-2050)
            $historicalEndYear = min($endYear, 2024);
            $predictedStartYear = max($startYear, 2025);

            // Get historical data
            if($startYear <= 2024) {
                $historicalData = $this->getHistoricalData(
                    $startYear,
                    $historicalEndYear,
                    $areaId,
                    $season,
                    $metric
                );
                if(isset($historicalData["data"])) {
                    $result["data"] = array_merge($result["data"], $historicalData["data"]);
                    $result["metadata"]["historical_years"] = range($startYear, $historicalEndYear);
                }
            }

            // Get predicted data
            if($endYear >= 2025) {
                $predictedData = $this->getPredictedData(
                    $predictedStartYear,
                    $endYear,
                    $areaId,
                    $season,
                    $metric
                );
                if(isset($predictedData["data"])) {
                    $result["data"] = array_merge($result["data"], $predictedData["data"]);
                    $result["metadata"]["predicted_years"] = range($predictedStartYear, $endYear);
                }
            }

            // Sort data by year and then by area_id for consistent ordering
            usort($result["data"], function($a, $b) {
                if($a['year'] === $b['year']) {
                    return $a['area_id'] - $b['area_id'];
                }
                return $a['year'] - $b['year'];
            });

            $result["metadata"]["total_data_points"] = count($result["data"]);

            http_response_code(200);
            echo json_encode($result);

        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(array(
                "message" => "Error getting chart data",
                "error" => $e->getMessage()
            ));
        }
    }

    /**
     * Get historical data from database (1984-2024)
     */
    private function getHistoricalData($startYear, $endYear, $areaId, $season, $metric) {
        try {
            $query = "SELECT id, area_id, year, season, ndvi, evi, ndwi, temp FROM environmental_data WHERE year BETWEEN ? AND ?";
            $params = array($startYear, $endYear);

            if($areaId !== 'all') {
                $query .= " AND area_id = ?";
                $params[] = $areaId;
            }

            if($season !== 'all') {
                $query .= " AND season = ?";
                $params[] = $season;
            }

            $query .= " ORDER BY year ASC, area_id ASC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $num = $stmt->rowCount();

            $result = array("data" => array());

            if($num > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $item = array(
                        "id" => $row["id"],
                        "area_id" => intval($row["area_id"]),
                        "year" => intval($row["year"]),
                        "season" => $row["season"],
                        $metric => floatval($row[$metric]),
                        "is_prediction" => false
                    );
                    array_push($result["data"], $item);
                }
            }

            return $result;
        } catch(Exception $e) {
            error_log("Error fetching historical data: " . $e->getMessage());
            return array("data" => array());
        }
    }

    /**
     * Get predicted data (2025-2050)
     */
    private function getPredictedData($startYear, $endYear, $areaId, $season, $metric) {
        try {
            // Get all areas
            $stmt = $this->db->prepare("SELECT DISTINCT area_id FROM environmental_data ORDER BY area_id");
            $stmt->execute();
            $allAreas = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Filter areas if specific area is requested
            $areas = $areaId === 'all' ? $allAreas : array($areaId);
            
            // Validate that requested area exists
            if($areaId !== 'all' && !in_array($areaId, $allAreas)) {
                error_log("Requested area $areaId not found in database");
                return array("data" => array());
            }

            $result = array("data" => array());
            $seasons = $season === 'all' ? array('autumn', 'spring', 'summer', 'winter') : array($season);

            // For each year and season combination, make a prediction request
            for($year = $startYear; $year <= $endYear; $year++) {
                foreach($seasons as $s) {
                    // Make prediction request for this year/season/metric
                    $predictionResult = $this->makePredictionRequest($year, $areas, $s, $metric);
                    
                    if($predictionResult !== null) {
                        foreach($predictionResult as $areaId => $value) {
                            $item = array(
                                "id" => null,
                                "area_id" => intval($areaId),
                                "year" => $year,
                                "season" => $s,
                                $metric => floatval($value),
                                "is_prediction" => true
                            );
                            array_push($result["data"], $item);
                        }
                    }
                }
            }

            return $result;
        } catch(Exception $e) {
            error_log("Error fetching predicted data: " . $e->getMessage());
            return array("data" => array());
        }
    }

    /**
     * Make a prediction request to Flask
     */
    private function makePredictionRequest($year, $areas, $season, $metric) {
        try {
            // Scale values using z-score normalization
            $yearScaled = $this->scaleValue($year, 2000, 25);
            $areaIdsScaled = array_map(function($id) {
                return $this->scaleValue($id, 16, 9);
            }, $areas);

            // Prepare payload
            $payload = array(
                "year_scaled" => floatval($yearScaled),
                "area_ids_scaled" => $areaIdsScaled,
                "season" => $season,
                "metric" => $metric
            );

            // Call Flask prediction endpoint
            $ch = curl_init($this->flaskUrl . "/predict-batch");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if($response === false || $httpCode !== 200) {
                error_log("Prediction failed for year=$year, season=$season, metric=$metric. HTTP Code: $httpCode");
                return null;
            }

            $predictions = json_decode($response, true);

            if(!isset($predictions['area_predictions']) || !is_array($predictions['area_predictions'])) {
                error_log("Invalid prediction response format for year=$year");
                return null;
            }

            // Map back to area IDs
            $result = array();
            foreach($predictions['area_predictions'] as $idx => $areaPred) {
                if(isset($areas[$idx]) && isset($areaPred['prediction'])) {
                    $result[$areas[$idx]] = $areaPred['prediction'];
                }
            }

            return $result;
        } catch(Exception $e) {
            error_log("Error making prediction request: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Z-score normalization: (value - mean) / std
     */
    private function scaleValue($value, $mean, $std) {
        if($std == 0) return 0;
        return ($value - $mean) / $std;
    }
}
?>
