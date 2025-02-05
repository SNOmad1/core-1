<?php
/**
 * @author Jannik Stehle <jstehle@owncloud.com>
 *
 * @copyright Copyright (c) 2021, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Tests\Core\Command\User;

use Doctrine\DBAL\ForwardCompatibility\DriverStatement;
use OC\Core\Command\User\HomeListUsers;
use OC\User\AccountMapper;
use OCP\IDBConnection;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

/**
 * Class HomeListUsersTest
 *
 * @group DB
 */
class HomeListUsersTest extends TestCase {
	/** @var CommandTester */
	private $commandTester;

	/** @var IDBConnection | \PHPUnit\Framework\MockObject\MockObject */
	private $connection;

	/** @var \OCP\IUserManager | \PHPUnit\Framework\MockObject\MockObject */
	protected $userManager;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = $this->getMockBuilder('\OC\DB\Connection')
			->disableOriginalConstructor()
			->getMock();
		$this->userManager = $this->getMockBuilder('\OC\User\Manager')
			->disableOriginalConstructor()
			->getMock();
		$command = new HomeListUsers(
			$this->connection,
			$this->userManager
		);
		$this->commandTester = new CommandTester($command);
	}

	public function testCommandInputForHomePath() {
		$homePath = '/path/to/homes';
		$uid = 'user1';

		$resultMock = $this->createMock(DriverStatement::class);
		$resultMock->method('fetch')->willReturnOnConsecutiveCalls(['user_id' => $uid], false);
		$queryMock = $this->getMockBuilder('\OC\DB\QueryBuilder\QueryBuilder')
			->setConstructorArgs([$this->connection])
			->setMethods(['execute'])
			->getMock();
		$queryMock->method('execute')->willReturn($resultMock);
		$this->connection->method('getQueryBuilder')->willReturn($queryMock);

		$this->commandTester->execute(['path' => $homePath]);
		$output = $this->commandTester->getDisplay();
		$this->assertStringContainsString($uid, $output);
	}

	public function testCommandInputAll() {
		$uid = 'testhomeuser';
		$path = '/some/path';
		$userObject = $this->getMockBuilder('\OC\User\User')
			->disableOriginalConstructor()
			->getMock();
		$userObject->method('getHome')->willReturn($path . '/' . $uid);
		$userObject->method('getUID')->willReturn($uid);
		$this->userManager->method('search')->willReturn([$uid => $userObject]);

		$this->commandTester->execute(['--all' => true]);
		$output = $this->commandTester->getDisplay();
		$this->assertSame("  - $path:\n    - $uid\n", $output);
	}

	public function testCommandInputBoth() {
		$this->commandTester->execute(['--all' => true, 'path' => '/some/path']);
		$output = $this->commandTester->getDisplay();
		$this->assertStringContainsString('--all and path option cannot be given together', $output);
	}

	public function testCommandInputNone() {
		$this->commandTester->execute([]);
		$output = $this->commandTester->getDisplay();
		$this->assertStringContainsString('Not enough arguments (missing: "path").', $output);
	}
}
