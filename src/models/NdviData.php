<?php
class NdviData {
    private $conn;
    private $table_name = "ndvi_data";

    public $id;
    public $avg_ndvi;
    public $season;
    public $year;
    public $area_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all NDVI data
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY year DESC, season";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get single NDVI record by ID
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->avg_ndvi = $row['avg_ndvi'];
            $this->season = $row['season'];
            $this->year = $row['year'];
            $this->area_id = $row['area_id'];
            return true;
        }
        return false;
    }

    // Create new NDVI record
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 SET avg_ndvi=:avg_ndvi, season=:season, year=:year, area_id=:area_id";

        $stmt = $this->conn->prepare($query);

        // Sanitize data
        $this->avg_ndvi = htmlspecialchars(strip_tags($this->avg_ndvi));
        $this->season = htmlspecialchars(strip_tags($this->season));
        $this->year = htmlspecialchars(strip_tags($this->year));
        $this->area_id = htmlspecialchars(strip_tags($this->area_id));

        // Bind parameters
        $stmt->bindParam(":avg_ndvi", $this->avg_ndvi);
        $stmt->bindParam(":season", $this->season);
        $stmt->bindParam(":year", $this->year);
        $stmt->bindParam(":area_id", $this->area_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update NDVI record
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                 SET avg_ndvi=:avg_ndvi, season=:season, year=:year, area_id=:area_id
                 WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Sanitize data
        $this->avg_ndvi = htmlspecialchars(strip_tags($this->avg_ndvi));
        $this->season = htmlspecialchars(strip_tags($this->season));
        $this->year = htmlspecialchars(strip_tags($this->year));
        $this->area_id = htmlspecialchars(strip_tags($this->area_id));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Bind parameters
        $stmt->bindParam(":avg_ndvi", $this->avg_ndvi);
        $stmt->bindParam(":season", $this->season);
        $stmt->bindParam(":year", $this->year);
        $stmt->bindParam(":area_id", $this->area_id);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete NDVI record
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Get NDVI data by area_id
    public function readByArea($area_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE area_id = ? ORDER BY year DESC, season";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $area_id);
        $stmt->execute();
        return $stmt;
    }

    // Get NDVI data by year and season
    public function readByYearSeason($year, $season) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE year = ? AND season = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $year);
        $stmt->bindParam(2, $season);
        $stmt->execute();
        return $stmt;
    }
}
?>