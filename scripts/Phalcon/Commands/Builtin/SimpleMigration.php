<?php
/**
 * SimpleMigration Command
 *
 * @copyright   Copyright (c) 2017 Rootwork InfoTech LLC
 * @license     New BSD License
 * @author      Mike Soule <mike@rootwork.it>
 * @package     Phalcon\Commands\Builtin
 */

namespace Phalcon\Commands\Builtin;

use Phalcon\Script\Color;
use Phalcon\Commands\Command;
use Phalcon\SimpleMigrations;

/**
 * SimpleMigration Command
 *
 * Generates a simple migration
 *
 * @package Phalcon\Commands\Builtin
 */
class SimpleMigration extends Command
{
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getPossibleParams()
    {
        return [
            'action=s'          => 'Generates a new Simple Migration [generate|create]',
            'config=s'          => 'Configuration file',
            'migrations=s'      => 'Migrations directory',
            'directory=s'       => 'Directory where the project was created',
            'object=s'          => 'DB object to migrate. (Default: all)',
            'types=s'           => 'DB object types to migrate (separated by commas) '
                                 . '[table,view,function,procedure,trigger]',
            'version=s'         => 'Version to migrate',
            'force'             => 'Forces to overwrite existing migrations',
            'no-auto-increment' => 'Disable auto increment (Generating only)',
            'help'              => 'Shows this help [optional]',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param array $parameters
     */
    public function run(array $parameters)
    {
        $path = $this->isReceivedOption('directory') ? $this->getOption('directory') : '';
        $path = realpath($path) . DIRECTORY_SEPARATOR;

        if ($this->isReceivedOption('config')) {
            $config = $this->loadConfig($path . $this->getOption('config'));
        } else {
            $config = $this->getConfig($path);
        }

        if ($this->isReceivedOption('migrations')) {
            $migrationsDir = $path . $this->getOption('migrations');
        } elseif (isset($config['application']['migrationsDir'])) {
            $migrationsDir = $config['application']['migrationsDir'];
            if (!$this->path->isAbsolutePath($migrationsDir)) {
                $migrationsDir = $path . $migrationsDir;
            }
        } elseif (file_exists($path . 'app')) {
            $migrationsDir = $path . 'app/migrations';
        } elseif (file_exists($path . 'apps')) {
            $migrationsDir = $path . 'apps/migrations';
        } else {
            $migrationsDir = $path . 'migrations';
        }

        $objectName = $this->isReceivedOption('object') ? $this->getOption('object') : '@';
        $descr = $this->getOption('descr');
        $action = $this->getOption(['action', 1]);
        $version = $this->getOption('version');

        switch ($action) {
            case 'generate':
                SimpleMigrations::generate([
                    'directory'       => $path,
                    'objectName'      => $objectName,
                    'migrationsDir'   => $migrationsDir,
                    'version'         => $version,
                    'force'           => $this->isReceivedOption('force'),
                    'noAutoIncrement' => $this->isReceivedOption('no-auto-increment'),
                    'config'          => $config,
                    'descr'           => $descr,
                ]);
                break;
            case 'create':
                SimpleMigrations::create([
                    'directory'       => $path,
                    'objectName'      => $objectName,
                    'migrationsDir'   => $migrationsDir,
                    'version'         => $version,
                    'force'           => $this->isReceivedOption('force'),
                    'config'          => $config,
                    'descr'           => $descr,
                ]);
                break;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getCommands()
    {
        return ['simple-migration'];
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function getHelp()
    {
        print Color::head('Help:') . PHP_EOL;
        print Color::colorize('  Generates/Creates a Migration') . PHP_EOL . PHP_EOL;

        print Color::head('Usage: Generate a Simple Migration') . PHP_EOL;
        print Color::colorize('  migration generate', Color::FG_GREEN) . PHP_EOL . PHP_EOL;

        print Color::head('Usage: Create a new Simple Migration') . PHP_EOL;
        print Color::colorize('  migration create', Color::FG_GREEN) . PHP_EOL . PHP_EOL;

        print Color::head('Arguments:') . PHP_EOL;
        print Color::colorize('  help', Color::FG_GREEN);
        print Color::colorize("\tShows this help text") . PHP_EOL . PHP_EOL;

        $this->printParameters($this->getPossibleParams());
    }

    /**
     * {@inheritdoc}
     *
     * @return integer
     */
    public function getRequiredParams()
    {
        return 1;
    }
}
