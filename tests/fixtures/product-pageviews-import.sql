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
  `listing_type` varchar(50) DEFAULT 'sell_on_site',
  `status` tinyint DEFAULT 0,
  `visibility` tinyint DEFAULT 1,
  `is_active` tinyint DEFAULT 1,
  `is_sold` tinyint DEFAULT 0,
  `verified` varchar(10) DEFAULT 'No',
  `multiple_sale` tinyint DEFAULT 0,
  `price` decimal(13,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'NGN',
  `stock` int DEFAULT 0,
  `pageviews` int DEFAULT 0,
  `is_draft` tinyint DEFAULT 0,
  `is_deleted` tinyint DEFAULT 0,
  `is_rejected` tinyint DEFAULT 0,
  `reject_reason` varchar(255) DEFAULT NULL,
  `is_edited` tinyint DEFAULT 0,
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

INSERT INTO `users` (`id`, `username`, `slug`, `email`, `email_status`, `password`, `role_id`, `balance`, `banned`, `first_name`, `last_name`, `created_at`, `updated_at`)
VALUES (94001,'views-vendor','views-vendor','views-vendor@example.com',1,'hash',2,0,0,'Views','Vendor','2022-01-01 00:00:00','2022-01-01 00:00:00');

INSERT INTO `categories` (`id`, `slug`, `parent_id`, `status`, `created_at`, `updated_at`)
VALUES (94010,'accessories',NULL,1,'2022-01-01 00:00:00','2022-01-01 00:00:00');

INSERT INTO `products` (`id`, `user_id`, `category_id`, `slug`, `sku`, `product_type`, `listing_type`, `status`, `visibility`, `is_active`, `is_sold`, `verified`, `multiple_sale`, `price`, `currency`, `stock`, `pageviews`, `is_draft`, `is_deleted`, `is_rejected`, `reject_reason`, `is_edited`, `created_at`, `updated_at`)
VALUES
(96001,94001,94010,'popular-sunglasses','SKU-96001','physical','sell_on_site',1,1,1,0,'No',0,50000.00,'NGN',1,1847,0,0,0,NULL,0,'2022-01-01 00:00:00','2022-01-01 00:00:00'),
(96002,94001,94010,'new-listing','SKU-96002','physical','sell_on_site',1,1,1,0,'No',0,10000.00,'NGN',1,0,0,0,0,NULL,0,'2022-01-02 00:00:00','2022-01-02 00:00:00');

INSERT INTO `product_details` (`id`, `product_id`, `title`, `description`) VALUES
(1,96001,'Popular Sunglasses','Has many legacy views'),
(2,96002,'New Listing','No views yet');
