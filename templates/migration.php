<?php
/**
 * Author: Hzhihua
 * Date: 17-9-7
 * Time: 下午12:18
 * Hzhihua <1044144905@qq.com>
 */
/**
 * This view is used by console/controllers/MigrateController.php
 * The following variables are available in this view:
 */
/* @var $className string the new migration class name */
/* @var $safeUp string the content of safeUp function */
/* @var $safeDown string the content of safeDown function */
echo "<?php\n";
?>

use hzhihua\dump\Migration;

class <?= $className ?> extends Migration
{
	/**
     * @inheritdoc
     */
    public function safeUp()
    {
        <?= $safeUp ?>
    }

	/**
     * @inheritdoc
     */
    public function safeDown()
    {
        <?= $safeDown ?>
    }
}
