<?php
/**
 * SimpleMigrations Class
 *
 * @copyright   Copyright (c) 2017 Rootwork InfoTech LLC
 * @license     New BSD License
 * @author      Mike Soule <mike@rootwork.it>
 * @package     Phalcon\Commands\Builtin
 */

namespace Phalcon;

use Phalcon\Script\Color;
use Phalcon\Version\Item as VersionItem;
use Phalcon\Mvc\Model\SimpleMigration as ModelMigration;

/**
 * SimpleMigrations Class
 *
 * @package Phalcon
 */
class SimpleMigrations
{

    /**
     * Generate migrations for existing DB objects.
     *
     * @param array $options
     *
     * @throws \Exception
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public static function generate(array $options)
    {
        $objectName         = $options['objectName'];
        $types              = $options['types'];
        $exportData         = $options['exportData'];
        $migrationsDir      = $options['migrationsDir'];
        $version            = isset($options['version']) ? $options['version'] : null;
        $force              = $options['force'];
        $config             = $options['config'];
        $noAutoIncrement    = isset($options['noAutoIncrement']) ? $options['noAutoIncrement'] : null;

        self::ensureMigrationsDir($migrationsDir);
        $versionItem    = self::getVersionItem($version, $migrationsDir);
        $migrationPath  = self::getMigrationPath($migrationsDir, $versionItem, $force);

        // Try to connect to the DB
        if (!isset($config->database)) {
            throw new \RuntimeException('Cannot load database configuration');
        }

        ModelMigration::setup($config->database);
        ModelMigration::setSkipAutoIncrement($noAutoIncrement);
        ModelMigration::setMigrationPath($migrationsDir);

        $wasMigrated = false;
        if ($objectName === '@') {
            $migrations = ModelMigration::generateAll($versionItem, $exportData, $types);
            foreach ($migrations as $objectName => $migration) {
                $objectFile = $migrationPath . DIRECTORY_SEPARATOR . $objectName . '.php';
                $wasMigrated = file_put_contents($objectFile, $migration) || $wasMigrated;
            }
        } else {
            $objects = explode(',', $objectName);
            foreach ($objects as $object) {
                $migration = ModelMigration::generate($versionItem, $object, $exportData);
                $objectFile = $migrationPath . DIRECTORY_SEPARATOR . $object . '.php';
                $wasMigrated = file_put_contents($objectFile, $migration);
            }
        }

        if (self::isConsole() && $wasMigrated) {
            print Color::success('Version ' . $versionItem . ' was successfully generated') . PHP_EOL;
        } elseif (self::isConsole()) {
            print Color::info('Nothing to generate. You should create DB tables or objects first.') . PHP_EOL;
        }
    }

    /**
     * Create a new empty migration
     *
     * @param array $options
     *
     * @throws \Exception
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public static function create(array $options)
    {
        $objectName         = $options['objectName'];
        $migrationsDir      = $options['migrationsDir'];
        $version            = isset($options['version']) ? $options['version'] : null;
        $force              = $options['force'];
        $config             = $options['config'];
        $noAutoIncrement    = isset($options['noAutoIncrement']) ? $options['noAutoIncrement'] : null;

        self::ensureMigrationsDir($migrationsDir);
        $versionItem    = self::getVersionItem($version, $migrationsDir);
        $migrationPath  = self::getMigrationPath($migrationsDir, $version, $force);

        // Try to connect to the DB
        if (!isset($config->database)) {
            throw new \RuntimeException('Cannot load database configuration');
        }

        ModelMigration::setup($config->database);
        ModelMigration::setSkipAutoIncrement($noAutoIncrement);
        ModelMigration::setMigrationPath($migrationsDir);

        $wasMigrated = false;
        $objects = explode(',', $objectName);
        foreach ($objects as $object) {
            $migration = ModelMigration::create($versionItem, $object);
            $objectFile = $migrationPath . DIRECTORY_SEPARATOR . $object . '.php';
            $wasMigrated = file_put_contents($objectFile, $migration);
        }

        if (self::isConsole() && $wasMigrated) {
            print Color::success('Version ' . $versionItem . ' was successfully created') . PHP_EOL;
        } elseif (self::isConsole()) {
            print Color::info('Nothing to create.') . PHP_EOL;
        }
    }

    /**
     * Check if the script is running on Console mode
     *
     * @return boolean
     */
    public static function isConsole()
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Ensure the migrations directory exists.
     *
     * @param string $migrationsDir
     */
    protected static function ensureMigrationsDir($migrationsDir)
    {
        if ($migrationsDir && !file_exists($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }
    }

    /**
     * Get a version item for this migration.
     *
     * @param string $version
     * @param string $migrationsDir
     *
     * @return VersionItem
     * @throws \Exception
     */
    protected static function getVersionItem($version, $migrationsDir)
    {
        if ($version) {
            if (!preg_match('/[a-z0-9](\.[a-z0-9]+)*/', $version, $matches)) {
                throw new \Exception("Version {$version} is invalid");
            }

            $version = $matches[0];
            $version = new VersionItem($version, 3);
        } else {
            $versions = ModelMigration::scanForVersions($migrationsDir);

            if (!count($versions)) {
                $version = new VersionItem('1.0.0');
            } else {
                $version = VersionItem::maximum($versions);
                $version = $version->addMinor(1);
            }
        }

        return $version;
    }

    /**
     * @param string        $migrationsDir
     * @param VersionItem   $versionItem
     * @param boolean       $force
     *
     * @return string
     */
    protected static function getMigrationPath($migrationsDir, $versionItem, $force)
    {
        $migrationPath = rtrim($migrationsDir, '\\/') . DIRECTORY_SEPARATOR . $versionItem;

        if (!file_exists($migrationPath)) {
            if (is_writable(dirname($migrationPath))) {
                mkdir($migrationPath);
                return $migrationPath;
            } else {
                throw new \RuntimeException("Unable to write '{$migrationPath}' directory. Permission denied");
            }
        } elseif (!$force) {
            throw new \LogicException('Version ' . $versionItem . ' already exists');
        }

        return $migrationPath;
    }
}
