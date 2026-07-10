<?php

use Illuminate\Support\Facades\DB;

test('duplicate legacy usernames import with unique suffixes', function () {
    $path = tempnam(sys_get_temp_dir(), 'dup-users-dump');
    file_put_contents($path, <<<'SQL'
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_status` tinyint DEFAULT 0,
  `password` varchar(255) DEFAULT NULL,
  `role_id` int DEFAULT 3,
  `balance` decimal(13,2) DEFAULT 0.00,
  `banned` tinyint DEFAULT 0,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `users` (`id`, `username`, `slug`, `email`, `email_status`, `password`, `role_id`, `balance`, `banned`, `first_name`, `last_name`, `created_at`, `updated_at`)
VALUES
(100,'Footwear','footwear-shop','footwear1@example.com',1,'hash',2,0,0,'First','Vendor','2022-01-01 00:00:00','2022-01-01 00:00:00'),
(200,'Footwear','footwearfit-1','footwear2@example.com',1,'hash',2,0,0,'Second','Vendor','2022-01-02 00:00:00','2022-01-02 00:00:00');
SQL);

    $this->artisan('selloff:migrate', ['--fresh' => true]);
    $this->artisan('selloff:import-legacy-data', ['--source' => $path])->assertSuccessful();
    unlink($path);

    expect(DB::table('users')->count())->toBe(2);
    expect(DB::table('users')->where('id', 100)->value('username'))->toBe('Footwear');
    expect(DB::table('users')->where('id', 200)->value('username'))->toBe('Footwear-200');
    expect(DB::table('users')->where('id', 100)->value('slug'))->toBe('footwear-shop');
    expect(DB::table('users')->where('id', 200)->value('slug'))->toBe('footwearfit-1');
});
