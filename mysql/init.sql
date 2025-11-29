CREATE TABLE ndvi_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    avg_ndvi DOUBLE,
    season VARCHAR(20),
    year INT,
    area_id INT
);

-- Load data from CSV
LOAD DATA INFILE 'NDVI_processed4.csv'
INTO TABLE ndvi_data
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(avg_ndvi, season, year, area_id);
