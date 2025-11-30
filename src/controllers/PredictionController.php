<?php
class PredictionController {
    private $db;
    private $flaskUrl = "http://localhost:5000";
    private $envData;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->envData = new EnvironmentalData($this->db);
    }

    // GET - Route prediction requests
    public function handleGet() {
        if(isset($_GET['health'])) {
            $this->checkHealth();
        } else if(isset($_GET['batch'])) {
            $this->getPredictionBatch($_GET['year'], $_GET['season']);
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Invalid prediction request. Use ?batch=1&year=XXXX&season=XXXX or ?health=1"));
        }
    }
    
    // Check if Flask is reachable
    private function checkHealth() {
        try {
            $ch = curl_init($this->flaskUrl . "/health");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if($response === false) {
                http_response_code(500);
                echo json_encode(array(
                    "status" => "flask_unavailable",
                    "flask_url" => $this->flaskUrl,
                    "error" => $curlError
                ));
                return;
            }
            
            if($httpCode === 200) {
                $data = json_decode($response, true);
                http_response_code(200);
                echo json_encode(array(
                    "status" => "healthy",
                    "flask" => $data
                ));
            } else {
                http_response_code(500);
                echo json_encode(array(
                    "status" => "flask_error",
                    "http_code" => $httpCode,
                    "response" => $response
                ));
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(array(
                "status" => "error",
                "error" => $e->getMessage()
            ));
        }
    }

    // POST - Single prediction
    public function handlePost() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if(isset($data['metric']) && isset($data['features'])) {
            $this->predictSingle($data['metric'], $data['features']);
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Missing metric or features"));
        }
    }

    /**
     * Get predictions for all areas in one season/year
     * Query: ?batch=1&year=2024&season=winter
     */
    private function getPredictionBatch($year, $season) {
        try {
            // Validate year and season parameters
            if(!isset($year) || !isset($season)) {
                http_response_code(400);
                echo json_encode(array("message" => "Missing year or season parameter"));
                return;
            }
            
            $year = intval($year);
            $season = strtolower($season);
            
            // Validate season
            $valid_seasons = array("winter", "spring", "summer", "autumn");
            if(!in_array($season, $valid_seasons)) {
                http_response_code(400);
                echo json_encode(array("message" => "Invalid season. Valid options: " . implode(", ", $valid_seasons)));
                return;
            }
            
            // Get all unique areas from historical data
            $stmt = $this->db->prepare("SELECT DISTINCT area_id FROM environmental_data ORDER BY area_id");
            $stmt->execute();
            $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if(empty($areas)) {
                http_response_code(404);
                echo json_encode(array("message" => "No areas found in database"));
                return;
            }

            // Scale values using z-score normalization
            // These means and stds should match the training data scaler
            $yearScaled = $this->scaleValue($year, 2000, 25);
            $areaIdsScaled = array_map(function($id) {
                return $this->scaleValue($id, 16, 9);
            }, $areas);
            
            // Prepare payload for Flask batch prediction endpoint
            $payload = array(
                "year_scaled" => floatval($yearScaled),
                "area_ids_scaled" => $areaIdsScaled,
                "season" => $season
            );
            
            // Log the request for debugging
            error_log("Sending prediction request to Flask: " . json_encode($payload));
            
            // Call Flask batch prediction endpoint
            $ch = curl_init($this->flaskUrl . "/predict-batch");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Log the response for debugging
            error_log("Flask response (HTTP {$httpCode}): {$response}");
            
            // Check for curl errors
            if($response === false) {
                http_response_code(500);
                echo json_encode(array(
                    "message" => "Failed to connect to prediction service at {$this->flaskUrl}/predict-batch",
                    "error" => $curlError,
                    "flask_url" => $this->flaskUrl
                ));
                return;
            }
            
            if($httpCode === 200) {
                $predictions = json_decode($response, true);
                
                // Verify the response structure from Flask
                if(!isset($predictions['area_predictions']) || !is_array($predictions['area_predictions'])) {
                    http_response_code(500);
                    echo json_encode(array(
                        "message" => "Invalid response format from prediction service",
                        "received" => $predictions
                    ));
                    return;
                }
                
                // Map back to original area_ids
                $result = array(
                    "year" => $year,
                    "season" => $season,
                    "data" => array()
                );
                
                foreach($predictions['area_predictions'] as $idx => $areaPred) {
                    if(isset($areas[$idx]) && isset($areaPred['predictions'])) {
                        $areaId = $areas[$idx];
                        $result["data"][$areaId] = $areaPred['predictions'];
                    }
                }
                
                http_response_code(200);
                echo json_encode($result);
            } else {
                // Flask returned an error
                $errorResponse = json_decode($response, true);
                http_response_code(500);
                echo json_encode(array(
                    "message" => "Prediction service returned error",
                    "http_code" => $httpCode,
                    "flask_error" => isset($errorResponse['error']) ? $errorResponse['error'] : $response
                ));
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(array(
                "message" => "Error getting predictions",
                "error" => $e->getMessage()
            ));
        }
    }

    /**
     * Get prediction for a single record
     * POST with: { "metric": "ndvi", "features": [val1, val2, ...] }
     */
    private function predictSingle($metric, $features) {
        try {
            $validMetrics = array('ndvi', 'evi', 'ndwi', 'temp');
            
            if(!in_array($metric, $validMetrics)) {
                http_response_code(400);
                echo json_encode(array("message" => "Invalid metric"));
                return;
            }
            
            $payload = array("features" => $features);
            
            $ch = curl_init($this->flaskUrl . "/predict/" . $metric);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            http_response_code($httpCode);
            echo $response;
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(array("message" => "Error making prediction", "error" => $e->getMessage()));
        }
    }

    /**
     * Simple z-score scaling: (value - mean) / std
     */
    private function scaleValue($value, $mean, $std) {
        return ($value - $mean) / $std;
    }
}
?>
