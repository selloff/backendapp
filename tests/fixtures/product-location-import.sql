CREATE TABLE `location_countries` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `status` tinyint DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `location_states` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `country_id` int NOT NULL,
  `status` tinyint DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `location_cities` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `country_id` int NOT NULL,
  `state_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_status` tinyint DEFAULT 0,
  `password` varchar(255) DEFAULT NULL,
  `role_id` int DEFAULT 2,
  `balance` decimal(13,2) DEFAULT 0.00,
  `banned` tinyint DEFAULT 0,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `shop_name` varchar(255) DEFAULT NULL,
  `is_verified_seller` tinyint DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `status` tinyint DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `products` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `product_type` varchar(50) DEFAULT 'physical',
  `listing_type` varchar(50) DEFAULT 'ordinary_listing',
  `status` tinyint DEFAULT 1,
  `visibility` tinyint DEFAULT 1,
  `is_active` tinyint DEFAULT 1,
  `is_sold` tinyint DEFAULT 0,
  `verified` varchar(10) DEFAULT 'Yes',
  `multiple_sale` tinyint DEFAULT 0,
  `price` decimal(13,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'NGN',
  `stock` int DEFAULT 0,
  `country_id` int DEFAULT NULL,
  `state_id` int DEFAULT NULL,
  `city_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `product_details` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `location_countries` (`id`, `name`, `status`) VALUES (161, 'Nigeria', 1);
INSERT INTO `location_states` (`id`, `name`, `country_id`, `status`) VALUES (306, 'Ogun', 161, 1);
INSERT INTO `location_cities` (`id`, `name`, `country_id`, `state_id`) VALUES (1001, 'Abeokuta South', 161, 306);

INSERT INTO `users` (`id`, `username`, `slug`, `email`, `email_status`, `password`, `role_id`, `balance`, `banned`, `first_name`, `last_name`, `shop_name`, `is_verified_seller`, `created_at`, `updated_at`)
VALUES (92001,'lord-man','lord-man','lord-man@example.com',1,'hash',2,0,0,'Lord','Man','Lord Man Shop',1,'2022-01-01 00:00:00','2022-01-01 00:00:00');

INSERT INTO `categories` (`id`, `slug`, `parent_id`, `status`, `created_at`, `updated_at`)
VALUES (92010,'phones',NULL,1,'2022-01-01 00:00:00','2022-01-01 00:00:00');

INSERT INTO `products` (`id`, `user_id`, `category_id`, `slug`, `sku`, `product_type`, `listing_type`, `status`, `visibility`, `is_active`, `is_sold`, `verified`, `multiple_sale`, `price`, `currency`, `stock`, `country_id`, `state_id`, `city_id`, `created_at`, `updated_at`)
VALUES (94001,92001,92010,'iphone-15','SKU-94001','physical','ordinary_listing',1,1,1,0,'Yes',0,620000.00,'NGN',1,161,306,1001,'2022-01-01 00:00:00','2022-01-01 00:00:00');

INSERT INTO `product_details` (`id`, `product_id`, `title`, `description`)
VALUES (1,94001,'iPhone 15','Demo phone with location');
