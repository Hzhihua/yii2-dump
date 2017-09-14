<?php
/**
 * @Author: Hzhihua
 * @Date: 17-9-7 12:18
 * @Email cnzhihua@gmail.com
 */

namespace hzhihua\dump\models;

use yii\db\Connection;
use yii\db\Query;
use yii\db\TableSchema;
use yii\db\Expression;
use yii\db\ColumnSchema;
use yii\helpers\ArrayHelper;
use hzhihua\dump\abstracts\AbstractSchema;

/**
 * Class Schema
 * Handle table
 *
 * @package hzhihua\dump\models
 * @Author Hzhihua <cnzhihua@gmail.com>
 */
class Schema extends AbstractSchema
{
    /**
     * enter 换行符号
     */
    const ENTER = PHP_EOL;

    /**
     * text ident format
     * 4 space
     */
    public $textIndent = '    ';

    /**
     * cache sql result "SHOW TABLE STATUS"
     * @var null
     */
    protected $_tableStatus = null;

    /**
     * table list
     * @param Connection $db
     * @param string $sparactor "table1, table2, table3"
     * @return string
     */
    public function getTableList(Connection $db, $sparactor = ', ')
    {
        $tableList = '';

        foreach ($db->schema->getTableNames() as $tableName) {
            $tableList .= static::removePrefix($tableName, $this->db->tablePrefix) . $sparactor;
        }

        return rtrim($tableList, $sparactor);
    }

    /**
     * get table safeUp definition with primary key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getTable(TableSchema $table, $indent = 0)
    {
        $definition =
            $this->getCreateTable($table->name, $indent)
            . $this->getColumns($table->columns, $indent + 1)
            . $this->getTableOptions($table, $indent)
        ;

        return $definition;
    }

    /**
     * get table safeDown definition
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getDropTable(TableSchema $table, $indent = 0)
    {
        // Do not run this sql: "drop table `tableName`", it will drop the table that has exists before running "./yii migrate"

        $textIndent = $this->textIndent($indent);
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        $definition = <<<DEFINITION
{$textIndent}foreach (\$this->runSuccess as \$keyName => \$value) {
{$textIndent}    if ('createTable' === \$keyName) {
{$textIndent}        \$this->dropTable('{{%$tableName}}');
{$textIndent}    } elseif ('addTableComment' === \$keyName) {
{$textIndent}        \$this->dropCommentFromTable('{{%$tableName}}');
{$textIndent}    } else {
{$textIndent}        throw new \yii\db\Exception('some errors in:' . __FILE__);
{$textIndent}    }
{$textIndent}}
DEFINITION;

        return $definition;

    }

    /**
     * fetch table data limit(start,end)
     * @param TableSchema $table
     * @param $limit string select data from table limit ...
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getTableData(TableSchema $table, $limit = null, $indent = 0)
    {

        if (null === $limit) {
            return '';
        }

        $data = [];
        $_limit = '';
        $batchInsertLength = 500;
        $textIndent = $this->textIndent($indent);
        $definition = $textIndent . '$this->_transaction = $this->getDb()->beginTransaction();' . self::ENTER;

        if (is_bool($limit)) { // -limit
            $_limit = [null, null]; // select all data
        } elseif (empty($limit)) { // -limit=
            $_limit = [null, null]; // select all data
        } elseif (false !== strpos($limit, ',')) { // -limit=1,10 or -limit=1,
            $_limit = explode(',', $limit);

            if (empty($_limit[1])) { // -limit=1,
                $_limit[1] = null;
            }
        } else { // -limit=1
            $_limit[0] = 0;
            $_limit[1] = $limit;
        }

        $limitCount = $_limit[1] - $_limit[0];

        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        $columns = $this->_arrayStyleString($table->columns, 'key', $indent + 1);

        $query = (new Query())
            ->select('*')
            ->from($table->name)
            ->offset($_limit[0])
            ->limit($_limit[1])
        ;

        if ($limitCount > $batchInsertLength) {
            // 批量查询
            foreach ($query->batch(500) as $rows) {
                $data = array_merge($data, $rows);
            }

            if (empty($data)) {
                return '';
            }

            $countData = count($data);
            for ($i = 0; $i < $countData; $i += $batchInsertLength) {
                $insertData = $this->arrayStyleString(array_slice($data, $i, $batchInsertLength), $indent + 1);

                // $this->batchInsert({{%tableName}},
                //     [$columns],
                //     [$insertData]
                // );
                $definition .= $textIndent;
                $definition .= "\$this->batchInsert('{{%$tableName}}', " . self::ENTER;
                $definition .= $columns . ', ' . self::ENTER;
                $definition .= $insertData . self::ENTER;
                $definition .= $textIndent . ');' . self::ENTER;
            }

        } else {
            $data = $query->all();

            if (empty($data)) {
                return '';
            }

            $insertData = $this->arrayStyleString($data, $indent + 1);

            // $this->batchInsert({{%tableName}},
            //     [$columns],
            //     [$insertData]
            // );
            $definition .= $textIndent;
            $definition .= "\$this->batchInsert('{{%$tableName}}', " . self::ENTER;
            $definition .= $columns . ', ' . self::ENTER;
            $definition .= $insertData . self::ENTER;
            $definition .= $textIndent . ');' . self::ENTER;
        }

        $definition .= $textIndent . '$this->_transaction->commit();' . self::ENTER;

        return $definition;

    }

    /**
     * truncate table data
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getDropTableData(TableSchema $table, $indent = 0)
    {
        // Do not use "truncate tableName", it will delete all data of table, include data before you run "./yii migrate" commond

        $definition = $this->textIndent($indent);
        $definition .= '$this->_transaction->rollBack();';
        $definition .= self::ENTER;

        return $definition;
    }

    /**
     * get column key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getKey(TableSchema $table, $indent = 0)
    {
        $definition = '';
        $textIndent = $this->textIndent($indent);
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        $data = $this->db->createCommand('SHOW KEYS FROM ' . $table->name)->queryAll();
        $keys = ArrayHelper::map($data, 'Column_name', 'Non_unique', 'Key_name');

        foreach ($keys as $keyName => $value) {
            $columns = null;
            $isUnique = null;
            foreach ($value as $columnName => $nonUnique) {
                $columns .= $columnName . ',';
            }
            $isUnique = $nonUnique ? 0 : 1;
            $columns = rtrim($columns, ',');

            $definition .= $textIndent; // text-indent 缩进
            $definition .= "\$this->runSuccess['$keyName'] = "; // record which key add successfully

            if ('PRIMARY' === $keyName) { // add primary key
                $definition .= "\$this->addPrimaryKey(null, '{{%$tableName}}', '$columns');";
            } else {
                $definition .= "\$this->createIndex('$keyName', '{{%$tableName}}', '$columns', $isUnique);";
            }

            $definition .= self::ENTER;

        }

        return $definition;
    }

    /**
     * drop column key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getDropKey(TableSchema $table, $indent = 0)
    {
        $textIndent = $this->textIndent($indent);
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        $definition = <<<DEFINITION
{$textIndent}foreach (\$this->runSuccess as \$keyName => \$value) {
{$textIndent}    if ('PRIMARY' === \$keyName) {
{$textIndent}        \$this->dropPrimaryKey(null, '{{%$tableName}}');
{$textIndent}    } else {
{$textIndent}        \$this->dropIndex(\$keyName, '{{%$tableName}}');
{$textIndent}    }
{$textIndent}}
DEFINITION;

        return $definition . self::ENTER;

    }

    /**
     * add forengin key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getFK(TableSchema $table, $indent = 0)
    {
        if (empty($table->foreignKeys)) {
            return '';
        }

        $textIndent = $this->textIndent($indent);
        $definition = $textIndent;
        $definition .= '$tablePrefix = \Yii::$app->getDb()->tablePrefix;' . self::ENTER;
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        foreach ($table->foreignKeys as $fkName => $fk) {
            $refTable = '';
            $refColumns = '';
            $columns = '';
            $fkName = static::removePrefix($fkName, $this->db->tablePrefix);

            foreach ($fk as $k => $v) {
                if (0 === $k) {
                    $refTable = $v;
                } else {
                    $columns = $k;
                    $refColumns = $v;
                }
            }

            $definition .= $textIndent;
            $definition .= "\$this->runSuccess[\$tablePrefix.'{$fkName}'] = ";
            $definition .= sprintf(
                    "\$this->addForeignKey(\$tablePrefix.'%s', '{{%%%s}}', '%s', '{{%%%s}}', '%s');" . self::ENTER,
                    $fkName, // 外健名称
                    $tableName, // 表名
                    $columns, // 列名
                    static::removePrefix($refTable, $this->db->tablePrefix), // 对应外健的表名
                    $refColumns // 对应外健的列名
                );

        }
        return $definition;
    }

    /**
     * drop foreign key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getDropFK(TableSchema $table, $indent = 0)
    {
        if (empty($table->foreignKeys)) {
            return '';
        }

        $textIndent = $this->textIndent($indent);
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        $definition = <<<DEFINITION
{$textIndent}\$tablePrefix = \\Yii::\$app->getDb()->tablePrefix;
{$textIndent}foreach (\$this->runSuccess as \$keyName => \$value) {
{$textIndent}    \$this->dropForeignKey(\$tablePrefix.\$keyName, '{{%$tableName}}');
{$textIndent}}
DEFINITION;

        return $definition . self::ENTER;

    }

    /**
     * return string of array style
     * eg: "[['1'], ['2'], ['3']]"
     * @param array $array 二维数组
     * @param int $indent int text-indent 文本缩进
     * @return string
     */
    public function arrayStyleString(array $array, $indent = 0)
    {
        $string = '';
        $string .= $this->textIndent($indent) . '[';
        $string .= self::ENTER;

        foreach ($array as $key => $value) {
            $string .= $this->_arrayStyleString($value, 'value', $indent + 1) . ',' . self::ENTER;
        }

        $string .= $this->textIndent($indent) . ']';

        return $string;
    }

    /**
     * return string of array style
     * eg: "['1', '2', '3']"
     * @param array $array 一维数组
     * @param string $type
     * @param int $indent int text-indent 文本缩进
     * @return string
     */
    public function _arrayStyleString(array $array, $type = "value", $indent = 0)
    {
        $string = '';
        $string .= '[';

        if ('key' === $type) { // get column name
            foreach ($array as $key => $value) {
                $string .= '\'' . $key . '\', ';
            }
        } else {  // get rows data
            foreach ($array as $key => $value) {
                if (null === $value) {
                    $string .= 'null, ';
                } elseif (is_int($value)) {
                    $string .= $value . ', ';
                }else {
                    $string .= '\'' . $value . '\', ';
                }
            }
        }

        $string = rtrim($string, ', ');
        $string .= ']';

        return $this->textIndent($indent) . $string;
    }

    /**
     * @param int $indent text-indent number
     * @return string
     */
    public function textIndent($indent)
    {
        return str_repeat($this->textIndent, $indent);
    }

    /**
     * @param $tableName string
     * @param int $indent the number of text indent
     * @return string
     */
    public function getCreateTable($tableName, $indent = 0)
    {
        $tableName = static::removePrefix($tableName, $this->db->tablePrefix);
        $textIndent = $this->textIndent($indent);

        $definition = $textIndent . '$this->runSuccess[\'createTable\'] = ';
        $definition .= "\$this->createTable('{{%$tableName}}', [" . self::ENTER;

        return $definition;
    }

    /**
     * Returns the columns definition.
     * @param $columns array
     * @param int $indent the number of text indent
     * @return string
     */
    public function getColumns(array $columns, $indent = 0)
    {
        $definition = '';
        $textIndent = $this->textIndent($indent);
        foreach ($columns as $column) {
            $tmp = sprintf("'%s' => \$this->%s%s," . self::ENTER,
                $column->name, static::getSchemaType($column), static::other($column));

            if (null !== $column->enumValues) {
                $tmp = static::replaceEnumColumn($tmp);
            }
            $definition .= $textIndent . $tmp; // 处理缩进问题
        }
        return $definition;
    }

    /**
     * Returns the primary key definition.
     * @param array $pk
     * @param array $columns
     * @param int $indent the number of text indent
     * @return string the primary key definition
     */
    public function getPrimaryKey(array $pk, array $columns, $indent = 0)
    {
        if (empty($pk)) {
            return '';
        }

        // Composite primary keys
        if (2 <= count($pk)) {
            $compositePk = implode(', ', $pk);
            return $this->textIndent($indent) . "'PRIMARY KEY ($compositePk)'," . self::ENTER;
        }
        // Primary key not an auto-increment
        $flag = false;
        foreach ($columns as $column) {
            if ($column->autoIncrement) {
                $flag = true;
            }
        }
        if (false === $flag) {
            return $this->textIndent($indent) . sprintf("'PRIMARY KEY (%s)'," . self::ENTER, $pk[0]);
        }
        return '';
    }

    /**
     * @param TableSchema $table
     * @param int $indent the number of text indent
     * @return string
     */
    public function getTableOptions(TableSchema $table, $indent = 0)
    {
        $tableComment = $this->getTableComment($table, $indent);
        $tableOptions = $this->textIndent($indent) . '], $this->tableOptions);' . self::ENTER;

        if (! empty($tableComment)) {
            $tableOptions .= self::ENTER . $tableComment . self::ENTER;
        }

        return $tableOptions;
    }

    /**
     * get table comment
     * @param TableSchema $table
     * @param int $indent the number of text indent
     * @return string
     */
    public function getTableComment(TableSchema $table, $indent = 0)
    {

        if (null === $this->_tableStatus) {
            try {
                // 不知 “SHOW TABLE STATUS” sql语句在其他数据库中是否会执行成功，所以用try catch捕获异常
                $this->_tableStatus = $this->db->createCommand('SHOW TABLE STATUS')->queryAll();
            } catch (\Exception $e) {
                return '';
            }
        }

        $textIndent = $this->textIndent($indent);
        $definition = $textIndent . '$this->runSuccess[\'addTableComment\'] = ';
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        foreach ($this->_tableStatus as $value) {
            if ($table->name === $value['Name'] && ! empty($value['Comment'])) {
                return $definition . "\$this->addCommentOnTable('{{%$tableName}}', '{$value['Comment']}');";
            }
        }

        return '';
    }

    /**
     * Returns the schema type.
     * @param ColumnSchema $column
     * @return string the schema type
     */
    public static function getSchemaType($column)
    {
        // primary key
        if ($column->isPrimaryKey && $column->autoIncrement) {
            if ('bigint' === $column->type) {
                return 'bigInteger()';
            } elseif('smallint' === $column->type) {
                return 'smallInteger()';
            }
            return 'integer()';
        }

        // boolean
        if ('tinyint(1)' === $column->dbType) {
            return 'boolean()';
        }

        // smallint
        if ('smallint' === $column->type) {
            if (null === $column->size) {
                return 'smallInteger()';
            }
            return 'smallInteger';
        }

        // bigint
        if ('bigint' === $column->type) {
            if (null === $column->size) {
                return 'bigInteger()';
            }
            return 'bigInteger';
        }

        // enum
        if (null !== $column->enumValues) {
            // https://github.com/yiisoft/yii2/issues/9797
            $enumValues = array_map('addslashes', $column->enumValues);
            return "enum(['".implode('\', \'', $enumValues)."'])";
        }

        // others
        if (null === $column->size && 0 >= $column->scale) {
            return $column->type.'()';
        }

        return $column->type;
    }

    /**
     * Returns the other definition.
     * @param ColumnSchema $column
     * @return string the other definition
     */
    public static function other(ColumnSchema $column)
    {
        $definition = '';

        // size
        if (null !== $column->scale && 0 < $column->scale) {
            $definition .= "($column->precision,$column->scale)";

        } elseif (null !== $column->size && ! $column->autoIncrement && 'tinyint(1)' !== $column->dbType) {
            $definition .= "($column->size)";

        } elseif (null !== $column->size && ! $column->isPrimaryKey && $column->unsigned) {
            $definition .= "($column->size)";
        }

        // unsigned
        if ($column->unsigned) {
            $definition .= '->unsigned()';
        }

        // null
        if ($column->allowNull) {
            $definition .= '->null()';

        } elseif (! $column->autoIncrement) {
            $definition .= '->notNull()';
        }

        // default value
        if ($column->defaultValue instanceof Expression) {
            $definition .= "->defaultExpression('$column->defaultValue')";

        } elseif (is_int($column->defaultValue)) {
            $definition .= "->defaultValue($column->defaultValue)";

        } elseif (is_bool($column->defaultValue)) {
            $definition .= '->defaultValue('.var_export($column->defaultValue, true).')';

        } elseif (is_string($column->defaultValue)) {
            $definition .= "->defaultValue('".addslashes($column->defaultValue)."')";
        }

        // comment
        if (null !== $column->comment && '' !== $column->comment) {
            $definition .= "->comment('".addslashes($column->comment)."')";
        }

        // append

        return $definition;
    }

    /**
     * @param string $tmp temporary definition
     * @return string
     */
    public static function replaceEnumColumn($tmp)
    {
        return preg_replace("/,\n/", "\"," . self::ENTER, strtr($tmp, [
            '()' => '',
            '])' => ')',
            '),' => ',',
            '$this->enum([' => '"ENUM (',
            '->notNull' => ' NOT NULL',
            '->null' => ' DEFAULT NULL',
            '->defaultValue(' => ' DEFAULT ',
            '->comment(' => ' COMMENT ',
        ]));
    }

}