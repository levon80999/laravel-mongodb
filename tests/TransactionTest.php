<?php

use Illuminate\Support\Facades\DB;
use Jenssegers\Mongodb\Eloquent\Model;

class TransactionTest extends TestCase
{
    protected string $connection = 'dsn_mongodb';

    public function setUp(): void
    {
        parent::setUp();

        User::on($this->connection)->truncate();
    }

    public function tearDown(): void
    {
        User::on($this->connection)->truncate();

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app)
    {
        $config = require 'config/database.php';

        $app['config']->set('database.connections.'.$this->connection, $config['connections'][$this->connection]);
        $app['config']->set('database.default', $this->connection);
    }

    public function testCreateWhenTransactionCommitted(): void
    {
        DB::beginTransaction();
            /** @var User $user */
            $user = User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::commit();

        $this->assertInstanceOf(Model::class, $user);
        $this->assertTrue($user->exists);
        $this->assertEquals('klinson', $user->name);

        /** @var User $check */
        $check = User::on($this->connection)->find($user->_id);
        $this->assertNotNull($check);
        $this->assertEquals($user->name, $check->name);
    }

    public function testCreateWhenTransactionRollback(): void
    {
        DB::beginTransaction();
            /** @var User $user */
            $user = User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::rollBack();

        $this->assertInstanceOf(Model::class, $user);
        $this->assertTrue($user->exists);
        $this->assertEquals('klinson', $user->name);

        $check = User::on($this->connection)->where('_id', $user->_id)->exists();
        $this->assertFalse($check);
    }

    public function testInsertThroughQueryBuilderWhenTransactionCommitted(): void
    {
        DB::beginTransaction();
            DB::collection('users')->insert(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::commit();

        $existUser = DB::collection('users')->where('name', 'klinson')->where('age', 20)->where('title', 'admin')->exists();
        $this->assertTrue($existUser);
    }

    public function testInsertThroughQueryBuilderWhenTransactionRollback(): void
    {
        DB::beginTransaction();
            DB::collection('users')->insert(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::rollBack();

        $existUser = DB::collection('users')->where('name', 'klinson')->where('age', 20)->where('title', 'admin')->exists();
        $this->assertFalse($existUser);
    }

    public function testInsertThroughEloquentSaveWhenTransactionCommitted(): void
    {
        DB::beginTransaction();
            /** @var User $user */
            $user = User::on($this->connection)->getModel();
            $user->name = 'klinson';
            $user->save();
        DB::commit();

        $this->assertTrue($user->exists);
        $this->assertNotNull($user->getIdAttribute());

        /** @var User $check */
        $check = User::on($this->connection)->find($user->_id);
        $this->assertNotNull($check);
        $this->assertEquals($check->name, $user->name);
    }

    public function testInsertThroughEloquentSaveWhenTransactionRollback(): void
    {
        DB::beginTransaction();
            /** @var User $user */
            $user = User::on($this->connection)->getModel();
            $user->name = 'klinson';
            $user->save();
        DB::rollBack();

        $this->assertTrue($user->exists);
        $this->assertNotNull($user->getIdAttribute());

        $userExists = User::on($this->connection)->where('_id', $user->_id)->exists();
        $this->assertFalse($userExists);
    }

    public function testInsertGetIdWhenTransactionCommitted(): void
    {
        DB::beginTransaction();
            $userId = DB::collection('users')->insertGetId(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::commit();

        $user = DB::collection('users')->find((string) $userId);
        $this->assertEquals('klinson', $user['name']);
    }

    public function testInsertGetIdWhenTransactionRollback(): void
    {
        DB::beginTransaction();
            $userId = DB::collection('users')->insertGetId(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::rollBack();

        $userExists = DB::collection('users')->where('_id', (string) $userId)->exists();
        $this->assertFalse($userExists);
    }

    public function testUpdateThroughQueryBuilderWhenTransactionCommitted(): void
    {
        User::on($this->connection)->create(['name' => 'users', 'age' => 20, 'title' => 'user']);

        DB::beginTransaction();
            $updated = DB::collection('users')->where('name', 'users')->update(['age' => 999]);
            $this->assertEquals(1, $updated);
        DB::commit();

        $userExists = DB::collection('users')->where('name', 'users')->where('age', 999)->exists();
        $this->assertTrue($userExists);
    }

    public function testUpdateThroughQueryBuilderWhenTransactionRollback(): void
    {
        User::on($this->connection)->create(['name' => 'users', 'age' => 20, 'title' => 'user']);

        DB::beginTransaction();
            $updated = DB::collection('users')->where('name', 'users')->update(['age' => 999]);
            $this->assertEquals(1, $updated);
        DB::rollBack();

        $userExists = DB::collection('users')->where('name', 'users')->where('age', 999)->exists();
        $this->assertFalse($userExists);
    }

    public function testUpdateThroughEloquentUpdateWhenTransactionCommitted(): void
    {
        /** @var User $user1 */
        $user1 = User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        /** @var User $user2 */
        $user2 = User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
            $user1->age = 999;
            $user1->update();
            $user2->update(['age' => 1000]);
        DB::commit();

        $this->assertEquals(999, $user1->age);
        $this->assertEquals(1000, $user2->age);

        $check1 = User::on($this->connection)->where('_id', $user1->_id)->where('age', 999)->exists();
        $check2 = User::on($this->connection)->where('_id', $user2->_id)->where('age', 1000)->exists();
        $this->assertTrue($check1);
        $this->assertTrue($check2);
    }

    public function testUpdateThroughEloquentUpdateWhenTransactionRollback(): void
    {
        /** @var User $user1 */
        $user1 = User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        /** @var User $user2 */
        $user2 = User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
            $user1->age = 999;
            $user1->update();
            $user2->update(['age' => 1000]);
        DB::rollBack();

        $this->assertEquals(999, $user1->age);
        $this->assertEquals(1000, $user2->age);

        $check1 = User::on($this->connection)->where('_id', $user1->_id)->where('age', 999)->exists();
        $check2 = User::on($this->connection)->where('_id', $user2->_id)->where('age', 1000)->exists();
        $this->assertFalse($check1);
        $this->assertFalse($check2);
    }

    public function testDeleteThroughQueryBuilderWhenTransactionCommitted(): void
    {
        User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
            $deleted = User::on($this->connection)->where(['name' => 'klinson', 'age' => 20, 'title' => 'admin'])->delete();
            $this->assertEquals(1, $deleted);
        DB::commit();

        $userExists = User::on($this->connection)->where(['name' => 'klinson', 'age' => 20, 'title' => 'admin'])->exists();
        $this->assertFalse($userExists);
    }

    public function testDeleteThroughQueryBuilderWhenTransactionRollback(): void
    {
        User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
            $deleted = User::on($this->connection)->where(['name' => 'klinson', 'age' => 20, 'title' => 'admin'])->delete();
            $this->assertEquals(1, $deleted);
        DB::rollBack();

        $userExists = User::on($this->connection)->where(['name' => 'klinson', 'age' => 20, 'title' => 'admin'])->exists();
        $this->assertTrue($userExists);
    }

    public function testDeleteThroughEloquentUpdateWhenTransactionCommitted(): void
    {
        /** @var User $user1 */
        $user1 = User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
            $user1->delete();
        DB::commit();

        $userExists = User::on($this->connection)->where('_id', $user1->_id)->exists();
        $this->assertFalse($userExists);
    }

    public function testDeleteThroughEloquentUpdateWhenTransactionRollback(): void
    {
        /** @var User $user1 */
        $user1 = User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
            $user1->delete();
        DB::rollBack();

        $userExists = User::on($this->connection)->where('_id', $user1->_id)->exists();
        $this->assertTrue($userExists);
    }

    public function testIncrementWhenTransactionCommitted(): void
    {
        User::on($this->connection)->create(['name' => 'users', 'age' => 20, 'title' => 'user']);

        DB::beginTransaction();
            DB::collection('users')->where('name', 'users')->increment('age');
        DB::commit();

        $userExists = DB::collection('users')->where('name', 'users')->where('age', 21)->exists();
        $this->assertTrue($userExists);
    }

    public function testIncrementWhenTransactionRollback(): void
    {
        User::on($this->connection)->create(['name' => 'users', 'age' => 20, 'title' => 'user']);

        DB::beginTransaction();
            DB::collection('users')->where('name', 'users')->increment('age');
        DB::rollBack();

        $userExists = DB::collection('users')->where('name', 'users')->where('age', 20)->exists();
        $this->assertTrue($userExists);
    }

    public function testDecrementWhenTransactionCommitted(): void
    {
        User::on($this->connection)->create(['name' => 'users', 'age' => 20, 'title' => 'user']);

        DB::beginTransaction();
            DB::collection('users')->where('name', 'users')->decrement('age');
        DB::commit();

        $userExists = DB::collection('users')->where('name', 'users')->where('age', 19)->exists();
        $this->assertTrue($userExists);
    }

    public function testDecrementWhenTransactionRollback(): void
    {
        User::on($this->connection)->create(['name' => 'users', 'age' => 20, 'title' => 'user']);

        DB::beginTransaction();
            DB::collection('users')->where('name', 'users')->decrement('age');
        DB::rollBack();

        $userExists = DB::collection('users')->where('name', 'users')->where('age', 20)->exists();
        $this->assertTrue($userExists);
    }

    public function testQuery()
    {
        /** rollback test */
        DB::beginTransaction();
            $count = DB::collection('users')->count();
            $this->assertEquals(0, $count);
            DB::collection('users')->insert(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
            $count = DB::collection('users')->count();
            $this->assertEquals(1, $count);
        DB::rollBack();

        $count = DB::collection('users')->count();
        $this->assertEquals(0, $count);

        /** commit test */
        DB::beginTransaction();
            $count = DB::collection('users')->count();
            $this->assertEquals(0, $count);
            DB::collection('users')->insert(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
            $count = DB::collection('users')->count();
            $this->assertEquals(1, $count);
        DB::commit();

        $count = DB::collection('users')->count();
        $this->assertEquals(1, $count);
    }

    public function testTransaction(): void
    {
        User::on($this->connection)->create(['name' => 'users', 'age' => 20, 'title' => 'user']);

        DB::transaction(function () {
            User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
            User::on($this->connection)->where('users')->update(['age' => 999]);
        });

        $count = User::on($this->connection)->count();
        $this->assertEquals(2, $count);

        $checkInsert = User::on($this->connection)->where('klinson')->exists();
        $this->assertTrue($checkInsert);

        $checkUpdate = User::on($this->connection)->where('users')->where('age', 999)->exists();
        $this->assertTrue($checkUpdate);
    }

    public function testIsTransactionSuccessfullyAbortedWhenAttemptsEnd(): void
    {
        $oldUsersCount = User::on($this->connection)->count();

        $result = DB::transaction(function () {
            User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
            User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        }, 0);

        $newUsersCount = User::on($this->connection)->count();

        $this->assertNull($result);
        $this->assertEquals($newUsersCount, $oldUsersCount);
    }

    public function testIsDataInTransactionReturnedWhenTransactionWasSuccess(): void
    {
        $oldUsersCount = User::on($this->connection)->count();

        $result = DB::transaction(function () {
            return User::on($this->connection)->create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        });

        $newUsersCount = User::on($this->connection)->count();

        $this->assertNotNull($result);
        $this->assertEquals($result->title, 'admin');
        $this->assertNotEquals($newUsersCount, $oldUsersCount);
    }

    public function testThrowExceptionForNestedTransactions(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(53 /* InvalidIdField */);

        DB::beginTransaction();
            DB::beginTransaction();
            DB::commit();
        DB::rollBack();
    }

    public function testThrowExceptionWhenNestingTransactionInManualTransaction()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(53 /* InvalidIdField */);

        DB::beginTransaction();
            DB::transaction(function () {
            });
        DB::rollBack();
    }

    public function testThrowExceptionWhenCallCommitBeforeStartTransaction(): void
    {
        $this->expectException(RuntimeException::class);

        DB::commit();
    }

    public function testThrowExceptionWhenCallRollbackBeforeStartTransaction(): void
    {
        $this->expectException(RuntimeException::class);

        DB::rollback();
    }

    public function testThrowExceptionWhenCallRollbackAfterCommit(): void
    {
        $this->expectException(RuntimeException::class);

        DB::beginTransaction();
        DB::commit();

        DB::rollback();
    }
}
