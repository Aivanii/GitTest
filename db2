SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS shipment;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS receipt;
DROP TABLE IF EXISTS supplier_chips;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS chips;
DROP TABLE IF EXISTS manager;
DROP TABLE IF EXISTS order_details;

START TRANSACTION;

CREATE TABLE manager (
    manager_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(15) NOT NULL,
    surname VARCHAR(20) NOT NULL,
    patronymic VARCHAR(20),
    email VARCHAR(50) NOT NULL UNIQUE,
    tel VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT chk_tel_format CHECK (tel REGEXP '^\\+7\\([0-9]{3}\\)[0-9]{3}-[0-9]{2}-[0-9]{2}$'),
    CONSTRAINT chk_email_format CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$')
) COMMENT 'Менеджеры компании';

CREATE TABLE chips (
    chip_id INT AUTO_INCREMENT PRIMARY KEY,
    chip_name VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(30) NOT NULL COMMENT 'Тип микросхемы',
    price DECIMAL(10,2) NOT NULL CHECK (price > 0),
    technical_specifications TEXT COMMENT 'Технические характеристики',
    created_date DATE DEFAULT (CURDATE()),
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_chip_type (type),
    INDEX idx_chip_price (price)
) COMMENT 'Каталог микросхем';

CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(70) NOT NULL UNIQUE,
    director VARCHAR(100) NOT NULL COMMENT 'ФИО директора',
    address VARCHAR(500) NOT NULL COMMENT 'Юридический адрес',
    contact_person VARCHAR(100) NOT NULL COMMENT 'Контактное лицо',
    phone VARCHAR(20) NOT NULL UNIQUE COMMENT 'Телефон',
    email VARCHAR(100) NOT NULL UNIQUE COMMENT 'Email',
    manager_id INT,
    rating TINYINT CHECK (rating BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_company_name (company_name),
    INDEX idx_director (director),
    INDEX idx_supplier_manager (manager_id),
    
    CONSTRAINT chk_supplier_email 
        CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'),
    CONSTRAINT chk_supplier_phone 
        CHECK (phone REGEXP '^\\+7\\([0-9]{3}\\)[0-9]{3}-[0-9]{2}-[0-9]{2}$'),
    
    CONSTRAINT fk_supplier_manager_id
        FOREIGN KEY (manager_id) REFERENCES manager (manager_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) COMMENT 'Поставщики микросхем';

CREATE TABLE supplier_chips (
    supplier_chip_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    chip_id INT NOT NULL,
    supply_price DECIMAL(10,2) NOT NULL CHECK (supply_price > 0),
    min_order_quantity INT DEFAULT 1 CHECK (min_order_quantity > 0),
    delivery_time_days INT DEFAULT 7 CHECK (delivery_time_days > 0),
    is_available BOOLEAN DEFAULT TRUE,
    
    UNIQUE KEY unique_supplier_chip (supplier_id, chip_id),
    
    CONSTRAINT fk_supplier_chips_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_supplier_chips_chip
        FOREIGN KEY (chip_id) REFERENCES chips(chip_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_chip_id (chip_id),
    INDEX idx_supply_price (supply_price)
) COMMENT 'Какие микросхемы поставляет каждый поставщик';

CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    order_date DATE NOT NULL,
    completion_date DATE NULL,
    employee_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20),
    customer_email VARCHAR(100),
    total_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('новый', 'в обработке', 'выполнен', 'отменен') DEFAULT 'новый',
    
    INDEX idx_order_date (order_date),
    INDEX idx_completion_date (completion_date),
    INDEX idx_customer_name (customer_name),
    INDEX idx_status (status),
    
    CONSTRAINT chk_order_dates CHECK (order_date <= IFNULL(completion_date, '9999-12-31')),
    CONSTRAINT chk_customer_phone CHECK (
        customer_phone IS NULL OR 
        customer_phone REGEXP '^\\+7\\([0-9]{3}\\)[0-9]{3}-[0-9]{2}-[0-9]{2}$'
    ),
    CONSTRAINT chk_customer_email CHECK (
        customer_email IS NULL OR 
        customer_email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'
    )
) COMMENT 'Заказы от клиентов';

CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    chip_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10,2) NOT NULL CHECK (unit_price > 0),
    total_price DECIMAL(15,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    
    INDEX idx_order_id (order_id),
    INDEX idx_chip_id (chip_id),
    
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_order_items_chip
        FOREIGN KEY (chip_id) REFERENCES chips(chip_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) COMMENT 'Позиции в заказах';

CREATE TABLE receipt (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_invoice VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    date DATE NOT NULL,
    chip_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL CHECK (price > 0),
    quantity INT NOT NULL CHECK (quantity > 0),
    total_cost DECIMAL(15,2) GENERATED ALWAYS AS (price * quantity) STORED,
    storage_location VARCHAR(50),
    
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_chip_id (chip_id),
    INDEX idx_date (date),
    INDEX idx_receipt_invoice (receipt_invoice),
    
    CONSTRAINT fk_receipt_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_receipt_chip
        FOREIGN KEY (chip_id) REFERENCES chips(chip_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) COMMENT 'Поступление товаров от поставщиков';

CREATE TABLE shipment (
    shipment_id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_invoice VARCHAR(50) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    date DATE NOT NULL,
    chip_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    shipped_by INT,
    notes TEXT,
    
    INDEX idx_order_id (order_id),
    INDEX idx_chip_id (chip_id),
    INDEX idx_shipment_date (date),
    INDEX idx_shipment_invoice (shipment_invoice),
    
    CONSTRAINT fk_shipment_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_shipment_chip
        FOREIGN KEY (chip_id) REFERENCES chips(chip_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_shipment_manager
        FOREIGN KEY (shipped_by) REFERENCES manager(manager_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) COMMENT 'Отгрузка товаров клиентам';

COMMIT;

DELIMITER $$

CREATE TRIGGER update_order_total
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders o
    SET o.total_amount = (
        SELECT SUM(total_price)
        FROM order_items
        WHERE order_id = NEW.order_id
    )
    WHERE o.order_id = NEW.order_id;
END$$

CREATE TRIGGER update_order_total_update
AFTER UPDATE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders o
    SET o.total_amount = (
        SELECT SUM(total_price)
        FROM order_items
        WHERE order_id = NEW.order_id
    )
    WHERE o.order_id = NEW.order_id;
END$$

CREATE TRIGGER update_order_total_delete
AFTER DELETE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders o
    SET o.total_amount = (
        SELECT COALESCE(SUM(total_price), 0)
        FROM order_items
        WHERE order_id = OLD.order_id
    )
    WHERE o.order_id = OLD.order_id;
END$$

DELIMITER ;

START TRANSACTION;

-- Менеджеры
INSERT INTO manager (name, surname, patronymic, email, tel) VALUES 
('Олег', 'Олегов', 'Олегович', 'oleg.olegov@company.com', '+7(123)456-78-90'),
('Анна', 'Иванова', 'Петровна', 'anna.ivanova@company.com', '+7(987)654-32-10'),
('Сергей', 'Петров', 'Сергеевич', 'sergey.petrov@company.com', '+7(495)123-45-67');

-- Микросхемы
INSERT INTO chips (chip_name, type, price, technical_specifications) VALUES 
('ATmega328P', 'Микроконтроллер', 150.50, '8-бит, 32KB Flash, 2KB RAM, 16 МГц'),
('ESP32-WROOM', 'Wi-Fi/Bluetooth модуль', 320.75, 'Двухъядерный, 240 МГц, 4MB Flash'),
('STM32F103C8T6', 'ARM микроконтроллер', 89.90, 'Cortex-M3, 72 МГц, 64KB Flash'),
('LM358', 'Операционный усилитель', 15.25, 'Двойной ОУ, питание 3-32V'),
('NE555', 'Таймер', 8.50, 'Таймер общего назначения'),
('ULN2003', 'Драйвер шагового двигателя', 45.30, '7 каналов, 500mA на канал');

-- Поставщики
INSERT INTO suppliers (company_name, director, address, contact_person, phone, email, manager_id, rating) VALUES 
('ООО "ЭлектронКомплект"', 'Антон Антонович Антонов', 'г. Москва, ул. Ленина, 15', 'Евгений Евгеньев', '+7(999)111-22-33', 'sales@electronkomplekt.ru', 1, 4),
('АО "МикроСхемы Сервис"', 'Дмитрий Игоревич Семенов', 'г. Санкт-Петербург, Невский пр-т, 100', 'Екатерина Воронова', '+7(812)333-22-11', 'info@microchips.ru', 2, 5),
('ИП "РадиоДеталь"', 'Игорь Васильевич Козлов', 'г. Новосибирск, пр. Карла Маркса, 50', 'Марина Соколова', '+7(383)444-55-66', 'order@radiodetal.ru', 1, 3);

-- Связь поставщиков и микросхем
INSERT INTO supplier_chips (supplier_id, chip_id, supply_price, min_order_quantity, delivery_time_days) VALUES
(1, 1, 120.00, 10, 3),
(1, 2, 280.00, 5, 5),
(1, 3, 75.00, 20, 2),
(2, 2, 290.00, 3, 7),
(2, 4, 12.00, 100, 1),
(2, 5, 6.50, 200, 1),
(3, 6, 38.00, 15, 4),
(3, 1, 125.00, 15, 5);

-- Заказы
INSERT INTO orders (order_date, completion_date, employee_id, customer_name, customer_phone, customer_email, status) VALUES
('2024-01-20', '2024-01-23', 1, 'ООО "ТехноПром"', '+7(495)111-22-33', 'order@technoprom.ru', 'выполнен'),
('2024-01-25', NULL, 2, 'ИП Сидоров А.В.', '+7(916)222-33-44', 'sidorov@mail.ru', 'в обработке'),
('2024-02-01', '2024-02-03', 1, 'ЗАО "Электроник"', '+7(812)333-44-55', 'sales@electronic.ru', 'выполнен'),
('2024-02-10', NULL, 3, 'ООО "Роботех"', '+7(495)444-55-66', 'info@robotech.ru', 'новый');

-- Позиции заказов (триггер автоматически обновит total_amount в orders)
INSERT INTO order_items (order_id, chip_id, quantity, unit_price) VALUES
(1, 1, 10, 150.00),
(1, 2, 5, 320.75),
(2, 3, 8, 89.90),
(2, 4, 20, 15.25),
(3, 1, 15, 145.00),
(3, 6, 5, 45.30),
(4, 2, 3, 315.00);

-- Поступления
INSERT INTO receipt (receipt_invoice, supplier_id, date, chip_id, price, quantity, storage_location) VALUES 
('ПРИХ-2024-001', 1, '2024-01-15', 1, 120.00, 100, 'Склад А, стеллаж 1'),
('ПРИХ-2024-002', 2, '2024-01-18', 2, 280.00, 50, 'Склад А, стеллаж 2'),
('ПРИХ-2024-003', 3, '2024-01-20', 6, 38.00, 80, 'Склад Б, стеллаж 3');

-- Отгрузки
INSERT INTO shipment (shipment_invoice, order_id, date, chip_id, quantity, shipped_by, notes) VALUES 
('РАСХ-2024-001', 1, '2024-01-23', 1, 10, 1, 'Отгружено курьерской службой'),
('РАСХ-2024-002', 1, '2024-01-23', 2, 5, 1, 'Отгружено курьерской службой'),
('РАСХ-2024-003', 3, '2024-02-03', 1, 15, 2, 'Самовывоз'),
('РАСХ-2024-004', 3, '2024-02-03', 6, 5, 2, 'Самовывоз');

COMMIT;

-- =============================================
-- 5. ПРЕДСТАВЛЕНИЯ (VIEWS) ДЛЯ ЧАСТЫХ ЗАПРОСОВ
-- =============================================
-- Представление для просмотра остатков на складе
CREATE VIEW warehouse_balance AS
SELECT 
    c.chip_id,
    c.chip_name,
    c.type,
    c.price,
    COALESCE(SUM(r.quantity), 0) AS total_received,
    COALESCE(SUM(s.quantity), 0) AS total_shipped,
    COALESCE(SUM(r.quantity), 0) - COALESCE(SUM(s.quantity), 0) AS balance
FROM chips c
LEFT JOIN receipt r ON c.chip_id = r.chip_id
LEFT JOIN shipment s ON c.chip_id = s.chip_id
GROUP BY c.chip_id, c.chip_name, c.type, c.price;

-- Представление для заказов с деталями
CREATE VIEW order_details AS
SELECT 
    o.order_id,
    o.order_date,
    o.completion_date,
    o.customer_name,
    o.status,
    o.total_amount,
    COUNT(oi.order_item_id) AS items_count,
    GROUP_CONCAT(c.chip_name SEPARATOR ', ') AS chips_list
FROM orders o
LEFT JOIN order_items oi ON o.order_id = oi.order_id
LEFT JOIN chips c ON oi.chip_id = c.chip_id
GROUP BY o.order_id, o.order_date, o.completion_date, o.customer_name, o.status, o.total_amount;

-- =============================================
-- 6. ВЫПОЛНЕНИЕ ЗАПРОСОВ (ЗАДАНИЯ 12-21)
-- =============================================
-- Задание 12: Типы и цены микросхем
SELECT '=== Задание 12: Типы и цены микросхем ===' AS task;
SELECT type, price FROM chips ORDER BY type, price;

-- Задание 13: Микросхемы в диапазоне цен 20-40
SELECT '=== Задание 13: Микросхемы в диапазоне 20-40 руб ===' AS task;
SELECT chip_name, type, price FROM chips 
WHERE price BETWEEN 20 AND 40
ORDER BY price;

-- Задание 14: Поиск по названию микросхемы
SELECT '=== Задание 14: Поиск микросхемы "ATmega" ===' AS task;
SELECT chip_name, type, technical_specifications, price FROM chips 
WHERE chip_name LIKE '%ATmega%'
ORDER BY price;

-- Задание 15: Цены в разных валютах
SELECT '=== Задание 15: Цены в разных валютах ===' AS task;
SET @usd_rate = 92.50;
SET @eur_rate = 100.20; 
SET @cny_rate = 12.80;

SELECT 
    chip_name AS Название, 
    type AS Тип, 
    price AS 'Цена, руб',
    ROUND(price / @usd_rate, 2) AS 'Цена, USD',
    ROUND(price / @eur_rate, 2) AS 'Цена, EUR',
    ROUND(price / @cny_rate, 2) AS 'Цена, CNY'
FROM chips
ORDER BY price / @usd_rate ASC;

-- Задание 16: Выполненные заказы
SELECT '=== Задание 16: Выполненные заказы ===' AS task;
SELECT * FROM orders 
WHERE completion_date IS NOT NULL
ORDER BY completion_date ASC;

-- Задание 17: Незавершенные заказы по сотрудникам
SELECT '=== Задание 17: Незавершенные заказы по сотрудникам ===' AS task;
SELECT employee_id, COUNT(*) AS 'Количество заказов' 
FROM orders
WHERE completion_date IS NULL
GROUP BY employee_id
ORDER BY COUNT(*) DESC;

-- Задание 19: Демонстрация различных способов вставки
SELECT '=== Задание 19: Проверка вставки данных ===' AS task;
-- Проверяем, что данные вставились
SELECT 'Менеджеры:' AS check_type;
SELECT * FROM manager;

SELECT 'Микросхемы:' AS check_type;
SELECT * FROM chips;

SELECT 'Поставщики:' AS check_type;
SELECT * FROM suppliers;

-- Задание 20: Обновление количества в заказах
SELECT '=== Задание 20: Обновление заказов ===' AS task;
-- Перед обновлением
SELECT 'До обновления:' AS state;
SELECT * FROM order_items WHERE order_id IN (1, 2, 3);

-- Обновление
UPDATE order_items SET quantity = 25 WHERE order_id = 1 AND chip_id = 1;
UPDATE order_items SET quantity = 50 WHERE order_id = 2 AND chip_id = 3;
UPDATE order_items SET quantity = 69 WHERE order_id = 3 AND chip_id = 1;

-- После обновления (триггер автоматически обновил total_amount)
SELECT 'После обновления:' AS state;
SELECT * FROM order_items WHERE order_id IN (1, 2, 3);
SELECT 'Общая сумма заказов обновлена триггером:' AS note;
SELECT order_id, total_amount FROM orders WHERE order_id IN (1, 2, 3);

-- Задание 21: Удаление заказа
SELECT '=== Задание 21: Удаление заказа ===' AS task;
-- Перед удалением
SELECT 'Перед удалением заказа 1:' AS state;
SELECT * FROM orders WHERE order_id = 1;
SELECT * FROM order_items WHERE order_id = 1;
SELECT * FROM shipment WHERE order_id = 1;

-- Удаление (CASCADE удалит order_items, RESTRICT проверит shipment)
DELETE FROM orders WHERE order_id = 1;

-- После удаления
SELECT 'После удаления заказа 1:' AS state;
SELECT * FROM orders WHERE order_id = 1;
SELECT * FROM order_items WHERE order_id = 1;
SELECT * FROM shipment WHERE order_id = 1;

-- Итоговый список заказов
SELECT 'Итоговый список заказов:' AS final;
SELECT order_id, order_date, customer_name, status, total_amount FROM orders ORDER BY order_id;

-- =============================================
-- 7. ДОПОЛНИТЕЛЬНЫЕ ПОЛЕЗНЫЕ ЗАПРОСЫ
-- =============================================
SELECT '=== Дополнительные отчеты ===' AS section;

-- Остатки на складе
SELECT '1. Остатки на складе:' AS report;
SELECT * FROM warehouse_balance WHERE balance > 0 ORDER BY balance DESC;

-- Детали заказов
SELECT '2. Детали заказов:' AS report;
SELECT * FROM order_details;

-- Поставщики и их ассортимент
SELECT '3. Поставщики и ассортимент:' AS report;
SELECT 
    s.company_name,
    s.contact_person,
    s.phone,
    COUNT(sc.chip_id) AS chips_count,
    GROUP_CONCAT(c.chip_name SEPARATOR ', ') AS chips_list
FROM suppliers s
LEFT JOIN supplier_chips sc ON s.supplier_id = sc.supplier_id
LEFT JOIN chips c ON sc.chip_id = c.chip_id
GROUP BY s.supplier_id, s.company_name, s.contact_person, s.phone
ORDER BY chips_count DESC;

-- Продажи по месяцам
SELECT '4. Продажи по месяцам:' AS report;
SELECT 
    DATE_FORMAT(s.date, '%Y-%m') AS month,
    COUNT(DISTINCT s.order_id) AS orders_count,
    SUM(s.quantity) AS total_quantity,
    SUM(s.quantity * c.price) AS total_revenue
FROM shipment s
JOIN chips c ON s.chip_id = c.chip_id
GROUP BY DATE_FORMAT(s.date, '%Y-%m')
ORDER BY month DESC;

-- =============================================
-- 8. ПРОВЕРКА ЦЕЛОСТНОСТИ ДАННЫХ
-- =============================================
SELECT '=== Проверка целостности данных ===' AS check_section;

-- Проверка внешних ключей
SELECT 
    'Внешние ключи:' AS check_type,
    COUNT(*) AS total_tables,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
     WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' AND TABLE_SCHEMA = DATABASE()) AS foreign_keys_count
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE();

-- Проверка индексов
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, INDEX_NAME;

SELECT '=== База данных успешно создана и заполнена ===' AS final_message;
