CREATE DATABASE IF NOT EXISTS i_am_so_weary;
USE i_am_so_weary;

CREATE TABLE Suppliers (
    supplier_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    organization_name VARCHAR(100) UNIQUE NOT NULL,
    director VARCHAR(50) NOT NULL,
    address VARCHAR(200) NOT NULL,
    supplied_chips VARCHAR(500),
    contact_person VARCHAR(50) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    INDEX idx_organization_name (organization_name),
    INDEX idx_director (director)
);

CREATE TABLE Microchips (
    chip_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    chip_type VARCHAR(50) UNIQUE NOT NULL,
    chip_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

CREATE TABLE Orders (
    order_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    order_date DATE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    fulfillment_date DATE,
    employee_id INTEGER
);

CREATE TABLE Receipts (
    receipt_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    receipt_invoice VARCHAR(100) UNIQUE NOT NULL,
    supplier_id INTEGER NOT NULL,
    receipt_date DATE NOT NULL,
    chip_type VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INTEGER NOT NULL,
    total_cost DECIMAL(12,2) AS (price * quantity),
    FOREIGN KEY (supplier_id) REFERENCES Suppliers(supplier_id),
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_chip_type (chip_type)
);

CREATE TABLE Shipments (
    shipment_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    shipment_invoice VARCHAR(100) UNIQUE NOT NULL,
    order_id INTEGER NOT NULL,
    shipment_date DATE NOT NULL,
    chip_type VARCHAR(50) NOT NULL,
    quantity INTEGER NOT NULL,
    FOREIGN KEY (order_id) REFERENCES Orders(order_id),
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type),
    INDEX idx_order_id (order_id),
    INDEX idx_chip_type (chip_type)
);

CREATE TABLE Ordered_Chips (
    order_id INTEGER,
    chip_type VARCHAR(50),
    quantity INTEGER,
    FOREIGN KEY (order_id) REFERENCES Orders(order_id),
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type)
);

INSERT INTO Suppliers (organization_name, director, address, supplied_chips, contact_person, phone, email) VALUES 
('ElectronComponents Ltd', 'Ivan Petrov', 'Moscow, Lenina st. 25', 'ATMega328, ESP32', 'Maria Sidorova', '+7(495)123-45-67', 'info@electron.ru'),
('MicroSystems Inc', 'Alexey Kozlov', 'SPb, Nevsky pr. 100', 'Arduino, Raspberry Pi', 'Anna Ivanova', '+7(812)987-65-43', 'sales@microsys.ru');

INSERT INTO Microchips (chip_type, chip_name, price) VALUES 
('ATMega328', 'ATMega328P-PU', 150.50),
('ESP32', 'ESP32-WROOM-32', 320.75),
('STM32', 'STM32F103C8T6', 280.00);

INSERT INTO Orders (order_number, order_date, customer_name) VALUES 
('ORD-001', '2024-01-10', 'TechCompany LLC'),
('ORD-002', '2024-01-12', 'Innovation Systems');

INSERT INTO Receipts (receipt_invoice, supplier_id, receipt_date, chip_type, price, quantity) VALUES 
('INV-001', 1, '2024-01-15', 'ATMega328', 150.50, 100),
('INV-002', 1, '2024-01-20', 'ESP32', 320.75, 50);

INSERT INTO Shipments (shipment_invoice, order_id, shipment_date, chip_type, quantity) VALUES 
('SHIP-001', 1, '2024-01-16', 'ATMega328', 10),
('SHIP-002', 1, '2024-01-18', 'ESP32', 5);

INSERT INTO Ordered_Chips VALUES 
(1, 'ATMega328', 25),
(1, 'ESP32', 10),
(2, 'ATMega328', 50);

SELECT chip_type, price FROM Microchips;

SELECT chip_type, price 
FROM Microchips 
WHERE price BETWEEN 100 AND 500 
ORDER BY price DESC;

SELECT chip_type, chip_name 
FROM Microchips 
WHERE chip_name LIKE '%ESP%';

SELECT 
    chip_name,
    price AS price_rub,
    price / 90 AS price_usd,
    price / 100 AS price_eur,
    price / 12 AS price_cny
FROM Microchips 
ORDER BY price / 90 ASC;

SELECT * 
FROM Orders 
WHERE fulfillment_date IS NULL 
ORDER BY order_date ASC;

SELECT 
    employee_id,
    COUNT(*) AS unfulfilled_orders_count
FROM Orders 
WHERE fulfillment_date IS NULL 
GROUP BY employee_id 
ORDER BY unfulfilled_orders_count DESC;

-- 18. 
-- TRUNCATE TABLE Shipments;
-- TRUNCATE TABLE Receipts;
-- TRUNCATE TABLE Suppliers;

INSERT INTO Microchips (chip_type, chip_name, price) VALUES 
('NewChip', 'New Microchip', 199.99);

INSERT INTO Receipts (receipt_invoice, supplier_id, receipt_date, chip_type, price, quantity) VALUES 
('INV-100', 1, CURDATE(), 'ATMega328', 155.00, 50);

UPDATE Ordered_Chips 
SET quantity = 30 
WHERE order_id = 1 AND chip_type = 'ATMega328';

DELETE FROM Orders WHERE order_id = 2;
