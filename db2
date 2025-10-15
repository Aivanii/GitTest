CREATE DATABASE IF NOT EXISTS i_am_so_weary;
USE i_am_so_weary;

-- Task 1: Create Suppliers table
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
    INDEX idx_director (director),
    CONSTRAINT chk_phone CHECK (phone REGEXP '^\\+7\\([0-9]{3}\\)[0-9]{3}-[0-9]{2}-[0-9]{2}$')
);

-- Task 2: Add index for director (already included above)

-- Task 3: Add email constraint
ALTER TABLE Suppliers 
ADD CONSTRAINT chk_email 
CHECK (email REGEXP '^[^@]+@[^@]+\\.[^@]{2,}$');

-- Task 4: Test data
INSERT INTO Suppliers (organization_name, director, address, supplied_chips, contact_person, phone, email)
VALUES 
('ElectronComponents Ltd', 'Ivan Petrov', 'Moscow, Lenina st. 25', 'ATMega328, ESP32', 'Maria Sidorova', '+7(495)123-45-67', 'info@electron.ru'),
('MicroSystems Inc', 'Alexey Kozlov', 'SPb, Nevsky pr. 100', 'Arduino, Raspberry Pi', 'Anna Ivanova', '+7(812)987-65-43', 'sales@microsys.ru');

-- Create Microchips table for foreign keys
CREATE TABLE Microchips (
    chip_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    chip_type VARCHAR(50) UNIQUE NOT NULL,
    chip_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

INSERT INTO Microchips (chip_type, chip_name, price) VALUES 
('ATMega328', 'ATMega328P-PU', 150.50),
('ESP32', 'ESP32-WROOM-32', 320.75);

-- Task 5: Create Receipts table
CREATE TABLE Receipts (
    receipt_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    receipt_invoice VARCHAR(100) UNIQUE NOT NULL,
    supplier_id INTEGER NOT NULL,
    receipt_date DATE NOT NULL,
    chip_type VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INTEGER NOT NULL,
    FOREIGN KEY (supplier_id) REFERENCES Suppliers(supplier_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_chip_type (chip_type)
);

-- Task 6: Add constraints
ALTER TABLE Receipts 
ADD CONSTRAINT chk_positive_price CHECK (price > 0),
ADD CONSTRAINT chk_positive_quantity CHECK (quantity > 0);

-- Task 7: Add calculated field
ALTER TABLE Receipts 
ADD COLUMN total_cost DECIMAL(12,2) 
AS (price * quantity);

-- Task 8: Test Receipts data
INSERT INTO Receipts (receipt_invoice, supplier_id, receipt_date, chip_type, price, quantity)
VALUES 
('INV-001', 1, '2024-01-15', 'ATMega328', 150.50, 100),
('INV-002', 1, '2024-01-20', 'ESP32', 320.75, 50),
('INV-003', 2, '2024-01-25', 'ATMega328', 145.00, 200);

-- Create Orders table for Shipments
CREATE TABLE Orders (
    order_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    order_date DATE NOT NULL,
    customer_name VARCHAR(100) NOT NULL
);

INSERT INTO Orders (order_number, order_date, customer_name) VALUES 
('ORD-001', '2024-01-10', 'TechCompany LLC'),
('ORD-002', '2024-01-12', 'Innovation Systems');

-- Task 9: Create Shipments table
CREATE TABLE Shipments (
    shipment_id INTEGER PRIMARY KEY,
    shipment_invoice VARCHAR(100) NOT NULL,
    order_id INTEGER NOT NULL,
    shipment_date DATE NOT NULL,
    chip_type VARCHAR(50) NOT NULL,
    quantity INTEGER NOT NULL,
    CONSTRAINT chk_shipment_quantity CHECK (quantity > 0),
    FOREIGN KEY (order_id) REFERENCES Orders(order_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_chip_type (chip_type)
);

-- Task 10: Modify Shipments
ALTER TABLE Shipments 
MODIFY COLUMN shipment_id INTEGER AUTO_INCREMENT,
MODIFY COLUMN shipment_invoice VARCHAR(100) UNIQUE NOT NULL;

-- Task 11: Test Shipments data
INSERT INTO Shipments (shipment_invoice, order_id, shipment_date, chip_type, quantity)
VALUES 
('SHIP-001', 1, '2024-01-16', 'ATMega328', 10),
('SHIP-002', 1, '2024-01-18', 'ESP32', 5),
('SHIP-003', 2, '2024-01-20', 'ATMega328', 15);

-- Task 12: Microchip Prices query
SELECT chip_type, price FROM Microchips;

-- Task 13: Filtered prices
SELECT chip_type, price 
FROM Microchips 
WHERE price BETWEEN 100 AND 500 
ORDER BY price DESC;

-- Task 14: Chip specifications with parameter
SELECT chip_type, chip_name 
FROM Microchips 
WHERE chip_name LIKE CONCAT('%', ? , '%');

-- Task 15: Price conversion
SELECT 
    chip_name,
    price AS price_rub,
    price / ? AS price_usd,
    price / ? AS price_eur,
    price / ? AS price_cny
FROM Microchips 
ORDER BY price_usd ASC;

-- Add fulfillment_date for tasks 16-17
ALTER TABLE Orders ADD COLUMN fulfillment_date DATE;
ALTER TABLE Orders ADD COLUMN employee_id INTEGER;

-- Task 16: Unfulfilled orders
SELECT * 
FROM Orders 
WHERE fulfillment_date IS NULL 
ORDER BY order_date ASC;

-- Task 17: Unfulfilled orders count
SELECT 
    employee_id,
    COUNT(*) AS unfulfilled_orders_count
FROM Orders 
WHERE fulfillment_date IS NULL 
GROUP BY employee_id 
ORDER BY unfulfilled_orders_count DESC;

-- Task 18: TRUNCATE tables
TRUNCATE TABLE Shipments;
TRUNCATE TABLE Receipts;
TRUNCATE TABLE Suppliers;

-- Task 19: Various insert methods
INSERT INTO Microchips (chip_id, chip_type, chip_name, price) 
VALUES (10, 'STM32', 'STM32F103', 280.00);

INSERT INTO Suppliers (organization_name, director, address, contact_person, phone, email)
VALUES ('NewSupplier', 'John Smith', 'New York', 'Mike Brown', '+7(495)555-66-77', 'new@supplier.com');

INSERT INTO Receipts (receipt_invoice, supplier_id, receipt_date, chip_type, price, quantity)
VALUES ('INV-100', 1, CURDATE(), 'ATMega328', 155.00, 50);

-- Create Ordered_Chips table for task 20
CREATE TABLE Ordered_Chips (
    order_id INTEGER,
    chip_type VARCHAR(50),
    quantity INTEGER,
    FOREIGN KEY (order_id) REFERENCES Orders(order_id),
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type)
);

INSERT INTO Ordered_Chips VALUES 
(1, 'ATMega328', 25),
(1, 'ESP32', 10),
(2, 'ATMega328', 50);

-- Task 20: Update ordered chips
UPDATE Ordered_Chips 
SET quantity = ? 
WHERE order_id = ? AND chip_type = ?;

-- Task 21: Delete order
DELETE FROM Orders WHERE order_id = ?;
