<?php
class EnvironmentalData {
    private $conn;
    private $table_name = "environmental_data";

    public $id;
    public $area_id;
    public $year;
    public $season;
    public $ndvi;
    public $evi;
    public $ndwi;
    public $temp;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all environmental data
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY year DESC, season";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get single environmental record by ID
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->area_id = $row['area_id'];
            $this->year = $row['year'];
            $this->season = $row['season'];
            $this->ndvi = $row['ndvi'];
            $this->evi = $row['evi'];
            $this->ndwi = $row['ndwi'];
            $this->temp = $row['temp'];
            return true;
        }
        return false;
    }

    // Create new environmental record
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 SET area_id=:area_id, year=:year, season=:season, ndvi=:ndvi, evi=:evi, ndwi=:ndwi, temp=:temp";

        $stmt = $this->conn->prepare($query);

        // Sanitize data
        $this->area_id = htmlspecialchars(strip_tags($this->area_id));
        $this->year = htmlspecialchars(strip_tags($this->year));
        $this->season = htmlspecialchars(strip_tags($this->season));
        $this->ndvi = htmlspecialchars(strip_tags($this->ndvi));
        $this->evi = htmlspecialchars(strip_tags($this->evi));
        $this->ndwi = htmlspecialchars(strip_tags($this->ndwi));
        $this->temp = htmlspecialchars(strip_tags($this->temp));

        // Bind parameters
        $stmt->bindParam(":area_id", $this->area_id);
        $stmt->bindParam(":year", $this->year);
        $stmt->bindParam(":season", $this->season);
        $stmt->bindParam(":ndvi", $this->ndvi);
        $stmt->bindParam(":evi", $this->evi);
        $stmt->bindParam(":ndwi", $this->ndwi);
        $stmt->bindParam(":temp", $this->temp);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update environmental record
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                 SET area_id=:area_id, year=:year, season=:season, ndvi=:ndvi, evi=:evi, ndwi=:ndwi, temp=:temp
                 WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Sanitize data
        $this->area_id = htmlspecialchars(strip_tags($this->area_id));
        $this->year = htmlspecialchars(strip_tags($this->year));
        $this->season = htmlspecialchars(strip_tags($this->season));
        $this->ndvi = htmlspecialchars(strip_tags($this->ndvi));
        $this->evi = htmlspecialchars(strip_tags($this->evi));
        $this->ndwi = htmlspecialchars(strip_tags($this->ndwi));
        $this->temp = htmlspecialchars(strip_tags($this->temp));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Bind parameters
        $stmt->bindParam(":area_id", $this->area_id);
        $stmt->bindParam(":year", $this->year);
        $stmt->bindParam(":season", $this->season);
        $stmt->bindParam(":ndvi", $this->ndvi);
        $stmt->bindParam(":evi", $this->evi);
        $stmt->bindParam(":ndwi", $this->ndwi);
        $stmt->bindParam(":temp", $this->temp);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete environmental record
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Get environmental data by area_id
    public function readByArea($area_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE area_id = ? ORDER BY year DESC, season";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $area_id);
        $stmt->execute();
        return $stmt;
    }

    // Get environmental data by year and season
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