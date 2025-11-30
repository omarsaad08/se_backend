CREATE TABLE environmental_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT,
    year INT,
    season VARCHAR(20),
    ndvi DOUBLE,
    evi DOUBLE,
    ndwi DOUBLE,
    temp DOUBLE
);

-- Load data from CSV
LOAD DATA INFILE 'merged_environmental_data.csv'
INTO TABLE environmental_data
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(area_id, year, season, ndvi, evi, ndwi, temp);
