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
    const ENTER = "\n";

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
{$textIndent}        throw new \yii\db\Exception('only support "dropTable" and "dropCommentFromTable"');
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

        $tableColumns = $table->columns;

        // 获取字段名称
        $columns = $this->textIndent($indent + 1) . '[';
        foreach ($tableColumns as $value) {
            $columns .= "'{$value->name}', ";
        }
        $columns = rtrim($columns, ', ') . ']';

        $query = (new Query())
            ->select('*')
            ->from($table->name)
            ->offset($_limit[0])
            ->limit($_limit[1])
        ;

        if ($limitCount > $batchInsertLength) {
            // 批量查询
            foreach ($query->batch($batchInsertLength, $this->db) as $rows) {
                $data = array_merge($data, $rows);
            }

            if (empty($data)) {
                return '';
            }

            $countData = count($data);
            for ($i = 0; $i < $countData; $i += $batchInsertLength) {
                $insertData = $this->twoArrayStyleString(array_slice($data, $i, $batchInsertLength), $tableColumns, $indent + 1);

                // $this->batchInsert({{%tableName}},
                //     [$columns],
                //     [$insertData]
                // );
                $definition .= $textIndent;
                $definition .= "\$this->batchInsert('{{%$tableName}}', " . self::ENTER;
                $definition .= $columns . ',' . self::ENTER;
                $definition .= $insertData . self::ENTER;
                $definition .= $textIndent . ');' . self::ENTER;
            }

        } else {
            $data = $query->all($this->db);

            if (empty($data)) {
                return '';
            }

            $insertData = $this->twoArrayStyleString($data, $tableColumns, $indent + 1);

            // $this->batchInsert({{%tableName}},
            //     [$columns],
            //     [$insertData]
            // );
            $definition .= $textIndent;
            $definition .= "\$this->batchInsert('{{%$tableName}}', " . self::ENTER;
            $definition .= $columns . ',' . self::ENTER;
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
        $definition .= "\$this->_transaction->rollBack();";
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
        $tablePrefix = $this->db->tablePrefix;
        $tableName = static::removePrefix($table->name, $tablePrefix);

        $data = $this->db->createCommand('SHOW KEYS FROM ' . $table->name)->queryAll();
        if (empty($data)) {
            return '';
        }

        // 获取索引数据,包括主键
        $keyData = [];
        foreach ($data as $value) {
            $keyName = static::removePrefix($value['Key_name'], $tablePrefix);
            if (empty($keyData[$keyName])) {
                $keyData[$keyName] = [];
            }

            array_push($keyData[$keyName], [
                'table' => $value['Table'],
                'is_unique' => $value['Non_unique'] ? 0 : 1,
                'column_name' => $value['Column_name'],
                'allow_null' => $value['Null'],
                'comment' => $value['Comment'],
                'index_comment' => $value['Index_comment'],
            ]);
        }

        foreach ($keyData as $keyName => $value) {
            $columns = implode(',', array_column($value,'column_name'));
            $definition .= $textIndent;
            $definition .= "\$this->runSuccess['$keyName'] = "; // record which key add successfully

            // primary key
            if ('PRIMARY' === $keyName) {
                $definition .= "\$this->addPrimaryKey(null, '{{%$tableName}}', '$columns');" . self::ENTER;

                foreach ($keyData['PRIMARY'] as $column) {
                    $column = $column['column_name']; // table column name
                    // auto_increment
                    if ($table->columns[$column]->autoIncrement) {
                        $columnType = $table->columns[$column]->type;
                        $property = $table->columns[$column]->unsigned ? 'unsigned' : '';
                        $auto_increment = $this->getAutoIncrementNumber($table->name);
                        $definition .= $textIndent;
                        $definition .= "\$this->runSuccess['addAutoIncrement'] = ";
                        $definition .= "\$this->addAutoIncrement('{{%$tableName}}', '$column', '$columnType', '$property', $auto_increment);" . self::ENTER;
                    }
                }

            } else {
                // other keys except primary key
                $isUnique = $value[0]['is_unique'];
                $definition .= "\$this->createIndex('$keyName', '{{%$tableName}}', '$columns', {$isUnique});" . self::ENTER;
            }


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
{$textIndent}    if ('addAutoIncrement' === \$keyName) {
{$textIndent}        continue;
{$textIndent}    } elseif ('PRIMARY' === \$keyName) {
{$textIndent}        // must be remove auto_increment before drop primary key
{$textIndent}        if (isset(\$this->runSuccess['addAutoIncrement'])) {
{$textIndent}            \$value = \$this->runSuccess['addAutoIncrement'];
{$textIndent}            \$this->dropAutoIncrement("{\$value['table_name']}", \$value['column_name'], \$value['column_type'], \$value['property']);
{$textIndent}        }
{$textIndent}        \$this->dropPrimaryKey(null, '{{%{$tableName}}}');
{$textIndent}    } elseif (!empty(\$keyName)) {
{$textIndent}        \$this->dropIndex("`\$keyName`", '{{%{$tableName}}}');
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

        $string = $this->db->createCommand("SHOW CREATE TABLE {$table->name}")->queryAll();
        $onParams = $this->FKOnParams($string[0]['Create Table']);

        $textIndent = $this->textIndent($indent);
        $definition = $textIndent;
        $definition .= '$tablePrefix = \Yii::$app->getDb()->tablePrefix;' . self::ENTER;
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        foreach ($table->foreignKeys as $fkName => $fk) {
            $refTable = '';
            $refColumns = '';
            $columns = '';
            $delete = isset($onParams[$fkName]['ON DELETE']) ? "'{$onParams[$fkName]['ON DELETE']}'" : 'null';
            $update = isset($onParams[$fkName]['ON UPDATE']) ? "'{$onParams[$fkName]['ON UPDATE']}'" : 'null';
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
                    "\$this->addForeignKey(\$tablePrefix.'%s', '{{%%%s}}', '%s', '{{%%%s}}', '%s', %s, %s);" . self::ENTER,
                    $fkName, // 外健名称
                    $tableName, // 表名
                    $columns, // 列名
                    static::removePrefix($refTable, $this->db->tablePrefix), // 对应外健的表名
                    $refColumns, // 对应外健的列名
                    $delete, // ON DELETE
                    $update // ON UPDATE
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
{$textIndent}foreach (\$this->runSuccess as \$keyName => \$value) {
{$textIndent}    \$this->dropForeignKey(\$keyName, '{{%$tableName}}');
{$textIndent}}
DEFINITION;

        return $definition . self::ENTER;

    }

    /**
     * 获取表自动增长的起始位置
     * @param string $tableName 表名称，带前缀
     * @return int
     */
    public function getAutoIncrementNumber($tableName)
    {
        $data = $this->db->createCommand("SHOW CREATE TABLE {$tableName}")->queryAll();
        $startPosition = (int) strpos($data[0]['Create Table'], 'AUTO_INCREMENT=');
        $endPosition = (int) strpos($data[0]['Create Table'], 'DEFAULT CHARSET=');

        return $startPosition && $endPosition ? (int) substr($data[0]['Create Table'], $startPosition + 15, $endPosition - $startPosition - 1) : 0;
    }

    /**
     * return string of array style
     * eg: "[['1'], ['2'], ['3']]"
     * @param array $array 二维数组
     * @param array $columnType 字段类型
     * @param int $indent int text-indent 文本缩进
     * @return string
     */
    public function twoArrayStyleString(array $array, array $columnType, $indent = 0)
    {
        $string = $this->textIndent($indent) . '[' . self::ENTER;

        foreach ($array as $key => $value) {
            $string .= $this->oneArrayStyleString($value, $columnType, $indent + 1) . ',' . self::ENTER;
        }

        $string .= $this->textIndent($indent) . ']';

        return $string;
    }

    /**
     * return string of array style
     * eg: "['1', '2', '3']"
     * @param array $array 一维数组
     * @param array $columnType 字段类型
     * @param int $indent int text-indent 文本缩进
     * @return string
     */
    public function oneArrayStyleString(array $data, array $columnType, $indent = 0)
    {
        $string = '[';

        // get rows data
        foreach ($data as $columnName => $value) {
            if (null === $value) {
                $string .= 'null, ';
            } elseif (false !== strpos($columnType[$columnName]->dbType, 'int')) {
                $string .= (int) $value . ', ';
            } else { // string text ...
                $value = str_replace(["'", "\\", "\r\n"], ["\'", "\\", self::ENTER], $value);
                $string .= "'{$value}', ";
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
                $comment = addslashes($value['Comment']);
                return $definition . "\$this->addCommentOnTable('{{%$tableName}}', '{$comment}');";
            }
        }

        return '';
    }

    /**
     * 获取外键ON关键字参数
     * @param $string "show create table <table-name>"
     * @return array
     */
    public function FKOnParams($string)
    {
        $matchs = [];
        $data = [];
        $pattern = "RESTRICT|CASCADE|NO ACTION|SET DEFAULT|SET NULL";

        // 匹配每一行
        preg_match_all("/CONSTRAINT `([0-9a-zA-Z_]+)` FOREIGN KEY .*/", $string, $matchs);

        foreach ($matchs[0] as $key => $fkSql) {
            if (strpos($fkSql, 'ON DELETE') !== false) {
                $match = [];
                preg_match("/ON DELETE ($pattern)/", $fkSql, $match);
                $data[$matchs[1][$key]]['ON DELETE'] = $match[1];
            }

            if (strpos($fkSql, 'ON UPDATE') !== false) {
                $match = [];
                preg_match("/ON UPDATE ($pattern)/", $fkSql, $match);
                $data[$matchs[1][$key]]['ON UPDATE'] = $match[1];
            }
        }

        return $data;
    }

    /**
     * Returns the schema type.
     * @param ColumnSchema $column
     * @return string the schema type
     */
    public static function getSchemaType(ColumnSchema $column)
    {
        // boolean
        if ('tinyint(1)' === $column->dbType) {
            return 'boolean()';
        }

        // enum
        if (null !== $column->enumValues) {
            // https://github.com/yiisoft/yii2/issues/9797
            $enumValues = array_map('addslashes', $column->enumValues);
            return "enum(['".implode('\', \'', $enumValues)."'])";
        }

        switch ($column->type) {
            case 'smallint':
                $type = 'smallInteger';
                break;

            case 'int':
                $type = 'integer';
                break;

            case 'bigint':
                $type = 'bigInteger';
                break;

            default:
                $type = $column->type;
                break;
        }

        // others
        if (null === $column->size) {
            return "$type()";
        } else {
            return "{$type}({$column->size})";
        }

    }

    /**
     * Returns the other definition.
     * @param ColumnSchema $column
     * @return string the other definition
     */
    public static function other(ColumnSchema $column)
    {
        $definition = '';

        // unsigned
        if ($column->unsigned) {
            $definition .= '->unsigned()';
        }

        // null
        if ($column->allowNull) {
            $definition .= '->null()';
        } else {
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