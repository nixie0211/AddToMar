-- PayMongo payment integration (run once on existing databases)
USE addtomar_db;

ALTER TABLE orders
    ADD COLUMN paymongo_checkout_id VARCHAR(100) NULL AFTER payment_proof;

ALTER TABLE orders
    ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER paymongo_checkout_id;

UPDATE settings SET down_payment_percent = 50;
