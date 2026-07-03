<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\MySqlDumpReader;
use Tests\TestCase;

class MySqlDumpReaderSequelAceTest extends TestCase
{
    public function test_parses_sequel_ace_insert_format_with_values_on_next_line(): void
    {
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

        $this->assertSame(2, $reader->rowCount('payment_gateways'));
        $this->assertSame(1, $reader->rowCount('users'));
        $this->assertSame('stripe', $reader->rows('payment_gateways')[0]['name']);
        $this->assertSame('buyer@example.com', $reader->rows('users')[0]['email']);
    }

    public function test_parses_insert_header_followed_by_values_row_on_next_line(): void
    {
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

        $this->assertSame(1, $reader->rowCount('users'));
        $this->assertSame('split-values-row@example.com', $reader->rows('users')[0]['email']);
    }

    public function test_parses_rows_from_legacy_subset_fixture(): void
    {
        $fixture = base_path('tests/fixtures/legacy-subset.sql');
        $reader = new MySqlDumpReader($fixture);

        $this->assertGreaterThan(0, $reader->rowCount('users'));
        $this->assertGreaterThan(0, $reader->rowCount('products'));
    }

    public function test_unescapes_mysql_line_break_sequences_in_strings(): void
    {
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

        $this->assertSame("For Sale\r\n- Year: 2010\r\n- Price: 6.5m", $reader->rows('product_details')[0]['description']);
    }

    public function test_unescapes_mysql_tab_and_quote_sequences(): void
    {
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

        $this->assertSame("tab\there\\backslash'quote", $reader->rows('notes')[0]['body']);
    }
}
