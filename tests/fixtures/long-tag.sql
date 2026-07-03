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
  `status` tinyint DEFAULT 1,
  `visibility` tinyint DEFAULT 1,
  `is_active` tinyint DEFAULT 1,
  `is_sold` tinyint DEFAULT 0,
  `verified` varchar(10) DEFAULT 'Yes',
  `multiple_sale` tinyint DEFAULT 0,
  `price` decimal(13,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'NGN',
  `stock` int DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `tags` (
  `id` int NOT NULL,
  `tag` varchar(600) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `product_tags` (
  `id` int NOT NULL,
  `tag_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `users` (`id`, `username`, `slug`, `email`, `email_status`, `password`, `role_id`, `balance`, `banned`, `first_name`, `last_name`, `created_at`, `updated_at`)
VALUES (91001,'long-tag-vendor','long-tag-vendor','long-tag-vendor@example.com',1,'hash',2,0,0,'Vendor','User','2022-01-01 00:00:00','2022-01-01 00:00:00');

INSERT INTO `categories` (`id`, `slug`, `parent_id`, `status`, `created_at`, `updated_at`)
VALUES (91010,'long-tag-cat',NULL,1,'2022-01-01 00:00:00','2022-01-01 00:00:00');

INSERT INTO `products` (`id`, `user_id`, `category_id`, `slug`, `sku`, `product_type`, `listing_type`, `status`, `visibility`, `is_active`, `is_sold`, `verified`, `multiple_sale`, `price`, `currency`, `stock`, `created_at`, `updated_at`)
VALUES (9315,91001,91010,'long-tag-product','SKU-9315','physical','sell_on_site',1,1,1,0,'Yes',0,100.00,'NGN',1,'2022-01-01 00:00:00','2022-01-01 00:00:00');

INSERT INTO `tags` (`id`, `tag`, `created_at`, `updated_at`)
VALUES (4938,'hibiki whisky limited edition japanese whisky rare hibiki whisky limited edition whisky hibiki 21 year old hibiki harmony premium japanese whiskey hibiki rare edition hibiki collectible bottle whisky for collectors hibiki whisky gift best japanese whisky hibiki japan extra keywords to exceed varchar limit for postgres import truncation test case hibiki whisky limited edition japanese whisky rare hibiki whisky limited edition whisky hibiki 21 year old hibiki harmony premium japanese whiskey hibiki rare edition hibiki collectible bottle whisky for collectors hibiki whisky gift best japanese whisky hibiki japan','2022-01-01 00:00:00','2022-01-01 00:00:00');

INSERT INTO `product_tags` (`id`, `tag_id`, `product_id`)
VALUES (1,4938,9315);
