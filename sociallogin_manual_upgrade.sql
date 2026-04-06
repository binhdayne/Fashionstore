CREATE TABLE IF NOT EXISTS `fashionstore_social_account` (
    `entity_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `provider` VARCHAR(32) NOT NULL,
    `provider_user_id` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`entity_id`),
    UNIQUE KEY `FASHIONSTORE_SOCIAL_ACCOUNT_PROVIDER_PROVIDER_USER_ID` (`provider`, `provider_user_id`),
    UNIQUE KEY `FASHIONSTORE_SOCIAL_ACCOUNT_CUSTOMER_PROVIDER` (`customer_id`, `provider`),
    KEY `FASHIONSTORE_SOCIAL_ACCOUNT_CUSTOMER_ID` (`customer_id`),
    CONSTRAINT `FS_SOCIAL_ACC_CUST_ID_CUST_ENTITY_ID`
        FOREIGN KEY (`customer_id`) REFERENCES `customer_entity` (`entity_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_general_ci COMMENT='FashionStore Social Login Accounts';

INSERT INTO `setup_module` (`module`, `schema_version`, `data_version`)
VALUES ('FashionStore_SocialLogin', '1.0.0', '1.0.0')
ON DUPLICATE KEY UPDATE
    `schema_version` = VALUES(`schema_version`),
    `data_version` = VALUES(`data_version`);