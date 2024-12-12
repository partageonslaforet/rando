-- Ajout des colonnes manquantes à la table users
ALTER TABLE users
    ADD COLUMN verification_token VARCHAR(64) NULL AFTER password,
    ADD COLUMN is_verified BOOLEAN DEFAULT FALSE AFTER verification_token,
    ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user' AFTER is_verified,
    ADD COLUMN reset_token VARCHAR(64) NULL AFTER role,
    ADD COLUMN reset_token_expires DATETIME NULL AFTER reset_token,
    ADD COLUMN last_login DATETIME NULL AFTER reset_token_expires,
    ADD COLUMN name VARCHAR(255) NULL AFTER email,
    ADD COLUMN organization VARCHAR(255) NULL AFTER name,
    ADD COLUMN phone VARCHAR(20) NULL AFTER organization,
    ADD COLUMN address TEXT NULL AFTER phone;

-- Ajout des index pour améliorer les performances
ALTER TABLE users
    ADD INDEX idx_email (email),
    ADD INDEX idx_verification_token (verification_token),
    ADD INDEX idx_reset_token (reset_token),
    ADD INDEX idx_role (role);
    
    