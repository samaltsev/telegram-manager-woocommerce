<?php
declare(strict_types=1);
namespace WCTM\Database\Migrations;
if (!defined('ABSPATH')) { exit; }
final class Migration_002_Queue { public function up(): void { \WCTM\Database\Schema::createTables(); } }
