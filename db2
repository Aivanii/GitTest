CREATE DATABASE IF NOT EXISTS i_am_so_weary;
USE i_am_so_weary;

CREATE TABLE countries (
    country_id INT PRIMARY KEY AUTO_INCREMENT,
    country_name VARCHAR(50) UNIQUE NOT NULL,
    country_code VARCHAR(3) UNIQUE NOT NULL,
    currency VARCHAR(3) NOT NULL,
    INDEX idx_country_name (country_name)
);

CREATE TABLE organization_types (
    org_type_id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(20) UNIQUE NOT NULL,
    description VARCHAR(100)
);

CREATE TABLE currencies (
    currency_id INT PRIMARY KEY AUTO_INCREMENT,
    currency_code VARCHAR(3) UNIQUE NOT NULL,
    currency_name VARCHAR(50) NOT NULL,
    symbol VARCHAR(5)
);

CREATE TABLE exchange_rates (
    rate_id INT PRIMARY KEY AUTO_INCREMENT,
    currency_id INT NOT NULL,
    rate_date DATE NOT NULL,
    rate_to_rub DECIMAL(10,4) NOT NULL,
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    INDEX idx_rate_date (rate_date),
    UNIQUE KEY unique_currency_date (currency_id, rate_date)
);

CREATE TABLE employees (
    employee_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(15) NOT NULL,
    surname VARCHAR(20) NOT NULL,
    patronymic VARCHAR(20),
    position VARCHAR(50) NOT NULL,
    department VARCHAR(50),
    hire_date DATE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(60) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_employee_name (surname, name),
    INDEX idx_department (department)
);

CREATE TABLE Suppliers (
    supplier_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    organization_name VARCHAR(100) UNIQUE NOT NULL,
    director VARCHAR(50) NOT NULL,
    address VARCHAR(200) NOT NULL,
    supplied_chips VARCHAR(500),
    contact_person VARCHAR(50) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    legal_name VARCHAR(100),
    org_type_id INT,
    tax_id VARCHAR(20),
    country_id INT,
    address_legal VARCHAR(200) NOT NULL,
    address_actual VARCHAR(200),
    bank_details TEXT,
    quality_rating DECIMAL(3,2) DEFAULT 5.0,
    reliability_rating DECIMAL(3,2) DEFAULT 5.0,
    performance_rating DECIMAL(3,2) DEFAULT 5.0,
    last_delivery_date DATE,
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (org_type_id) REFERENCES organization_types(org_type_id),
    FOREIGN KEY (country_id) REFERENCES countries(country_id),
    INDEX idx_organization_name (organization_name),
    INDEX idx_director (director),
    INDEX idx_country (country_id),
    INDEX idx_rating (quality_rating, reliability_rating),
    CONSTRAINT chk_phone CHECK (phone REGEXP '^\\+7\\([0-9]{3}\\)[0-9]{3}-[0-9]{2}-[0-9]{2}$'),
    CONSTRAINT chk_email_format CHECK (email REGEXP '^[^@]+@[^@]+\\.[^@]{2,}$')
);

CREATE TABLE supplier_contacts (
    contact_id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    name VARCHAR(15) NOT NULL,
    surname VARCHAR(20) NOT NULL,
    patronymic VARCHAR(20),
    position VARCHAR(50) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(60) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    notes TEXT,
    FOREIGN KEY (supplier_id) REFERENCES Suppliers(supplier_id) ON DELETE CASCADE,
    INDEX idx_supplier_contact (supplier_id, is_primary),
    UNIQUE KEY unique_contact_phone (supplier_id, phone)
);

CREATE TABLE chip_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) UNIQUE NOT NULL,
    parent_category_id INT NULL,
    description TEXT,
    FOREIGN KEY (parent_category_id) REFERENCES chip_categories(category_id),
    INDEX idx_category_name (category_name)
);

CREATE TABLE Microchips (
    chip_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    chip_type VARCHAR(50) UNIQUE NOT NULL,
    chip_name VARCHAR(100) NOT NULL,
    category_id INT,
    manufacturer VARCHAR(50),
    description TEXT,
    specifications JSON,
    datasheet_url VARCHAR(255),
    weight_grams DECIMAL(8,3),
    dimensions VARCHAR(50),
    price DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES chip_categories(category_id),
    INDEX idx_chip_type (chip_type),
    INDEX idx_manufacturer (manufacturer),
    INDEX idx_category (category_id)
);

CREATE TABLE supplier_chips (
    supplier_chip_id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    chip_id INT NOT NULL,
    supplier_part_number VARCHAR(50),
    base_price DECIMAL(10,2) NOT NULL,
    currency_id INT NOT NULL,
    min_order_quantity INT DEFAULT 1,
    delivery_days INT DEFAULT 14,
    is_preferred BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (supplier_id) REFERENCES Suppliers(supplier_id) ON DELETE CASCADE,
    FOREIGN KEY (chip_id) REFERENCES Microchips(chip_id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    UNIQUE KEY unique_supplier_chip (supplier_id, chip_id),
    INDEX idx_supplier_price (supplier_id, base_price)
);

CREATE TABLE Receipts (
    receipt_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    receipt_invoice VARCHAR(100) UNIQUE NOT NULL,
    supplier_id INTEGER NOT NULL,
    receipt_date DATE NOT NULL,
    receipt_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
    warehouse_location VARCHAR(50),
    received_by_employee INT,
    chip_type VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INTEGER NOT NULL,
    total_cost DECIMAL(12,2) AS (price * quantity),
    status ENUM('pending', 'received', 'verified', 'rejected') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (supplier_id) REFERENCES Suppliers(supplier_id),
    FOREIGN KEY (received_by_employee) REFERENCES employees(employee_id),
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type),
    INDEX idx_receipt_date (receipt_date),
    INDEX idx_supplier_receipt (supplier_id, receipt_date),
    INDEX idx_status (status),
    CONSTRAINT chk_positive_price CHECK (price > 0),
    CONSTRAINT chk_positive_quantity CHECK (quantity > 0)
);

CREATE TABLE receipt_items (
    receipt_item_id INT PRIMARY KEY AUTO_INCREMENT,
    receipt_id INT NOT NULL,
    chip_id INT NOT NULL,
    supplier_chip_id INT NOT NULL,
    batch_number VARCHAR(50),
    quantity INT NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10,2) NOT NULL CHECK (unit_price > 0),
    currency_id INT NOT NULL,
    price_in_rub DECIMAL(10,2),
    total_price DECIMAL(12,2),
    quality_check BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (receipt_id) REFERENCES Receipts(receipt_id) ON DELETE CASCADE,
    FOREIGN KEY (chip_id) REFERENCES Microchips(chip_id),
    FOREIGN KEY (supplier_chip_id) REFERENCES supplier_chips(supplier_chip_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    INDEX idx_receipt_chip (receipt_id, chip_id),
    INDEX idx_batch (batch_number)
);

CREATE TABLE Orders (
    order_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    order_date DATE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    fulfillment_date DATE,
    employee_id INTEGER,
    total_amount DECIMAL(12,2),
    status ENUM('pending', 'confirmed', 'in_progress', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    INDEX idx_order_date (order_date),
    INDEX idx_customer (customer_name),
    INDEX idx_status (status)
);

CREATE TABLE Order_Details (
    order_detail_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    order_id INTEGER NOT NULL,
    chip_type VARCHAR(50) NOT NULL,
    ordered_quantity INTEGER NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type)
);

CREATE TABLE Ordered_Chips (
    order_id INTEGER,
    chip_type VARCHAR(50),
    quantity INTEGER,
    FOREIGN KEY (order_id) REFERENCES Orders(order_id),
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type)
);

CREATE TABLE Shipments (
    shipment_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    shipment_invoice VARCHAR(100) UNIQUE NOT NULL,
    order_id INTEGER NOT NULL,
    shipment_date DATE NOT NULL,
    shipped_by_employee INT,
    carrier VARCHAR(50),
    tracking_number VARCHAR(100),
    shipping_cost DECIMAL(10,2),
    chip_type VARCHAR(50) NOT NULL,
    quantity INTEGER NOT NULL,
    status ENUM('preparing', 'shipped', 'in_transit', 'delivered') DEFAULT 'preparing',
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (shipped_by_employee) REFERENCES employees(employee_id),
    FOREIGN KEY (chip_type) REFERENCES Microchips(chip_type),
    INDEX idx_shipment_date (shipment_date),
    INDEX idx_order_shipment (order_id, shipment_date),
    INDEX idx_tracking (tracking_number),
    CONSTRAINT chk_shipment_quantity CHECK (quantity > 0)
);

CREATE TABLE shipment_items (
    shipment_item_id INT PRIMARY KEY AUTO_INCREMENT,
    shipment_id INT NOT NULL,
    chip_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10,2) NOT NULL,
    receipt_item_id INT,
    FOREIGN KEY (shipment_id) REFERENCES Shipments(shipment_id) ON DELETE CASCADE,
    FOREIGN KEY (chip_id) REFERENCES Microchips(chip_id),
    FOREIGN KEY (receipt_item_id) REFERENCES receipt_items(receipt_item_id),
    INDEX idx_shipment_chip (shipment_id, chip_id)
);

CREATE TABLE stock_balance (
    stock_id INT PRIMARY KEY AUTO_INCREMENT,
    chip_id INT NOT NULL,
    receipt_item_id INT NOT NULL,
    quantity_available INT NOT NULL DEFAULT 0,
    reserved_quantity INT NOT NULL DEFAULT 0,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chip_id) REFERENCES Microchips(chip_id),
    FOREIGN KEY (receipt_item_id) REFERENCES receipt_items(receipt_item_id),
    UNIQUE KEY unique_chip_receipt (chip_id, receipt_item_id),
    INDEX idx_chip_stock (chip_id, quantity_available)
);

CREATE VIEW Microchip_Prices_View AS
SELECT chip_type, chip_name, price
FROM Microchips
ORDER BY price DESC;

CREATE VIEW Supplier_Performance_View AS
SELECT 
    s.organization_name,
    COUNT(r.receipt_id) as total_receipts,
    SUM(r.quantity) as total_chips_received,
    AVG(s.quality_rating) as avg_quality_rating
FROM Suppliers s
LEFT JOIN Receipts r ON s.supplier_id = r.supplier_id
GROUP BY s.supplier_id, s.organization_name;

DELIMITER //
CREATE TRIGGER after_receipt_insert 
    AFTER INSERT ON receipt_items
    FOR EACH ROW
BEGIN
    INSERT INTO stock_balance (chip_id, receipt_item_id, quantity_available)
    VALUES (NEW.chip_id, NEW.receipt_item_id, NEW.quantity)
    ON DUPLICATE KEY UPDATE 
        quantity_available = quantity_available + NEW.quantity,
        last_updated = CURRENT_TIMESTAMP;
END//

CREATE TRIGGER after_shipment_insert
    AFTER INSERT ON shipment_items
    FOR EACH ROW
BEGIN
    UPDATE stock_balance 
    SET quantity_available = quantity_available - NEW.quantity,
        last_updated = CURRENT_TIMESTAMP
    WHERE receipt_item_id = NEW.receipt_item_id;
END//
DELIMITER ;

INSERT INTO Suppliers (organization_name, director, address, supplied_chips, contact_person, phone, email)
VALUES 
('ElectronComponents Ltd', 'Ivan Petrov', 'Moscow, Lenina st. 25', 'ATMega328, ESP32', 'Maria Sidorova', '+7(495)123-45-67', 'info@electron.ru'),
('MicroSystems Inc', 'Alexey Kozlov', 'SPb, Nevsky pr. 100', 'Arduino, Raspberry Pi', 'Anna Ivanova', '+7(812)987-65-43', 'sales@microsys.ru');

INSERT INTO Microchips (chip_type, chip_name, price, specifications) 
VALUES 
('ATMega328', 'ATMega328P-PU', 150.50, '8-bit AVR Microcontroller'),
('ESP32', 'ESP32-WROOM-32', 320.75, 'Wi-Fi + Bluetooth Module'),
('STM32', 'STM32F103C8T6', 280.00, 'ARM Cortex-M3 Microcontroller');

INSERT INTO Receipts (receipt_invoice, supplier_id, receipt_date, chip_type, price, quantity)
VALUES 
('INV-001', 1, '2024-01-15', 'ATMega328', 150.50, 100),
('INV-002', 1, '2024-01-20', 'ESP32', 320.75, 50),
('INV-003', 2, '2024-01-25', 'ATMega328', 145.00, 200);

INSERT INTO Orders (order_number, order_date, customer_name, employee_id)
VALUES 
('ORD-001', '2024-01-10', 'TechCompany LLC', 1),
('ORD-002', '2024-01-12', 'Innovation Systems', 2);

INSERT INTO Order_Details (order_id, chip_type, ordered_quantity, unit_price)
VALUES 
(1, 'ATMega328', 25, 150.00),
(1, 'ESP32', 10, 320.00),
(2, 'ATMega328', 50, 145.00);

INSERT INTO Shipments (shipment_invoice, order_id, shipment_date, chip_type, quantity)
VALUES 
('SHIP-001', 1, '2024-01-16', 'ATMega328', 10),
('SHIP-002', 1, '2024-01-18', 'ESP32', 5),
('SHIP-003', 2, '2024-01-20', 'ATMega328', 15);

UPDATE Suppliers 
SET performance_rating = 4.8, last_delivery_date = '2024-01-20'
WHERE supplier_id = 1;

UPDATE Suppliers 
SET performance_rating = 4.5, last_delivery_date = '2024-01-25'
WHERE supplier_id = 2;
