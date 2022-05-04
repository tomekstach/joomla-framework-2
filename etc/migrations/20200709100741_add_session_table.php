<?php

declare(strict_types=1);

use Phinx\Db\Action\AddColumn;
use Phinx\Migration\AbstractMigration;

final class AddSessionTable extends AbstractMigration
{
  /**
   * Change Method.
   *
   * Write your reversible migrations using this method.
   *
   * More information on writing migrations is available here:
   * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
   *
   * Remember to call "create()" or "update()" and NOT "save()" when working
   * with the Table class.
   */
  public function change(): void
  {
    $this->table('session', ['id' => false])
      ->addColumn('session_id', 'string')
      ->addColumn('guest', 'integer', ['default' => '1'])
      ->addColumn('time', 'string')
      ->addColumn('data', 'text', ['null' => true])
      ->addColumn('userid', 'integer', ['default' => '0'])
      ->addColumn('username', 'string', ['null' => true])
      ->addColumn('useravatar', 'string', ['null' => true])
      ->addIndex('session_id', ['type' => 'primary_key'])
      ->create();
  }
}