<?php
/**
 * @author Jan Ackermann <jackermann@owncloud.com>
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

use OC\Core\Command\User\Report;
use OC\Files\View;
use OCP\IConfig;
use OCP\App\IAppManager;
use OC\Helper\UserTypeHelper;
use OCP\IUserManager;
use OCP\User\Constants;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class ReportTest
 *
 * @group DB
 */
class ReportTest extends TestCase {
	/** @var CommandTester */
	private $commandTester;

	/** @var IUserManager */
	private $userManager;

	/** @var IConfig | MockObject */
	private $config;

	/** @var IAppManager | MockObject */
	private $appManager;

	protected function setUp(): void {
		parent::setUp();
		$userTypeHelper = new UserTypeHelper();

		$userManager = \OC::$server->getUserManager();
		$this->userManager = $userManager;

		$config = $this->createMock(IConfig::class);
		$this->config = $config;

		$appManager = $this->createMock(IAppManager::class);
		$this->appManager = $appManager;

		$command = new Report($userManager, $userTypeHelper, $this->config, $this->appManager);
		$command->setApplication(new Application());
		$this->commandTester = new CommandTester($command);

		$view = new View('');
		list($storage) = $view->resolvePath('');
		/** @var $storage Storage */

		/**
		 * Create some folders in the 'datadirectory'
		 * which should not be counted as user directories
		 */
		foreach (Constants::DIRECTORIES_THAT_ARE_NOT_USERS as $nonUserFolder) {
			$storage->mkdir($nonUserFolder);
		}

		// Login to create user directory
		$this->loginAsUser('admin');
	}

	public function objectStorageProvider(): array {
		return [
			[null, false],
			[null, true],
			[['objectstore' => []], false],
			[['objectstore' => []], true]
		];
	}

	/**
	 * @dataProvider objectStorageProvider
	 * @return void
	 */
	public function testCommandInput($objectStorage, $objectStorageAppEnabled) {
		$this->config->method('getSystemValue')->willReturn($objectStorage);
		$this->appManager->method('isEnabledForUser')->willReturn($objectStorageAppEnabled);

		$this->commandTester->execute([]);
		$output = $this->commandTester->getDisplay();

		$view = new View('');
		$storage = $view->getMount('/')->getStorage();
		$isLocalStorage = $storage->isLocal();

		if ($isLocalStorage && (!$objectStorage || !$objectStorageAppEnabled)) {
			$expectedOutput = <<<EOS
+------------------+---+
| User Report      |   |
+------------------+---+
| OC\User\Database | 1 |
|                  |   |
| guest users      | 0 |
|                  |   |
| total users      | 1 |
|                  |   |
| user directories | 1 |
+------------------+---+

EOS;
		} else if ($objectStorage && $objectStorageAppEnabled) {
			$expectedOutput = <<<EOS
+------------------+-------------------------------------------+
| User Report      |                                           |
+------------------+-------------------------------------------+
| OC\User\Database | 1                                         |
|                  |                                           |
| guest users      | 0                                         |
|                  |                                           |
| total users      | 1                                         |
|                  |                                           |
| user directories | not supported with primary object storage |
+------------------+-------------------------------------------+

EOS;
		}else {
			$expectedOutput = <<<EOS
+------------------+---+
| User Report      |   |
+------------------+---+
| OC\User\Database | 1 |
|                  |   |
| guest users      | 0 |
|                  |   |
| total users      | 1 |
|                  |   |
| user directories | 0 |
+------------------+---+

EOS;
		}

		$this->assertEquals($expectedOutput, $output);
	}
}
