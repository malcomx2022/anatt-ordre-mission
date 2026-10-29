<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

// Suite « mysql » : vrai serveur MySQL / MariaDB (db:backup, db:restore, verrous
// InnoDB). Ignorée sous SQLite ; sans RefreshDatabase (cf. Tests\Support\MysqlReel).
uses(TestCase::class)->group('mysql')->in('Mysql');
