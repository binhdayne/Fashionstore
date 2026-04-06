SHOW TABLES LIKE 'fashionstore_social_account';
SELECT module, schema_version, data_version
FROM setup_module
WHERE module = 'FashionStore_SocialLogin';