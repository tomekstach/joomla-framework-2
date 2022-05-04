<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStatusTable extends AbstractMigration
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
    $table = $this->table('status');
    $table->addColumn('status_name', 'string')
      ->create();

    if ($this->isMigratingUp()) {
      $table->insert([['id' => 1, 'status_name' => 'guest']])
        ->insert([['id' => 2, 'status_name' => 'tester']])
        ->insert([['id' => 3, 'status_name' => 'developer']])
        ->insert([['id' => 4, 'status_name' => 'admin']])
        ->save();
    }
  }
}