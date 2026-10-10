-- Run once AFTER importing the sanitized spirited_finds_db (1).sql.
-- For already-existing databases, take a backup before applying.
ALTER TABLE orders
    ADD COLUMN recipient_name VARCHAR(150) NOT NULL DEFAULT '',
    ADD COLUMN contact_phone VARCHAR(20) NOT NULL DEFAULT '',
    ADD COLUMN delivery_address TEXT NULL,
    ADD COLUMN payment_method ENUM('COD') NOT NULL DEFAULT 'COD',
    ADD COLUMN shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00;

CREATE TABLE IF NOT EXISTS product_reviews (
    review_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text VARCHAR(1500) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY one_review_per_customer (product_id, user_id),
    INDEX ix_review_product_time (product_id, created_at),
    CONSTRAINT fk_review_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Replace legacy/demo ratings on existing databases with verified-review averages.
UPDATE products p
SET rating = COALESCE((
    SELECT ROUND(AVG(r.rating))
    FROM product_reviews r
    WHERE r.product_id = p.product_id
), 0);
