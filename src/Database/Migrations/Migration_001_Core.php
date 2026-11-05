<?php
declare(strict_types=1);
namespace WCTM\Database\Migrations;
if (!defined('ABSPATH')) { exit; }
final class Migration_001_Core { public function up(): void { \WCTM\Database\Schema::createTables(); } }
