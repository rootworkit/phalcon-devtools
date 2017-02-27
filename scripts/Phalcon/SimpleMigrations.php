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
use Phalcon\Mvc\Model\SimpleMigration as ModelMigration;
use Phalcon\Version\IncrementalItem as IncrementalVersion;
use Phalcon\Version\ItemCollection as VersionCollection;

/**
 * SimpleMigrations Class
 *
 * @package Phalcon
 */
class SimpleMigrations
{

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
        $descr              = isset($options['descr']) ? $options['descr'] : null;
        $noAutoIncrement    = isset($options['noAutoIncrement']) ? $options['noAutoIncrement'] : null;

        self::ensureMigrationsDir($migrationsDir);
        $versionItem    = self::getVersionItem($version, $migrationsDir, $descr);
        $migrationPath  = self::getMigrationPath($migrationsDir, $versionItem, $force);

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
            $wasMigrated = file_put_contents(
                $objectFile,
                '<?php' . PHP_EOL . PHP_EOL . $migration
            );
        }

        if (self::isConsole() && $wasMigrated) {
            print Color::success('Version ' . $versionItem->getVersion() . ' was successfully created') . PHP_EOL;
        } elseif (self::isConsole()) {
            print Color::info('Nothing to create.') . PHP_EOL;
        }
    }

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
        $exportData         = $options['exportData'];
        $migrationsDir      = $options['migrationsDir'];
        $version            = isset($options['version']) ? $options['version'] : null;
        $force              = $options['force'];
        $config             = $options['config'];
        $descr              = isset($options['descr']) ? $options['descr'] : null;
        $noAutoIncrement    = isset($options['noAutoIncrement']) ? $options['noAutoIncrement'] : null;

        self::ensureMigrationsDir($migrationsDir);
        $versionItem    = self::getVersionItem($version, $migrationsDir, $descr);
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
            $migrations = ModelMigration::generateAll($versionItem, $exportData);
            foreach ($migrations as $objectName => $migration) {
                $objectFile = $migrationPath . DIRECTORY_SEPARATOR . $objectName . '.php';
                $wasMigrated = file_put_contents(
                        $objectFile,
                        '<?php' . PHP_EOL . PHP_EOL . $migration
                    ) || $wasMigrated;
            }
        } else {
            $objects = explode(',', $objectName);
            foreach ($objects as $object) {
                $migration = ModelMigration::generate($versionItem, $object, $exportData);
                $objectFile = $migrationPath . DIRECTORY_SEPARATOR . $object . '.php';
                $wasMigrated = file_put_contents(
                    $objectFile,
                    '<?php' . PHP_EOL . PHP_EOL . $migration
                );
            }
        }

        if (self::isConsole() && $wasMigrated) {
            print Color::success('Version ' . $versionItem->getVersion() . ' was successfully generated') . PHP_EOL;
        } elseif (self::isConsole()) {
            print Color::info('Nothing to generate. You should create DB tables or objects first.') . PHP_EOL;
        }
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
     * @param string $descr
     *
     * @return IncrementalVersion|Version\ItemInterface
     */
    protected static function getVersionItem($version, $migrationsDir, $descr)
    {
        // Use timestamped version if description is provided
        if ($descr) {
            $version = (string)(int)(microtime(true) * pow(10, 6));
            VersionCollection::setType(VersionCollection::TYPE_TIMESTAMPED);
            $versionItem = VersionCollection::createItem($version . '_' . $descr);

            // Elsewhere use old-style incremental versioning
            // The version is specified
        } elseif ($version) {
            VersionCollection::setType(VersionCollection::TYPE_INCREMENTAL);
            $versionItem = VersionCollection::createItem($version);

            // The version is guessed automatically
        } else {
            VersionCollection::setType(VersionCollection::TYPE_INCREMENTAL);
            $versionItems = ModelMigration::scanForVersions($migrationsDir);

            if (!isset($versionItems[0])) {
                $versionItem = VersionCollection::createItem('1.0.0');

            } else {
                /** @var IncrementalVersion $versionItem */
                $versionItem = VersionCollection::maximum($versionItems);
                $versionItem = $versionItem->addMinor(1);
            }
        }

        return $versionItem;
    }

    /**
     * @param string                                    $migrationsDir
     * @param IncrementalVersion|Version\ItemInterface  $versionItem
     * @param boolean                                   $force
     *
     * @return string
     */
    protected static function getMigrationPath($migrationsDir, $versionItem, $force)
    {
        $migrationPath = rtrim($migrationsDir, '\\/') . DIRECTORY_SEPARATOR . $versionItem->getVersion();
        if (!file_exists($migrationPath)) {
            if (is_writable(dirname($migrationPath))) {
                mkdir($migrationPath);
                return $migrationPath;
            } else {
                throw new \RuntimeException("Unable to write '{$migrationPath}' directory. Permission denied");
            }
        } elseif (!$force) {
            throw new \LogicException('Version ' . $versionItem->getVersion() . ' already exists');
        }
        return $migrationPath;
    }
}
