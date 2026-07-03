CREATE TABLE `membership_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title_array` text,
  `number_of_ads` int DEFAULT NULL,
  `number_of_days` int DEFAULT 30,
  `price` decimal(13,2) DEFAULT 0.00,
  `is_free` tinyint(1) DEFAULT 0,
  `is_unlimited_number_of_ads` tinyint(1) DEFAULT 0,
  `features_array` text,
  `plan_order` int DEFAULT 1,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `membership_plans` (`id`, `title_array`, `number_of_ads`, `number_of_days`, `price`, `is_free`, `is_unlimited_number_of_ads`, `features_array`, `plan_order`, `status`, `created_at`) VALUES
(9901, '{"en":"Imported Bronze"}', 20, 30, 5000.00, 0, 0, NULL, 2, 1, '2024-01-01 00:00:00'),
(9902, '{"en":"Imported Gold"}', 0, 30, 20000.00, 0, 1, NULL, 4, 1, '2024-01-01 00:00:00');
