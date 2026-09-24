<?php

namespace Karnoweb\Wallet\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Wallet\WalletServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [WalletServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->runPackageMigrations();
        $this->createFixtureTables();
    }

    /**
     * Requires each package migration file directly and runs `up()`.
     * Every migration file returns an anonymous `Migration` instance, so
     * this exercises the exact same schema shipped to consumers without
     * duplicating it here.
     */
    protected function runPackageMigrations(): void
    {
        $files = glob(__DIR__.'/../database/migrations/*.php');
        sort($files);

        foreach ($files as $file) {
            $migration = require $file;
            $migration->up();
        }
    }

    /**
     * Minimal host-application tables for the fixture models used across
     * the test suite. The package itself never references these tables.
     */
    protected function createFixtureTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        \Karnoweb\Wallet\Tests\Support\ModelWithSettings::$settingsOverride = [];

        parent::tearDown();
    }
}
