-- Sanitized legacy subset for CI ETL tests (Pass 10/18).
-- Do not use in production; no real PII or payment credentials.

CREATE TABLE `location_countries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `continent_code` varchar(10) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `location_countries` (`id`, `name`, `continent_code`, `status`, `created_at`, `updated_at`) VALUES
(601, 'Nigeria', 'AF', 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00');

CREATE TABLE `location_states` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `location_states` (`id`, `country_id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(602, 601, 'Lagos', 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00');

CREATE TABLE `location_cities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_id` int NOT NULL,
  `state_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `location_cities` (`id`, `country_id`, `state_id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(603, 601, 602, 'Ikeja', 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00');

CREATE TABLE `currencies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(10) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `currency_format` varchar(30) DEFAULT 'us',
  `symbol_direction` varchar(30) DEFAULT 'left',
  `space_money_symbol` tinyint(1) DEFAULT 0,
  `exchange_rate` decimal(13,6) DEFAULT 1.000000,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `currency_format`, `symbol_direction`, `space_money_symbol`, `exchange_rate`, `status`, `created_at`, `updated_at`) VALUES
(701, 'NGN', 'Nigerian Naira', '₦', 'us', 'left', 0, 1.000000, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00');

CREATE TABLE `languages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `short_form` varchar(10) DEFAULT NULL,
  `language_default` tinyint(1) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `languages` (`id`, `name`, `short_form`, `language_default`, `status`, `created_at`, `updated_at`) VALUES
(801, 'English', 'en', 1, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00');

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `email_status` tinyint(1) DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `role_id` int DEFAULT 3,
  `balance` decimal(13,2) DEFAULT 0.00,
  `banned` tinyint(1) DEFAULT 0,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `shop_name` varchar(255) DEFAULT NULL,
  `referral_code` varchar(50) DEFAULT NULL,
  `referral_user_id` int DEFAULT NULL,
  `referral_points` int DEFAULT 0,
  `referral_point_balance` int DEFAULT 0,
  `affiliate_commission_rate` decimal(13,2) DEFAULT 0.00,
  `affiliate_discount_rate` decimal(13,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `slug`, `username`, `email`, `email_status`, `password`, `role_id`, `balance`, `banned`, `first_name`, `last_name`, `shop_name`, `referral_code`, `referral_user_id`, `referral_points`, `referral_point_balance`, `affiliate_commission_rate`, `affiliate_discount_rate`, `created_at`, `updated_at`) VALUES
(101, 'legacy-admin', 'legacyadmin', 'legacy-admin@test.import', 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 0.00, 0, 'Legacy', 'Admin', NULL, 'REF-ADMIN', NULL, 0, 0, 0.00, 0.00, '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
(102, 'legacy-vendor', 'legacyvendor', 'legacy-vendor@test.import', 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 5000.00, 0, 'Legacy', 'Vendor', 'Legacy Shop', 'REF-VENDOR', NULL, 10, 10, 5.00, 2.00, '2024-01-02 00:00:00', '2024-01-02 00:00:00'),
(103, 'legacy-buyer', 'legacybuyer', 'legacy-buyer@test.import', 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 10000.00, 0, 'Legacy', 'Buyer', NULL, 'REF-BUYER', 102, 5, 5, 0.00, 0.00, '2024-01-03 00:00:00', '2024-01-03 00:00:00');

CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`id`, `slug`, `parent_id`, `status`, `created_at`, `updated_at`) VALUES
(201, 'electronics', NULL, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
(202, 'phones', 201, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00');

CREATE TABLE `category_lang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `lang_id` int DEFAULT 1,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `category_lang` (`id`, `category_id`, `lang_id`, `name`) VALUES
(1, 201, 1, 'Electronics'),
(2, 202, 1, 'Phones');

CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `product_type` varchar(30) DEFAULT 'physical',
  `listing_type` varchar(30) DEFAULT 'sell_on_site',
  `status` tinyint(1) DEFAULT 1,
  `visibility` varchar(30) DEFAULT 'visible',
  `is_active` tinyint(1) DEFAULT 1,
  `verified` varchar(10) DEFAULT 'Yes',
  `price` decimal(13,2) DEFAULT 0.00,
  `price_discounted` decimal(13,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'NGN',
  `stock` int DEFAULT 0,
  `is_sold` tinyint(1) DEFAULT 0,
  `multiple_sale` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` (`id`, `user_id`, `category_id`, `slug`, `sku`, `product_type`, `listing_type`, `status`, `visibility`, `is_active`, `verified`, `price`, `price_discounted`, `currency`, `stock`, `is_sold`, `multiple_sale`, `created_at`, `updated_at`) VALUES
(301, 102, 202, 'legacy-phone', 'LEGACY-PHONE-1', 'physical', 'sell_on_site', 1, 'visible', 1, 'Yes', 25000.00, NULL, 'NGN', 5, 0, 1, '2024-01-05 00:00:00', '2024-01-05 00:00:00');

CREATE TABLE `product_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `lang_id` int DEFAULT 1,
  `title` varchar(500) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_details` (`id`, `product_id`, `lang_id`, `title`, `description`) VALUES
(1, 301, 1, 'Legacy Demo Phone', 'Imported from sanitized legacy subset.');

CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` bigint NOT NULL,
  `buyer_id` int NOT NULL,
  `status` tinyint(1) DEFAULT 2,
  `payment_method` varchar(100) DEFAULT 'wallet_balance',
  `payment_status` varchar(50) DEFAULT 'paid',
  `price_subtotal` decimal(13,2) DEFAULT 0.00,
  `price_shipping` decimal(13,2) DEFAULT 0.00,
  `price_total` decimal(13,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'NGN',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `orders` (`id`, `order_number`, `buyer_id`, `status`, `payment_method`, `payment_status`, `price_subtotal`, `price_shipping`, `price_total`, `currency`, `created_at`, `updated_at`) VALUES
(401, 900001, 103, 2, 'wallet_balance', 'paid', 25000.00, 1500.00, 26500.00, 'NGN', '2024-01-10 00:00:00', '2024-01-10 00:00:00');

CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `seller_id` int NOT NULL,
  `product_title` varchar(500) DEFAULT NULL,
  `quantity` int DEFAULT 1,
  `unit_price` decimal(13,2) DEFAULT 0.00,
  `total_price` decimal(13,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `seller_id`, `product_title`, `quantity`, `unit_price`, `total_price`, `created_at`, `updated_at`) VALUES
(501, 401, 301, 102, 'Legacy Demo Phone', 1, 25000.00, 25000.00, '2024-01-10 00:00:00', '2024-01-10 00:00:00');

CREATE TABLE `shipping_addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `country_id` int DEFAULT NULL,
  `state_id` int DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `zip_code` varchar(50) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `shipping_addresses` (`id`, `user_id`, `title`, `first_name`, `last_name`, `email`, `phone_number`, `address`, `country_id`, `state_id`, `city`, `zip_code`, `is_default`, `created_at`, `updated_at`) VALUES
(901, 103, 'Home', 'Legacy', 'Buyer', 'legacy-buyer@test.import', '+2348000000000', '12 Legacy Street', 601, 602, 'Ikeja', '100001', 1, '2024-01-04 00:00:00', '2024-01-04 00:00:00');

CREATE TABLE `wallet_deposits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `deposit_amount` decimal(13,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'NGN',
  `payment_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `wallet_deposits` (`id`, `user_id`, `payment_method`, `payment_id`, `deposit_amount`, `currency`, `payment_status`, `created_at`, `updated_at`) VALUES
(1001, 103, 'paystack', 'DEP-1001', 5000.00, 'NGN', 1, '2024-01-06 00:00:00', '2024-01-06 00:00:00');

CREATE TABLE `wallet_expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `expense_item_id` varchar(30) DEFAULT NULL,
  `expense_type` varchar(50) DEFAULT NULL,
  `expense_amount` decimal(13,2) DEFAULT 0.00,
  `expense_detail` text,
  `currency` varchar(10) DEFAULT 'NGN',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `wallet_expenses` (`id`, `user_id`, `payment_id`, `expense_item_id`, `expense_type`, `expense_amount`, `expense_detail`, `currency`, `created_at`, `updated_at`) VALUES
(1002, 103, 'EXP-1002', '900001', 'sale', 26500.00, 'Legacy order payment', 'NGN', '2024-01-10 00:00:00', '2024-01-10 00:00:00');

CREATE TABLE `earnings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` bigint NOT NULL,
  `order_product_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `sale_amount` decimal(13,2) DEFAULT 0.00,
  `vat_rate` double DEFAULT NULL,
  `vat_amount` decimal(13,2) DEFAULT 0.00,
  `commission_rate` tinyint DEFAULT NULL,
  `commission` decimal(13,2) DEFAULT 0.00,
  `coupon_discount` decimal(13,2) DEFAULT 0.00,
  `shipping_cost` decimal(13,2) DEFAULT 0.00,
  `earned_amount` decimal(13,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'NGN',
  `exchange_rate` decimal(13,6) DEFAULT 1.000000,
  `is_refunded` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `earnings` (`id`, `order_number`, `order_product_id`, `user_id`, `sale_amount`, `vat_rate`, `vat_amount`, `commission_rate`, `commission`, `coupon_discount`, `shipping_cost`, `earned_amount`, `currency`, `exchange_rate`, `is_refunded`, `created_at`, `updated_at`) VALUES
(1101, 900001, 501, 102, 25000.00, 0, 0.00, 10, 2500.00, 0.00, 0.00, 22500.00, 'NGN', 1.000000, 0, '2024-01-10 00:00:00', '2024-01-10 00:00:00');

CREATE TABLE `followers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `following_id` int DEFAULT NULL,
  `follower_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `followers` (`id`, `following_id`, `follower_id`) VALUES
(1201, 102, 103);

CREATE TABLE `comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int DEFAULT 0,
  `product_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `comment` varchar(5000) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `comments` (`id`, `parent_id`, `product_id`, `user_id`, `email`, `name`, `comment`, `ip_address`, `status`, `created_at`) VALUES
(1301, 0, 301, 103, 'legacy-buyer@test.import', 'Legacy Buyer', 'Is this phone still available?', '127.0.0.1', 1, '2024-01-11 00:00:00');

CREATE TABLE `chat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int DEFAULT NULL,
  `receiver_id` int DEFAULT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `product_id` int DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `chat` (`id`, `sender_id`, `receiver_id`, `subject`, `product_id`, `created_at`, `updated_at`) VALUES
(1401, 103, 102, 'Legacy Demo Phone', 301, '2024-01-11 01:00:00', '2024-01-11 01:05:00');

CREATE TABLE `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `chat_id` int DEFAULT NULL,
  `sender_id` int DEFAULT NULL,
  `receiver_id` int DEFAULT NULL,
  `message` varchar(10000) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_user_id` int NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `chat_messages` (`id`, `chat_id`, `sender_id`, `receiver_id`, `message`, `is_read`, `deleted_user_id`, `created_at`) VALUES
(1501, 1401, 103, 102, 'Hello, is this still available?', 0, 0, '2024-01-11 01:00:00');

CREATE TABLE `blog_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lang_id` tinyint DEFAULT 1,
  `name` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `category_order` tinyint DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `blog_categories` (`id`, `lang_id`, `name`, `slug`, `description`, `keywords`, `category_order`) VALUES
(1601, 1, 'Import Stories', 'import-stories', 'Legacy subset blog category', 'import, test', 1);

CREATE TABLE `blog_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lang_id` tinyint DEFAULT 1,
  `title` varchar(500) DEFAULT NULL,
  `slug` varchar(500) DEFAULT NULL,
  `summary` varchar(1000) DEFAULT NULL,
  `content` longtext,
  `keywords` varchar(500) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `storage` varchar(20) DEFAULT 'local',
  `image_default` varchar(255) DEFAULT NULL,
  `image_small` varchar(255) DEFAULT NULL,
  `user_id` int DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `blog_posts` (`id`, `lang_id`, `title`, `slug`, `summary`, `content`, `keywords`, `category_id`, `storage`, `image_default`, `image_small`, `user_id`, `created_at`) VALUES
(1701, 1, 'Legacy Import Blog Post', 'legacy-import-blog-post', 'Sanitized blog post for ETL tests.', '<p>Imported blog content.</p>', 'import', 1601, 'local', 'blog/import.jpg', NULL, 101, '2024-01-12 00:00:00');

CREATE TABLE `pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lang_id` int DEFAULT 1,
  `title` varchar(500) DEFAULT NULL,
  `slug` varchar(500) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `keywords` varchar(500) DEFAULT NULL,
  `page_content` longtext,
  `page_order` int DEFAULT 1,
  `visibility` tinyint(1) DEFAULT 1,
  `title_active` tinyint(1) DEFAULT 1,
  `location` varchar(50) DEFAULT 'information',
  `is_custom` tinyint(1) NOT NULL DEFAULT 1,
  `page_default_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pages` (`id`, `lang_id`, `title`, `slug`, `description`, `keywords`, `page_content`, `page_order`, `visibility`, `title_active`, `location`, `is_custom`, `page_default_name`, `created_at`) VALUES
(1801, 1, 'Legacy About Page', 'legacy-about', 'About page for import tests', 'about', '<p>About legacy subset.</p>', 1, 1, 1, 'information', 1, NULL, '2024-01-12 00:00:00');

CREATE TABLE `knowledge_base_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lang_id` int DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `category_order` smallint DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `knowledge_base_categories` (`id`, `lang_id`, `name`, `slug`, `category_order`) VALUES
(1901, 1, 'Getting Started', 'getting-started', 1);

CREATE TABLE `knowledge_base` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lang_id` tinyint DEFAULT NULL,
  `title` varchar(500) DEFAULT NULL,
  `slug` varchar(500) DEFAULT NULL,
  `content` longtext,
  `category_id` varchar(50) DEFAULT NULL,
  `content_order` smallint DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `knowledge_base` (`id`, `lang_id`, `title`, `slug`, `content`, `category_id`, `content_order`, `created_at`) VALUES
(2001, 1, 'How to import data', 'how-to-import-data', '<p>Knowledge base article for ETL tests.</p>', '1901', 1, '2024-01-13 00:00:00');

CREATE TABLE `support_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `is_guest` tinyint(1) DEFAULT 0,
  `status` smallint DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `support_tickets` (`id`, `user_id`, `name`, `email`, `subject`, `is_guest`, `status`, `updated_at`, `created_at`) VALUES
(2101, 103, 'Legacy Buyer', 'legacy-buyer@test.import', 'Need help with my order', 0, 1, '2024-01-14 00:00:00', '2024-01-14 00:00:00');

CREATE TABLE `support_subtickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `message` text,
  `attachments` text,
  `is_support_reply` tinyint(1) DEFAULT 0,
  `storage` varchar(20) DEFAULT 'local',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `support_subtickets` (`id`, `ticket_id`, `user_id`, `message`, `attachments`, `is_support_reply`, `storage`, `created_at`) VALUES
(2201, 2101, 103, '<p>Can you confirm my order status?</p>', '', 0, 'local', '2024-01-14 00:00:00');

CREATE TABLE `contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `message` varchar(5000) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `contacts` (`id`, `name`, `email`, `message`, `created_at`) VALUES
(2301, 'Legacy Contact', 'contact@test.import', 'Question about marketplace policies.', '2024-01-15 00:00:00');

-- Phase 16 ETL depth tables

CREATE TABLE `category_paths` (
  `ancestor_id` int NOT NULL,
  `descendant_id` int NOT NULL,
  `depth` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`ancestor_id`, `descendant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `category_paths` (`ancestor_id`, `descendant_id`, `depth`) VALUES
(201, 201, 0),
(201, 202, 1),
(202, 202, 0);

CREATE TABLE `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tag` varchar(255) NOT NULL,
  `lang_id` int DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tags` (`id`, `tag`, `lang_id`, `created_at`) VALUES
(3201, 'legacy-phone', 1, '2024-01-05 00:00:00');

CREATE TABLE `product_tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `lang_id` int DEFAULT 1,
  `tag` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_tags` (`id`, `product_id`, `lang_id`, `tag`) VALUES
(3202, 301, 1, 'legacy-phone');

CREATE TABLE `product_options` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `option_name_translations` text,
  `option_type` varchar(30) DEFAULT 'dropdown',
  `display_order` int DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `option_key` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_options` (`id`, `product_id`, `option_name_translations`, `option_type`, `display_order`, `is_active`, `option_key`, `created_at`) VALUES
(3101, 301, '{"1":"Color"}', 'dropdown', 0, 1, 'opt-color', '2024-01-05 00:00:00');

CREATE TABLE `product_option_values` (
  `id` int NOT NULL AUTO_INCREMENT,
  `option_id` int NOT NULL,
  `option_value_translations` text,
  `display_order` int DEFAULT 0,
  `value_key` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_option_values` (`id`, `option_id`, `option_value_translations`, `display_order`, `value_key`, `created_at`) VALUES
(3102, 3101, '{"1":"Black"}', 0, 'val-black', '2024-01-05 00:00:00');

CREATE TABLE `product_option_variants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `price` decimal(13,2) DEFAULT 0.00,
  `price_discounted` decimal(13,2) DEFAULT NULL,
  `quantity` int DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `variant_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_option_variants` (`id`, `product_id`, `sku`, `price`, `price_discounted`, `quantity`, `is_default`, `is_active`, `variant_hash`, `created_at`) VALUES
(3103, 301, 'LEGACY-PHONE-BLK', 25000.00, NULL, 5, 1, 1, 'hash-black', '2024-01-05 00:00:00');

CREATE TABLE `product_option_variant_values` (
  `variant_id` int NOT NULL,
  `value_id` int NOT NULL,
  PRIMARY KEY (`variant_id`, `value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_option_variant_values` (`variant_id`, `value_id`) VALUES
(3103, 3102);

CREATE TABLE `custom_fields` (
  `id` int NOT NULL AUTO_INCREMENT,
  `field_type` varchar(30) DEFAULT 'text',
  `is_required` tinyint(1) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `field_order` int DEFAULT 1,
  `is_product_filter` tinyint(1) DEFAULT 0,
  `product_filter_key` varchar(255) DEFAULT NULL,
  `sort_options` varchar(30) DEFAULT 'alphabetically',
  `where_to_display` tinyint DEFAULT 2,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `custom_fields` (`id`, `field_type`, `is_required`, `status`, `field_order`, `is_product_filter`, `product_filter_key`, `sort_options`, `where_to_display`, `created_at`) VALUES
(3301, 'text', 0, 1, 1, 0, 'screen_size', 'alphabetically', 2, '2024-01-05 00:00:00');

CREATE TABLE `custom_field_lang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `field_id` int NOT NULL,
  `lang_id` int DEFAULT 1,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `custom_field_lang` (`id`, `field_id`, `lang_id`, `name`) VALUES
(1, 3301, 1, 'Screen Size');

CREATE TABLE `custom_fields_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `field_id` int NOT NULL,
  `category_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `custom_fields_category` (`id`, `field_id`, `category_id`) VALUES
(1, 3301, 202);

CREATE TABLE `custom_fields_options` (
  `id` int NOT NULL AUTO_INCREMENT,
  `field_id` int NOT NULL,
  `option_key` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `custom_fields_options` (`id`, `field_id`, `option_key`, `created_at`) VALUES
(3302, 3301, '6-inch', '2024-01-05 00:00:00');

CREATE TABLE `custom_field_option_lang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `option_id` int NOT NULL,
  `lang_id` int DEFAULT 1,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `custom_field_option_lang` (`id`, `option_id`, `lang_id`, `name`) VALUES
(1, 3302, 1, '6 inch');

CREATE TABLE `custom_fields_product` (
  `id` int NOT NULL AUTO_INCREMENT,
  `field_id` int NOT NULL,
  `product_id` int NOT NULL,
  `field_value` varchar(1000) DEFAULT NULL,
  `selected_option_id` int DEFAULT NULL,
  `product_filter_key` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `custom_fields_product` (`id`, `field_id`, `product_id`, `field_value`, `selected_option_id`, `product_filter_key`) VALUES
(3303, 3301, 301, NULL, 3302, 'screen_size');

INSERT INTO `products` (`id`, `user_id`, `category_id`, `slug`, `sku`, `product_type`, `listing_type`, `status`, `visibility`, `is_active`, `verified`, `price`, `price_discounted`, `currency`, `stock`, `is_sold`, `multiple_sale`, `created_at`, `updated_at`) VALUES
(302, 102, 202, 'legacy-ebook', 'LEGACY-EBOOK-1', 'digital', 'sell_on_site', 1, 'visible', 1, 'Yes', 5000.00, NULL, 'NGN', 999, 0, 1, '2024-01-06 00:00:00', '2024-01-06 00:00:00');

INSERT INTO `product_details` (`id`, `product_id`, `lang_id`, `title`, `description`) VALUES
(2, 302, 1, 'Legacy Demo Ebook', 'Digital product for ETL subset.');

CREATE TABLE `digital_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `file_name` varchar(500) NOT NULL,
  `storage` varchar(20) DEFAULT 'local',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `digital_files` (`id`, `product_id`, `user_id`, `file_name`, `storage`, `created_at`) VALUES
(3401, 302, 102, 'legacy-ebook.pdf', 'local', '2024-01-06 00:00:00');

CREATE TABLE `product_license_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `license_key` varchar(255) NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `order_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_license_keys` (`id`, `product_id`, `license_key`, `is_used`, `order_id`, `created_at`) VALUES
(3501, 302, 'LEGACY-KEY-001', 0, NULL, '2024-01-06 00:00:00');

CREATE TABLE `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `order_number` bigint NOT NULL,
  `client_username` varchar(255) DEFAULT NULL,
  `client_first_name` varchar(100) DEFAULT NULL,
  `client_last_name` varchar(100) DEFAULT NULL,
  `client_email` varchar(255) DEFAULT NULL,
  `invoice_items` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `invoices` (`id`, `order_id`, `order_number`, `client_username`, `client_first_name`, `client_last_name`, `client_email`, `invoice_items`, `created_at`) VALUES
(4101, 401, 900001, 'legacybuyer', 'Legacy', 'Buyer', 'legacy-buyer@test.import', 'a:1:{i:0;a:2:{s:5:\"title\";s:17:\"Legacy Demo Phone\";s:5:\"price\";d:25000;}}', '2024-01-10 00:00:00');

CREATE TABLE `escrow_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ref` varchar(100) DEFAULT NULL,
  `item_id` int DEFAULT NULL,
  `item_title` varchar(250) DEFAULT NULL,
  `item_slug` varchar(255) DEFAULT NULL,
  `item_price` decimal(13,2) DEFAULT 0.00,
  `buyer_id` int DEFAULT NULL,
  `seller_id` int DEFAULT NULL,
  `buyer_agreed_to_escrow` int DEFAULT 0,
  `seller_agreed_to_escrow` int DEFAULT 0,
  `payment_link_sent` int DEFAULT 0,
  `payment_received` int DEFAULT 0,
  `seller_shipped_item` int DEFAULT 0,
  `buyer_confirmed_item_delivery` int DEFAULT 0,
  `seller_received_payment` int DEFAULT 0,
  `transaction_complete` int DEFAULT 0,
  `amount_buyer_paid` decimal(13,2) DEFAULT 0.00,
  `amount_seller_received` decimal(13,2) DEFAULT 0.00,
  `commission_rate` float DEFAULT 0,
  `commission_amount` decimal(13,2) DEFAULT 0.00,
  `delivery_cost` decimal(13,2) DEFAULT 0.00,
  `total_amount` decimal(13,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'NGN',
  `status` int DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `escrow_transactions` (`id`, `ref`, `item_id`, `item_title`, `item_slug`, `item_price`, `buyer_id`, `seller_id`, `buyer_agreed_to_escrow`, `seller_agreed_to_escrow`, `payment_link_sent`, `payment_received`, `seller_shipped_item`, `buyer_confirmed_item_delivery`, `seller_received_payment`, `transaction_complete`, `amount_buyer_paid`, `amount_seller_received`, `commission_rate`, `commission_amount`, `delivery_cost`, `total_amount`, `currency`, `status`, `created_at`, `updated_at`) VALUES
(4201, 'ESC-4201', 301, 'Legacy Demo Phone', 'legacy-demo-phone', 25000.00, 103, 102, 1, 1, 1, 1, 0, 0, 0, 0, 26500.00, 22500.00, 10, 2500.00, 1500.00, 29000.00, 'NGN', 0, '2024-01-11 00:00:00', '2024-01-11 00:00:00');

CREATE TABLE `quote_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `seller_id` int NOT NULL,
  `buyer_id` int NOT NULL,
  `product_quantity` int DEFAULT 1,
  `price_offered` decimal(13,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'new_quote_request',
  `message` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `quote_requests` (`id`, `product_id`, `seller_id`, `buyer_id`, `product_quantity`, `price_offered`, `status`, `message`, `created_at`, `updated_at`) VALUES
(4102, 301, 102, 103, 2, 24000.00, 'new_quote_request', 'Can you offer a discount?', '2024-01-08 00:00:00', '2024-01-08 00:00:00');

CREATE TABLE `digital_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `seller_id` int NOT NULL,
  `buyer_id` int NOT NULL,
  `license_key` varchar(255) DEFAULT NULL,
  `purchase_code` varchar(100) DEFAULT NULL,
  `purchase_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `digital_sales` (`id`, `order_id`, `product_id`, `seller_id`, `buyer_id`, `license_key`, `purchase_code`, `purchase_date`, `created_at`) VALUES
(4103, 401, 302, 102, 103, 'LEGACY-KEY-USED', 'PURCHASE-4103', '2024-01-10 00:00:00', '2024-01-10 00:00:00');

CREATE TABLE `coupons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `seller_id` int DEFAULT NULL,
  `coupon_code` varchar(100) NOT NULL,
  `discount_rate` int DEFAULT 10,
  `coupon_count` int DEFAULT 100,
  `minimum_order_amount` decimal(13,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'NGN',
  `usage_type` varchar(30) DEFAULT 'single',
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `coupons` (`id`, `seller_id`, `coupon_code`, `discount_rate`, `coupon_count`, `minimum_order_amount`, `currency`, `usage_type`, `is_public`, `created_at`) VALUES
(8101, 102, 'LEGACY10', 10, 100, 0.00, 'NGN', 'single', 1, '2024-01-07 00:00:00');

CREATE TABLE `coupons_used` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `user_id` int NOT NULL,
  `coupon_code` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `coupons_used` (`id`, `order_id`, `user_id`, `coupon_code`, `created_at`) VALUES
(8102, 401, 103, 'LEGACY10', '2024-01-10 00:00:00');

CREATE TABLE `taxes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_data` text,
  `tax_rate` decimal(8,4) DEFAULT 0.0000,
  `status` tinyint(1) DEFAULT 1,
  `country_ids` text,
  `state_ids` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `taxes` (`id`, `name_data`, `tax_rate`, `status`, `country_ids`, `state_ids`, `created_at`) VALUES
(8201, 'a:1:{i:1;s:3:\"VAT\";}', 7.5000, 1, 'a:1:{i:0;i:601;}', 'a:0:{}', '2024-01-01 00:00:00');

CREATE TABLE `bank_transfers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` bigint DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `payment_note` varchar(500) DEFAULT NULL,
  `receipt_path` varchar(500) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `bank_transfers` (`id`, `order_number`, `user_id`, `payment_note`, `receipt_path`, `status`, `ip_address`, `created_at`) VALUES
(8301, 900001, 103, 'Paid via bank', 'uploads/receipts/test.pdf', 'pending', '127.0.0.1', '2024-01-09 00:00:00');

CREATE TABLE `membership_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  `price` decimal(13,2) DEFAULT 0.00,
  `number_of_days` int DEFAULT 30,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `membership_plans` (`id`, `title`, `description`, `price`, `number_of_days`, `status`, `created_at`) VALUES
(8401, 'Vendor Pro', 'Pro vendor membership', 10000.00, 30, 1, '2024-01-01 00:00:00');

CREATE TABLE `users_membership_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `number_of_days` int DEFAULT NULL,
  `price` decimal(13,2) DEFAULT NULL,
  `is_free` tinyint(1) DEFAULT '0',
  `payment_status` varchar(50) DEFAULT NULL,
  `plan_start_date` timestamp NULL DEFAULT NULL,
  `plan_end_date` timestamp NULL DEFAULT NULL,
  `plan_status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users_membership_plans` (`id`, `user_id`, `plan_id`, `number_of_days`, `price`, `is_free`, `payment_status`, `plan_start_date`, `plan_end_date`, `plan_status`, `created_at`) VALUES
(8402, 102, 8401, 30, 10000.00, 0, 'payment_received', '2024-01-02 00:00:00', '2024-02-02 00:00:00', 1, '2024-01-02 00:00:00');

CREATE TABLE `membership_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `plan_title` varchar(500) DEFAULT NULL,
  `payment_amount` decimal(13,2) DEFAULT 0.00,
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `membership_transactions` (`id`, `user_id`, `plan_id`, `plan_title`, `payment_amount`, `payment_method`, `payment_status`, `created_at`) VALUES
(8403, 102, 8401, 'Vendor Pro (Number of Days: 30)', 10000.00, 'wallet_balance', 'Completed', '2024-01-02 00:00:00');

CREATE TABLE `promoted_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `payment_amount` decimal(13,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'NGN',
  `payment_status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `promoted_transactions` (`id`, `user_id`, `product_id`, `payment_amount`, `currency`, `payment_status`, `created_at`) VALUES
(8501, 102, 301, 2500.00, 'NGN', 'Completed', '2024-01-07 00:00:00');

CREATE TABLE `user_login_activities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `user_login_activities` (`id`, `user_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(8601, 103, '127.0.0.1', 'LegacyImportTest/1.0', '2024-01-04 12:00:00');

CREATE TABLE `payment_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_transfer_enabled` tinyint(1) DEFAULT 1,
  `bank_transfer_accounts` text,
  `cash_on_delivery_enabled` tinyint(1) DEFAULT 1,
  `wallet_enabled` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `payment_settings` (`id`, `bank_transfer_enabled`, `bank_transfer_accounts`, `cash_on_delivery_enabled`, `wallet_enabled`) VALUES
(1, 1, 'Bank: Legacy Test Account', 1, 1);

CREATE TABLE `payment_gateways` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `name_key` varchar(100) NOT NULL,
  `public_key` varchar(500) DEFAULT NULL,
  `secret_key` varchar(500) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 0,
  `environment` varchar(30) DEFAULT 'sandbox',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `payment_gateways` (`id`, `name`, `name_key`, `public_key`, `secret_key`, `status`, `environment`) VALUES
(1, 'Stripe', 'stripe', 'pk_test_legacy', 'sk_test_legacy', 1, 'sandbox');
