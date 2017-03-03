<?php
/**
 * Phalcon\Mvc\Model\Migration
 *
 * @copyright   Copyright (c) 2017 Rootwork InfoTech LLC
 * @license     New BSD License
 * @author      Mike Soule <mike@rootwork.it>
 * @package     Phalcon\Mvc\Model
 */

namespace Phalcon\Mvc\Model;

use Phalcon\Db;
use Phalcon\Text;
use Phalcon\Generator\Snippet;

/**
 * Phalcon\Mvc\Model\Migration
 *
 * "Simple migration" for DB objects.
 *
 * @package Phalcon\Mvc\Model
 */
class SimpleMigration extends Migration
{

    /**
     * Determine if a table has a given column.
     *
     * @param string $table
     * @param string $column
     *
     * @return bool
     */
    public function hasColumn($table, $column)
    {
        $definitions = self::$_connection->describeColumns($table);

        foreach ($definitions as $definition) {
            if ($definition->getName() == $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate an empty migration class.
     *
     * @param string    $version
     * @param string    $object
     *
     * @return string
     */
    public static function create($version, $object)
    {
        $snippet    = new Snippet();
        $classData  = $snippet->getMigrationUp() . "\n    }\n"; // up()
        $classData .= $snippet->getMigrationDown() . "\n    }\n"; // down()

        // full class
        $classVersion   = preg_replace('/[^0-9A-Za-z]/', '', $version);
        $className      = Text::camelize($object) . 'Migration_'.$classVersion;
        $namespace      = '';
        $doc            = $snippet->getClassDoc($className, $namespace);
        $use            = $snippet->getUse('Phalcon\Mvc\Model\SimpleMigration') . "\n\n";
        $classData      = $snippet->getClass($namespace, $use, $doc, '', $className, 'SimpleMigration', $classData);

        return $classData;
    }

    /**
     * Generates all the class migration definitions for certain database setup.
     *
     * @param  string $version
     * @param  string $exportData
     * @param  array  $types
     *
     * @return array
     */
    public static function generateAll($version, $exportData = null, $types = [])
    {
        $classDefinition = [];

        foreach (self::getObjectsList() as $row) {
            $type = strtolower(self::getObjectType($row['name']));

            if (in_array($type, $types)) {
                $classDefinition[$row['name']] = self::generate($version, $row['name'], $exportData);
            }
        }

        return $classDefinition;
    }

    /**
     * Generate DB object creation class.
     *
     * @param string        $version
     * @param string        $object
     * @param string|null   $exportData
     *
     * @return string
     */
    public static function generate($version, $object, $exportData = null)
    {
        $snippet    = new Snippet();
        $objectType = self::getObjectType($object);
        $method     = 'generate' . ucfirst(strtolower($objectType));
        $classData  = $snippet->getMigrationUp() . self::$method($object);

        if ($objectType === 'TABLE' && ($exportData == 'oncreate' || $exportData == 'always')) {
            $allFields = [];

            foreach (self::$_connection->describeColumns($object) as $field) {
                $allFields[] = "'".$field->getName()."'";
            }

            $classData .= "\n" . $snippet->getMigrationBatchInsert($object, $allFields);
        }

        $classData .= "\n    }\n";

        // down()
        $classData .= $snippet->getMigrationDown();

        if ($objectType === 'TABLE' && $exportData == 'always') {
            $classData .= $snippet->getMigrationBatchDelete($object);
        }

        $classData .= "\n    }\n";

        // full class
        $classVersion   = preg_replace('/[^0-9A-Za-z]/', '', $version);
        $className      = Text::camelize($object) . 'Migration_'.$classVersion;
        $namespace      = '';
        $doc            = $snippet->getClassDoc($className, $namespace);
        $use            = $snippet->getUse('Phalcon\Mvc\Model\SimpleMigration') . "\n\n";
        $classData      = $snippet->getClass($namespace, $use, $doc, '', $className, 'SimpleMigration', $classData);

        // dump data
        if ($objectType === 'TABLE' && ($exportData == 'oncreate' || $exportData == 'always')) {
            $fileHandler = fopen(self::getMigrationPath() . $version . '/' . $object . '.dat', 'w');
            $sql = 'SELECT * FROM ' . self::$_connection->escapeIdentifier($object);
            $cursor = self::$_connection->query($sql);
            $cursor->setFetchMode(Db::FETCH_ASSOC);

            while ($row = $cursor->fetchArray()) {
                $data = [];
                foreach ($row as $key => $value) {
                    if (isset($numericFields[$key])) {
                        if ($value === '' || is_null($value)) {
                            $data[] = 'NULL';
                        } else {
                            $data[] = addslashes($value);
                        }
                    } else {
                        $data[] = is_null($value) ? "NULL" : addslashes($value);
                    }

                    unset($value);
                }

                fputcsv($fileHandler, $data);
                unset($row);
                unset($data);
            }

            fclose($fileHandler);
        }

        return $classData;
    }

    /**
     * Get a list of DB objects.
     *
     * @return array
     */
    public static function getObjectsList()
    {
        $sql = self::getObjectsListSql();
        return self::$_connection->fetchAll($sql);
    }

    /**
     * Get a DB object type.
     *
     * @param string $object
     *
     * @return string
     */
    public static function getObjectType($object)
    {
        $sql    = self::getObjectsListSql() . " AND name = '$object'";
        $type   = null;

        foreach (self::$_connection->fetchAll($sql) as $row) {
            $type = $row['type'];

            if ($type != 'TABLE') {
                return $type;
            }
        }

        return $type;
    }

    /**
     * Get the migration path.
     *
     * @return string
     */
    protected static function getMigrationPath()
    {
        $getter = \Closure::bind(static function () {
            return Migration::${'_migrationPath'};
        }, null, '\Phalcon\Mvc\Model\Migration');

        return $getter();
    }

    /**
     * Get the SQL statement for producing a list of DB objects.
     *
     * @return string
     */
    protected static function getObjectsListSql()
    {
        $dbName = self::getDbName();
        $sql = <<<EOT
SELECT type, name, schema_name
FROM (
    SELECT 'TABLE' AS type, TABLE_NAME AS name, TABLE_SCHEMA AS schema_name
    FROM information_schema.TABLES
    UNION
    SELECT 'VIEW' AS type, TABLE_NAME AS name, TABLE_SCHEMA AS schema_name
    FROM information_schema.VIEWS
    UNION
    SELECT ROUTINE_TYPE AS type, ROUTINE_NAME AS name, ROUTINE_SCHEMA AS schema_name
    FROM information_schema.ROUTINES
    UNION
    SELECT 'TRIGGER' AS type, TRIGGER_NAME AS name, TRIGGER_SCHEMA AS schema_name
    FROM information_schema.TRIGGERS
    UNION
    SELECT 'EVENT' AS type, EVENT_NAME AS name, EVENT_SCHEMA AS schema_name
    FROM information_schema.EVENTS
) R WHERE R.schema_name = '$dbName'
EOT;

        return $sql;
    }

    /**
     * Generate table creation code.
     *
     * @param string $name
     *
     * @return string
     */
    protected static function generateTable($name)
    {
        $result     = self::$_connection->fetchOne("SHOW CREATE TABLE $name");
        $createSql  = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $result['Create Table']);
        $lines      = explode("\n", $createSql);
        $lines      = array_map('trim', $lines);
        $first      = array_shift($lines);
        $last       = array_pop($lines);

        $template = <<<EOD
        self::\$_connection->execute("
            $first
                %s
            $last
        ");

EOD;

        return sprintf($template, join("\n                ", $lines));
    }

    /**
     * Generate view creation code.
     *
     * @param string $name
     *
     * @return string
     */
    protected static function generateView($name)
    {
        $result     = self::$_connection->fetchOne("SHOW CREATE VIEW $name");
        $createSql  = $result['Create View'];
        $createSql  = 'CREATE OR REPLACE ' . substr($createSql, strpos($createSql, "VIEW `$name`"));
        $lines      = self::getSqlLines($createSql);
        $first      = array_shift($lines);
        $last       = array_pop($lines);

        $template = <<<EOD
        self::\$_connection->execute("
            $first
                %s
            $last
        ");

EOD;

        return sprintf($template, join("\n                ", $lines));
    }

    /**
     * Generate event creation code.
     *
     * @param string $name
     *
     * @return string
     */
    protected static function generateEvent($name)
    {
        return self::generateBasicCreate('EVENT', $name);
    }

    /**
     * Generate procedure creation code.
     *
     * @param string $name
     *
     * @return string
     */
    protected static function generateProcedure($name)
    {
        return self::generateBasicCreate('PROCEDURE', $name);
    }

    /**
     * Generate function creation code.
     *
     * @param string $name
     *
     * @return string
     */
    protected static function generateFunction($name)
    {
        return self::generateBasicCreate('FUNCTION', $name);
    }

    /**
     * Generate trigger creation code.
     *
     * @param string $name
     *
     * @return string
     */
    protected static function generateTrigger($name)
    {
        return self::generateBasicCreate('TRIGGER', $name);
    }

    /**
     * Generate basic creation code.
     *
     * @param string $type
     * @param string $name
     *
     * @return string
     */
    protected static function generateBasicCreate($type, $name)
    {
        $types      = ['EVENT', 'FUNCTION', 'PROCEDURE', 'TRIGGER'];
        $type       = strtoupper($type);
        $resultKeys = [
            'EVENT'     => 'Create Event',
            'FUNCTION'  => 'Create Function',
            'PROCEDURE' => 'Create Procedure',
            'TRIGGER'   => 'SQL Original Statement',
        ];

        if (!in_array($type, $types)) {
            throw new \InvalidArgumentException("Invalid create type '$type'");
        }

        $result     = self::$_connection->fetchOne("SHOW CREATE $type $name");
        $createSql  = $result[$resultKeys[$type]];
        preg_match("/$type `?$name/", $createSql, $matches, PREG_OFFSET_CAPTURE);
        $createSql  = 'CREATE ' . substr($createSql, $matches[0][1]);
        $lines      = explode("\n", $createSql);
        $lines      = array_map('trim', $lines);
        $first      = array_shift($lines);
        $last       = array_pop($lines);

        $template = <<<EOD
        self::\$_connection->execute("
            $first
                %s
            $last
        ");

EOD;

        return sprintf($template, join("\n                ", $lines));
    }

    /**
     * Get array of lines from an SQL statement.
     *
     * @param string $sql
     * @param int    $max
     *
     * @return array
     */
    protected static function getSqlLines($sql, $max = 70)
    {
        $sql        = preg_replace('/ select /i', ' SELECT ', $sql);
        $parts      = explode(',', $sql);
        $line       = array_shift($parts) . ', ';
        $lines      = [];

        foreach ($parts as $part) {
            if (strlen($line . "$part, ") > $max) {
                $lines[] = $line;
                $line    = '';
            }

            $line .= "$part, ";
        }

        $lastLine   = rtrim($line, ', ');
        $delimiters = ['FROM', 'WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT'];

        foreach ($delimiters as $delimiter) {
            $lastLines = preg_split("/ $delimiter /i", $lastLine);

            if (count($lastLines) > 1) {
                $lines[]    = array_shift($lastLines);
                $lastLine   = "$delimiter " . array_shift($lastLines);
            }
        }

        $lines[]    = $lastLine;
        $lines      = array_map('trim', $lines);

        return $lines;
    }
}
