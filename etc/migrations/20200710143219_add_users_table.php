<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUsersTable extends AbstractMigration
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
    $table = $this->table('users');
    $table->addColumn('username', 'string')
      ->addColumn('password', 'string')
      ->addColumn('email', 'string')
      ->addColumn('level', 'integer', ['default' => '0'])
      ->addColumn('useravatar', 'string', ['null' => true])
      ->create();

    $table->renameColumn('id', 'user_id')
      ->save();

    if ($this->isMigratingUp()) {
      $table->insert([[
        'username' => 'test',
        'password' => '$2y$10$SmdcULzWwr1.MvhMVef4pOecN.4g2QR6LHpQt9lF1Va042iNB1lKO',
        'email' => 'kontakt@katalysteducation.org',
        'level' => '2',
        'useravatar' => 'test.png'
      ]])
        ->save();
    }
  }
}