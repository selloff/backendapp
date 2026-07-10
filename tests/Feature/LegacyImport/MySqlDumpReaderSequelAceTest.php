<?php

use App\LegacyImport\MySqlDumpReader;

test('parses sequel ace insert format with values on next line', function () {
    $path = tempnam(sys_get_temp_dir(), 'sequel-ace-dump');
    file_put_contents($path, <<<'SQL'
CREATE TABLE `payment_gateways` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `payment_gateways` (`id`, `name`)
VALUES
	(1,'stripe'),
	(2,'paypal');

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `users` (`id`, `email`) VALUES
(10,'buyer@example.com');
SQL);

    $reader = new MySqlDumpReader($path);
    unlink($path);

    expect($reader->rowCount('payment_gateways'))->toBe(2);
    expect($reader->rowCount('users'))->toBe(1);
    expect($reader->rows('payment_gateways')[0]['name'])->toBe('stripe');
    expect($reader->rows('users')[0]['email'])->toBe('buyer@example.com');
});

test('parses insert header followed by values row on next line', function () {
    $path = tempnam(sys_get_temp_dir(), 'values-row-dump');
    file_put_contents($path, <<<'SQL'
CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `users` (`id`, `email`)
VALUES (42,'split-values-row@example.com');
SQL);

    $reader = new MySqlDumpReader($path);
    unlink($path);

    expect($reader->rowCount('users'))->toBe(1);
    expect($reader->rows('users')[0]['email'])->toBe('split-values-row@example.com');
});

test('parses rows from legacy subset fixture', function () {
    $fixture = base_path('tests/fixtures/legacy-subset.sql');
    $reader = new MySqlDumpReader($fixture);

    expect($reader->rowCount('users'))->toBeGreaterThan(0);
    expect($reader->rowCount('products'))->toBeGreaterThan(0);
});

test('unescapes mysql line break sequences in strings', function () {
    $path = tempnam(sys_get_temp_dir(), 'mysql-escapes-dump');
    file_put_contents($path, <<<'SQL'
CREATE TABLE `product_details` (
  `product_id` int NOT NULL,
  `description` text,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `product_details` (`product_id`, `description`) VALUES
(1,'For Sale\r\n- Year: 2010\r\n- Price: 6.5m');
SQL);

    $reader = new MySqlDumpReader($path);
    unlink($path);

    expect($reader->rows('product_details')[0]['description'])->toBe("For Sale\r\n- Year: 2010\r\n- Price: 6.5m");
});

test('unescapes mysql tab and quote sequences', function () {
    $path = tempnam(sys_get_temp_dir(), 'mysql-tab-dump');
    file_put_contents($path, <<<'SQL'
CREATE TABLE `notes` (
  `id` int NOT NULL,
  `body` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `notes` (`id`, `body`) VALUES
(1,'tab\there\\backslash\'quote');
SQL);

    $reader = new MySqlDumpReader($path);
    unlink($path);

    expect($reader->rows('notes')[0]['body'])->toBe("tab\there\\backslash'quote");
});
