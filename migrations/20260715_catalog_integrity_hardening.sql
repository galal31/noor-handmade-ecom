ALTER TABLE products
    DROP FOREIGN KEY products_ibfk_1,
    ADD CONSTRAINT products_category_fk
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT;

