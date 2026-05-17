-- Destinations table with distances from ISU main campus
CREATE TABLE IF NOT EXISTS destinations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    destination_name VARCHAR(255) NOT NULL,
    distance_km DECIMAL(10,2) NOT NULL COMMENT 'One-way distance in kilometers',
    estimated_fuel_liters DECIMAL(10,2) DEFAULT 0 COMMENT 'Estimated fuel for round trip',
    estimated_oil_liters DECIMAL(10,2) DEFAULT 0 COMMENT 'Estimated lubricating oil',
    estimated_gear_oil_liters DECIMAL(10,2) DEFAULT 0 COMMENT 'Estimated gear oil',
    estimated_grease_grams DECIMAL(10,2) DEFAULT 0 COMMENT 'Estimated grease in grams',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert common destinations around Isabela
INSERT INTO destinations (destination_name, distance_km, estimated_fuel_liters, estimated_oil_liters, estimated_gear_oil_liters, estimated_grease_grams) VALUES
('ISU-Echague Campus', 25, 5.0, 0.5, 0.2, 50),
('ISU-Cabagan Campus', 35, 7.0, 0.5, 0.3, 75),
('ISU-Cauayan Campus', 45, 9.0, 0.8, 0.3, 100),
('ISU-San Mariano Campus', 60, 12.0, 1.0, 0.5, 150),
('ISU-Angadanan Campus', 40, 8.0, 0.7, 0.3, 80),
('ISU-Jones Campus', 30, 6.0, 0.5, 0.2, 60),
('Ilagan City Hall', 5, 1.0, 0.1, 0.1, 20),
('Santiago City', 50, 10.0, 0.8, 0.4, 120),
('Tuguegarao City', 80, 16.0, 1.2, 0.6, 200),
('Manila', 350, 70.0, 5.0, 2.0, 500),
('Baguio City', 200, 40.0, 3.0, 1.5, 350),
('Provincial Capitol', 8, 1.6, 0.2, 0.1, 25),
('CHED Regional Office', 10, 2.0, 0.2, 0.1, 30),
('DepEd Division Office', 7, 1.4, 0.1, 0.1, 20),
('DOH Regional Office', 12, 2.4, 0.3, 0.1, 35);
