ALTER TABLE orders
    ADD COLUMN subtotal_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER customer_address,
    ADD COLUMN shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal_price,
    ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER stock_released,
    ADD COLUMN archived_at DATETIME NULL AFTER is_archived,
    ADD COLUMN archived_by INT(11) NULL AFTER archived_at,
    ADD INDEX idx_orders_archive_date (is_archived, order_date),
    ADD CONSTRAINT orders_archived_by_fk FOREIGN KEY (archived_by) REFERENCES admins(id) ON DELETE SET NULL;

UPDATE orders
SET subtotal_price = total_price,
    shipping_cost = 0.00;

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT(11) NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    changed_by INT(11) NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_status_history_order (order_id, created_at),
    CONSTRAINT order_status_history_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT order_status_history_admin_fk FOREIGN KEY (changed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
SELECT id, NULL, status, 'حالة الطلب قبل تفعيل سجل الحالات', order_date
FROM orders;

CREATE TABLE order_tracking_attempts (
    client_key CHAR(64) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (client_key),
    KEY idx_tracking_attempts_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
