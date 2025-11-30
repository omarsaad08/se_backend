<?php
class EnvironmentalDataController {
    private $db;
    private $envData;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->envData = new EnvironmentalData($this->db);
    }

    // GET - Get all environmental data or specific records
    public function handleGet() {
        if(isset($_GET['area_id'])) {
            $this->getByArea($_GET['area_id']);
        } else if(isset($_GET['year']) && isset($_GET['season'])) {
            $this->getByYearSeason($_GET['year'], $_GET['season']);
        } else if(isset($_GET['id'])) {
            $this->getById($_GET['id']);
        } else {
            $this->getAll();
        }
    }

    // Filter single feature from result array
    private function filterFeature($data, $feature) {
        $valid_features = array('ndvi', 'evi', 'ndwi', 'temp');
        
        if(!in_array($feature, $valid_features)) {
            http_response_code(400);
            echo json_encode(array("message" => "Invalid feature. Valid features: " . implode(", ", $valid_features)));
            return;
        }

        if(isset($data["data"])) {
            foreach($data["data"] as &$item) {
                $feature_value = $item[$feature];
                $item = array(
                    "id" => $item["id"],
                    "area_id" => $item["area_id"],
                    "year" => $item["year"],
                    "season" => $item["season"],
                    $feature => $feature_value
                );
            }
        } else {
            $feature_value = $data[$feature];
            $data = array(
                "id" => $data["id"],
                "area_id" => $data["area_id"],
                "year" => $data["year"],
                "season" => $data["season"],
                $feature => $feature_value
            );
        }
        
        return $data;
    }

    // Get all environmental records
    public function getAll() {
        $stmt = $this->envData->read();
        $num = $stmt->rowCount();

        if($num > 0) {
            $env_arr = array();
            $env_arr["data"] = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $env_item = array(
                    "id" => $id,
                    "area_id" => (int)$area_id,
                    "year" => (int)$year,
                    "season" => $season,
                    "ndvi" => (float)$ndvi,
                    "evi" => (float)$evi,
                    "ndwi" => (float)$ndwi,
                    "temp" => (float)$temp
                );
                array_push($env_arr["data"], $env_item);
            }
            
            // Filter by feature if requested
            if(isset($_GET['feature'])) {
                $env_arr = $this->filterFeature($env_arr, $_GET['feature']);
            }
            
            http_response_code(200);
            echo json_encode($env_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "No environmental data found."));
        }
    }

    // Get environmental record by ID
    public function getById($id) {
        $this->envData->id = $id;
        
        if($this->envData->readOne()) {
            $env_item = array(
                "id" => $this->envData->id,
                "area_id" => (int)$this->envData->area_id,
                "year" => (int)$this->envData->year,
                "season" => $this->envData->season,
                "ndvi" => (float)$this->envData->ndvi,
                "evi" => (float)$this->envData->evi,
                "ndwi" => (float)$this->envData->ndwi,
                "temp" => (float)$this->envData->temp
            );
            
            // Filter by feature if requested
            if(isset($_GET['feature'])) {
                $env_item = $this->filterFeature($env_item, $_GET['feature']);
            }
            
            http_response_code(200);
            echo json_encode($env_item);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Environmental data not found."));
        }
    }

    // Get environmental data by area_id
    public function getByArea($area_id) {
        $stmt = $this->envData->readByArea($area_id);
        $num = $stmt->rowCount();

        if($num > 0) {
            $env_arr = array();
            $env_arr["data"] = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $env_item = array(
                    "id" => $id,
                    "area_id" => (int)$area_id,
                    "year" => (int)$year,
                    "season" => $season,
                    "ndvi" => (float)$ndvi,
                    "evi" => (float)$evi,
                    "ndwi" => (float)$ndwi,
                    "temp" => (float)$temp
                );
                array_push($env_arr["data"], $env_item);
            }
            
            // Filter by feature if requested
            if(isset($_GET['feature'])) {
                $env_arr = $this->filterFeature($env_arr, $_GET['feature']);
            }
            
            http_response_code(200);
            echo json_encode($env_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "No environmental data found for area ID: " . $area_id));
        }
    }

    // Get environmental data by year and season
    public function getByYearSeason($year, $season) {
        $stmt = $this->envData->readByYearSeason($year, $season);
        $num = $stmt->rowCount();

        if($num > 0) {
            $env_arr = array();
            $env_arr["data"] = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $env_item = array(
                    "id" => $id,
                    "area_id" => (int)$area_id,
                    "year" => (int)$year,
                    "season" => $season,
                    "ndvi" => (float)$ndvi,
                    "evi" => (float)$evi,
                    "ndwi" => (float)$ndwi,
                    "temp" => (float)$temp
                );
                array_push($env_arr["data"], $env_item);
            }
            
            // Filter by feature if requested
            if(isset($_GET['feature'])) {
                $env_arr = $this->filterFeature($env_arr, $_GET['feature']);
            }
            
            http_response_code(200);
            echo json_encode($env_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "No environmental data found for " . $season . " " . $year));
        }
    }

    // POST - Create new environmental record
    // public function handlePost() {
    //     $data = json_decode(file_get_contents("php://input"));

    //     if(!empty($data->area_id) && !empty($data->year) && !empty($data->season) && !empty($data->ndvi) && !empty($data->evi) && !empty($data->ndwi) && !empty($data->temp)) {
    //         $this->envData->area_id = $data->area_id;
    //         $this->envData->year = $data->year;
    //         $this->envData->season = $data->season;
    //         $this->envData->ndvi = $data->ndvi;
    //         $this->envData->evi = $data->evi;
    //         $this->envData->ndwi = $data->ndwi;
    //         $this->envData->temp = $data->temp;

    //         if($this->envData->create()) {
    //             http_response_code(201);
    //             echo json_encode(array("message" => "Environmental record was created."));
    //         } else {
    //             http_response_code(503);
    //             echo json_encode(array("message" => "Unable to create environmental record."));
    //         }
    //     } else {
    //         http_response_code(400);
    //         echo json_encode(array("message" => "Unable to create environmental record. Data is incomplete."));
    //     }
    // }

    // // PUT - Update environmental record
    // public function handlePut() {
    //     $data = json_decode(file_get_contents("php://input"));

    //     if(!empty($data->id) && !empty($data->area_id) && !empty($data->year) && !empty($data->season) && !empty($data->ndvi) && !empty($data->evi) && !empty($data->ndwi) && !empty($data->temp)) {
    //         $this->envData->id = $data->id;
    //         $this->envData->area_id = $data->area_id;
    //         $this->envData->year = $data->year;
    //         $this->envData->season = $data->season;
    //         $this->envData->ndvi = $data->ndvi;
    //         $this->envData->evi = $data->evi;
    //         $this->envData->ndwi = $data->ndwi;
    //         $this->envData->temp = $data->temp;

    //         if($this->envData->update()) {
    //             http_response_code(200);
    //             echo json_encode(array("message" => "Environmental record was updated."));
    //         } else {
    //             http_response_code(503);
    //             echo json_encode(array("message" => "Unable to update environmental record."));
    //         }
    //     } else {
    //         http_response_code(400);
    //         echo json_encode(array("message" => "Unable to update environmental record. Data is incomplete."));
    //     }
    // }

    // // DELETE - Delete environmental record
    // public function handleDelete() {
    //     $data = json_decode(file_get_contents("php://input"));

    //     if(!empty($data->id)) {
    //         $this->envData->id = $data->id;

    //         if($this->envData->delete()) {
    //             http_response_code(200);
    //             echo json_encode(array("message" => "Environmental record was deleted."));
    //         } else {
    //             http_response_code(503);
    //             echo json_encode(array("message" => "Unable to delete environmental record."));
    //         }
    //     } else {
    //         http_response_code(400);
    //         echo json_encode(array("message" => "Unable to delete environmental record. ID is missing."));
    //     }
    // }
}
?>