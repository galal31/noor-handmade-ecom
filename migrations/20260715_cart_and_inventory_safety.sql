ALTER TABLE products
    ADD COLUMN stock_quantity INT UNSIGNED NOT NULL DEFAULT 100 AFTER price;

ALTER TABLE orders
    ADD COLUMN checkout_token CHAR(64) NULL AFTER tracking_code,
    ADD COLUMN stock_released TINYINT(1) NOT NULL DEFAULT 0 AFTER checkout_token,
    ADD UNIQUE KEY uq_orders_checkout_token (checkout_token);

UPDATE orders SET stock_released = 1;

ALTER TABLE order_items
    ADD COLUMN product_name VARCHAR(255) NULL AFTER product_id;

UPDATE order_items oi
JOIN products p ON p.id = oi.product_id
SET oi.product_name = p.name
WHERE oi.product_name IS NULL;

ALTER TABLE order_items
    DROP FOREIGN KEY order_items_ibfk_2;

ALTER TABLE order_items
    MODIFY product_id INT(11) NULL,
    MODIFY product_name VARCHAR(255) NOT NULL;

ALTER TABLE order_items
    ADD CONSTRAINT order_items_ibfk_2
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;
