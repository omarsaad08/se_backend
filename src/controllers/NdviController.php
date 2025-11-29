<?php
class NdviController {
    private $db;
    private $ndviData;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->ndviData = new NdviData($this->db);
    }

    // GET - Get all NDVI data or specific records
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

    // Get all NDVI records
    public function getAll() {
        $stmt = $this->ndviData->read();
        $num = $stmt->rowCount();

        if($num > 0) {
            $ndvi_arr = array();
            $ndvi_arr["data"] = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $ndvi_item = array(
                    "id" => $id,
                    "avg_ndvi" => (float)$avg_ndvi,
                    "season" => $season,
                    "year" => (int)$year,
                    "area_id" => (int)$area_id
                );
                array_push($ndvi_arr["data"], $ndvi_item);
            }
            http_response_code(200);
            echo json_encode($ndvi_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "No NDVI data found."));
        }
    }

    // Get NDVI record by ID
    public function getById($id) {
        $this->ndviData->id = $id;
        
        if($this->ndviData->readOne()) {
            $ndvi_item = array(
                "id" => $this->ndviData->id,
                "avg_ndvi" => (float)$this->ndviData->avg_ndvi,
                "season" => $this->ndviData->season,
                "year" => (int)$this->ndviData->year,
                "area_id" => (int)$this->ndviData->area_id
            );
            http_response_code(200);
            echo json_encode($ndvi_item);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "NDVI data not found."));
        }
    }

    // Get NDVI data by area_id
    public function getByArea($area_id) {
        $stmt = $this->ndviData->readByArea($area_id);
        $num = $stmt->rowCount();

        if($num > 0) {
            $ndvi_arr = array();
            $ndvi_arr["data"] = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $ndvi_item = array(
                    "id" => $id,
                    "avg_ndvi" => (float)$avg_ndvi,
                    "season" => $season,
                    "year" => (int)$year,
                    "area_id" => (int)$area_id
                );
                array_push($ndvi_arr["data"], $ndvi_item);
            }
            http_response_code(200);
            echo json_encode($ndvi_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "No NDVI data found for area ID: " . $area_id));
        }
    }

    // Get NDVI data by year and season
    public function getByYearSeason($year, $season) {
        $stmt = $this->ndviData->readByYearSeason($year, $season);
        $num = $stmt->rowCount();

        if($num > 0) {
            $ndvi_arr = array();
            $ndvi_arr["data"] = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $ndvi_item = array(
                    "id" => $id,
                    "avg_ndvi" => (float)$avg_ndvi,
                    "season" => $season,
                    "year" => (int)$year,
                    "area_id" => (int)$area_id
                );
                array_push($ndvi_arr["data"], $ndvi_item);
            }
            http_response_code(200);
            echo json_encode($ndvi_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "No NDVI data found for " . $season . " " . $year));
        }
    }

    // POST - Create new NDVI record
    // public function handlePost() {
    //     $data = json_decode(file_get_contents("php://input"));

    //     if(!empty($data->avg_ndvi) && !empty($data->season) && !empty($data->year) && !empty($data->area_id)) {
    //         $this->ndviData->avg_ndvi = $data->avg_ndvi;
    //         $this->ndviData->season = $data->season;
    //         $this->ndviData->year = $data->year;
    //         $this->ndviData->area_id = $data->area_id;

    //         if($this->ndviData->create()) {
    //             http_response_code(201);
    //             echo json_encode(array("message" => "NDVI record was created."));
    //         } else {
    //             http_response_code(503);
    //             echo json_encode(array("message" => "Unable to create NDVI record."));
    //         }
    //     } else {
    //         http_response_code(400);
    //         echo json_encode(array("message" => "Unable to create NDVI record. Data is incomplete."));
    //     }
    // }

    // // PUT - Update NDVI record
    // public function handlePut() {
    //     $data = json_decode(file_get_contents("php://input"));

    //     if(!empty($data->id) && !empty($data->avg_ndvi) && !empty($data->season) && !empty($data->year) && !empty($data->area_id)) {
    //         $this->ndviData->id = $data->id;
    //         $this->ndviData->avg_ndvi = $data->avg_ndvi;
    //         $this->ndviData->season = $data->season;
    //         $this->ndviData->year = $data->year;
    //         $this->ndviData->area_id = $data->area_id;

    //         if($this->ndviData->update()) {
    //             http_response_code(200);
    //             echo json_encode(array("message" => "NDVI record was updated."));
    //         } else {
    //             http_response_code(503);
    //             echo json_encode(array("message" => "Unable to update NDVI record."));
    //         }
    //     } else {
    //         http_response_code(400);
    //         echo json_encode(array("message" => "Unable to update NDVI record. Data is incomplete."));
    //     }
    // }

    // // DELETE - Delete NDVI record
    // public function handleDelete() {
    //     $data = json_decode(file_get_contents("php://input"));

    //     if(!empty($data->id)) {
    //         $this->ndviData->id = $data->id;

    //         if($this->ndviData->delete()) {
    //             http_response_code(200);
    //             echo json_encode(array("message" => "NDVI record was deleted."));
    //         } else {
    //             http_response_code(503);
    //             echo json_encode(array("message" => "Unable to delete NDVI record."));
    //         }
    //     } else {
    //         http_response_code(400);
    //         echo json_encode(array("message" => "Unable to delete NDVI record. ID is missing."));
    //     }
    // }
}
?>