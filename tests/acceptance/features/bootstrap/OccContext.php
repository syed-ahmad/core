<?php
/**
 * ownCloud
 *
 * @author Phil Davis <phil@jankaritech.com>
 * @copyright Copyright (c) 2019, ownCloud GmbH
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License,
 * as published by the Free Software Foundation;
 * either version 3 of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;
use TestHelpers\SetupHelper;

require_once 'bootstrap.php';

/**
 * Occ context for test steps that test occ commands
 */
class OccContext implements Context {

	/**
	 *
	 * @var FeatureContext
	 */
	private $featureContext;

	/**
	 *
	 * @var ImportedCertificates
	 */
	private $importedCertificates = [];

	/**
	 *
	 * @var RemovedCertificates
	 */
	private $removedCertificates = [];

	/**
	 * @var string lastDeletedJobId
	 */
	private $lastDeletedJobId;

	/**
	 * @var boolean techPreviewEnabled
	 */
	private $techPreviewEnabled = false;

	/**
	 * @var string initialTechPreviewStatus
	 */
	private $initialTechPreviewStatus;

	/**
	 * @return boolean
	 */
	public function isTechPreviewEnabled() {
		return $this->techPreviewEnabled;
	}

	/**
	 * @return boolean
	 * @throws Exception
	 */
	public function enableDAVTechPreview() {
		if (!$this->isTechPreviewEnabled()) {
			$this->addSystemConfigKeyUsingTheOccCommand(
				"dav.enable.tech_preview", "true", "boolean"
			);
			$this->techPreviewEnabled = true;
			return true;
		}
		return false;
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function disableDAVTechPreview() {
		$this->deleteSystemConfigKeyUsingTheOccCommand(
			"dav.enable.tech_preview"
		);
		$this->techPreviewEnabled = false;
	}

	/**
	 * @param string $cmd
	 *
	 * @return void
	 * @throws Exception
	 */
	public function invokingTheCommand($cmd) {
		$this->featureContext->runOcc([$cmd]);
	}

	/**
	 * @param string $path
	 *
	 * @return void
	 * @throws Exception
	 */
	public function importSecurityCertificateFromPath($path) {
		$this->invokingTheCommand("security:certificates:import " . $path);
		$pathComponents = \explode("/", $path);
		$certificate = \end($pathComponents);
		\array_push($this->importedCertificates, $certificate);
	}

	/**
	 * @param string $cmd
	 * @param string $envVariableName
	 * @param string $envVariableValue
	 *
	 * @return void
	 * @throws Exception
	 */
	public function invokingTheCommandWithEnvVariable(
		$cmd, $envVariableName, $envVariableValue
	) {
		$args = [$cmd];
		$this->featureContext->runOccWithEnvVariables(
			$args, [$envVariableName => $envVariableValue]
		);
	}

	/**
	 * @param string $mode
	 *
	 * @return void
	 * @throws Exception
	 */
	public function changeBackgroundJobsModeUsingTheOccCommand($mode) {
		$this->invokingTheCommand("background:$mode");
	}

	/**
	 * @param string $mountPoint
	 * @param boolean $setting
	 *
	 * @return void
	 * @throws Exception
	 */
	public function setExtStorageReadOnlyUsingTheOccCommand($mountPoint, $setting = true) {
		$command = "files_external:option";

		$mountId = $this->featureContext->getStorageId($mountPoint);

		$key = "read_only";

		if ($setting) {
			$value = "1";
		} else {
			$value = "0";
		}

		$this->invokingTheCommand(
			"$command $mountId $key $value"
		);
	}

	/**
	 * @param string $mountPoint
	 * @param string $setting "never" (switch it off) otherwise "Once every direct access"
	 *
	 * @return void
	 * @throws Exception
	 */
	public function setExtStorageCheckChangesUsingTheOccCommand($mountPoint, $setting) {
		$command = "files_external:option";

		$mountId = $this->featureContext->getStorageId($mountPoint);

		$key = "filesystem_check_changes";

		if ($setting === "never") {
			$value = "0";
		} else {
			$value = "1";
		}

		$this->invokingTheCommand(
			"$command $mountId $key $value"
		);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function scanFileSystemForAllUsersUsingTheOccCommand() {
		$this->invokingTheCommand(
			"files:scan --all"
		);
	}

	/**
	 * @param string $user
	 *
	 * @return void
	 * @throws Exception
	 */
	public function scanFileSystemForAUserUsingTheOccCommand($user) {
		$this->invokingTheCommand(
			"files:scan $user"
		);
	}

	/**
	 * @param string $path
	 *
	 * @return void
	 * @throws Exception
	 */
	public function scanFileSystemPathUsingTheOccCommand($path) {
		$this->invokingTheCommand(
			"files:scan --path='$path'"
		);
	}

	/**
	 * @param string $group
	 *
	 * @return void
	 * @throws Exception
	 */
	public function scanFileSystemForAGroupUsingTheOccCommand($group) {
		$this->invokingTheCommand(
			"files:scan --group=$group"
		);
	}

	/**
	 * @param string $groups
	 *
	 * @return void
	 * @throws Exception
	 */
	public function scanFileSystemForGroupsUsingTheOccCommand($groups) {
		$this->invokingTheCommand(
			"files:scan --groups=$groups"
		);
	}

	/**
	 * @param string $mount
	 *
	 * @return void
	 */
	public function createLocalStorageMountUsingTheOccCommand($mount) {
		$result = SetupHelper::createLocalStorageMount($mount);
		$storageId = $result['storageId'];
		$this->featureContext->setResultOfOccCommand($result);
		$this->featureContext->addStorageId($mount, $storageId);
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @param string $app
	 *
	 * @return void
	 * @throws Exception
	 */
	public function addConfigKeyWithValueInAppUsingTheOccCommand($key, $value, $app) {
		$this->invokingTheCommand(
			"config:app:set --value ${value} ${app} ${key}"
		);
	}

	/**
	 * @param string $key
	 * @param string $app
	 *
	 * @return void
	 * @throws Exception
	 */
	public function deleteConfigKeyOfAppUsingTheOccCommand($key, $app) {
		$this->invokingTheCommand(
			"config:app:delete ${app} ${key}"
		);
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @param string $type
	 *
	 * @return void
	 * @throws Exception
	 */
	public function addSystemConfigKeyUsingTheOccCommand(
		$key, $value, $type = "string"
	) {
		$this->invokingTheCommand(
			"config:system:set --value ${value} --type ${type} ${key}"
		);
	}

	/**
	 * @param string $key
	 *
	 * @return void
	 * @throws Exception
	 */
	public function deleteSystemConfigKeyUsingTheOccCommand($key) {
		$this->invokingTheCommand(
			"config:system:delete ${key}"
		);
	}

	/**
	 * @param string $user
	 *
	 * @return void
	 * @throws Exception
	 */
	public function emptyTrashBinOfUserUsingOccCommand($user) {
		$this->invokingTheCommand(
			"trashbin:cleanup $user"
		);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function getAllJobsInBackgroundQueueUsingOccCommand() {
		$this->invokingTheCommand(
			"background:queue:status"
		);
	}

	/**
	 * @param string $user
	 *
	 * @return void
	 * @throws Exception
	 */
	public function deleteAllVersionsForUserUsingOccCommand($user) {
		$this->invokingTheCommand(
			"versions:cleanup $user"
		);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function deleteAllVersionsForAllUsersUsingTheOccCommand() {
		$this->invokingTheCommand(
			"versions:cleanup"
		);
	}

	/**
	 * @param string $job
	 *
	 * @return void
	 * @throws Exception
	 */
	public function deleteLastBackgroundJobUsingTheOccCommand($job) {
		$match = $this->getLastJobIdForJob($job);
		if ($match === false) {
			throw new \Exception("Couldn't find jobId for given job: $job");
		}
		$this->invokingTheCommand(
			"background:queue:delete $match"
		);
		$this->lastDeletedJobId = $match;
	}

	/**
	 * List created local storage mount
	 *
	 * @return void
	 * @throws Exception
	 */
	public function listLocalStorageMount() {
		$this->invokingTheCommand('files_external:list --output=json');
	}

	/**
	 * @When the administrator enables DAV tech_preview
	 *
	 * @return void true if DAV Tech Preview was disabled and had to be enabled
	 * @throws Exception
	 */
	public function theAdministratorEnablesDAVTechPreview() {
		$this->enableDAVTechPreview();
	}

	/**
	 * @Given the administrator has enabled DAV tech_preview
	 *
	 * @return void true if DAV Tech Preview was disabled and had to be enabled
	 * @throws Exception
	 */
	public function theAdministratorHasEnabledDAVTechPreview() {
		$this->enableDAVTechPreview();
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator disables DAV tech_preview
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorDisablesDAVTechPreview() {
		$this->disableDAVTechPreview();
	}

	/**
	 * @Given the administrator has disabled DAV tech_preview
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasDisabledDAVTechPreview() {
		$this->disableDAVTechPreview();
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When /^the administrator invokes occ command "([^"]*)"$/
	 *
	 * @param string $cmd
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorInvokesOccCommand($cmd) {
		$this->invokingTheCommand($cmd);
	}

	/**
	 * @Given /^the administrator has invoked occ command "([^"]*)"$/
	 *
	 * @param string $cmd
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasInvokedOccCommand($cmd) {
		$this->invokingTheCommand($cmd);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator imports security certificate from the path :path
	 *
	 * @param string $path
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorImportsSecurityCertificateFromThePath($path) {
		$this->importSecurityCertificateFromPath($path);
	}

	/**
	 * @Given the administrator has imported security certificate from the path :path
	 *
	 * @param string $path
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasImportedSecurityCertificateFromThePath($path) {
		$this->importSecurityCertificateFromPath($path);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator removes the security certificate :certificate
	 *
	 * @param string $certificate
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorRemovesTheSecurityCertificate($certificate) {
		$this->invokingTheCommand("security:certificates:remove " . $certificate);
		\array_push($this->removedCertificates, $certificate);
	}

	/**
	 * @When /^the administrator invokes occ command "([^"]*)" with environment variable "([^"]*)" set to "([^"]*)"$/
	 *
	 * @param string $cmd
	 * @param string $envVariableName
	 * @param string $envVariableValue
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorInvokesOccCommandWithEnvironmentVariable(
		$cmd, $envVariableName, $envVariableValue
	) {
		$this->invokingTheCommandWithEnvVariable(
			$cmd,
			$envVariableName,
			$envVariableValue
		);
	}

	/**
	 * @Given /^the administrator has invoked occ command "([^"]*)" with environment variable "([^"]*)" set to "([^"]*)"$/
	 *
	 * @param string $cmd
	 * @param string $envVariableName
	 * @param string $envVariableValue
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasInvokedOccCommandWithEnvironmentVariable(
		$cmd, $envVariableName, $envVariableValue
	) {
		$this->invokingTheCommandWithEnvVariable(
			$cmd,
			$envVariableName,
			$envVariableValue
		);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator runs upgrade routines on local server using the occ command
	 *
	 * @return void
	 */
	public function theAdministratorRunsUpgradeRoutinesOnLocalServerUsingTheOccCommand() {
		\system("./occ upgrade", $status);
		if ($status !== 0) {
			// if the above command fails make sure to turn off maintenance mode
			\system("./occ maintenance:mode --off");
		}
	}

	/**
	 * @Then /^the command should have been successful$/
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theCommandShouldHaveBeenSuccessful() {
		$exceptions = $this->featureContext->findExceptions();
		$exitStatusCode = $this->featureContext->getExitStatusCodeOfOccCommand();
		if ($exitStatusCode !== 0) {
			$msg = "The command was not successful, exit code was " .
				$exitStatusCode . ".\n" .
				"stdOut was: '" .
				$this->featureContext->getStdOutOfOccCommand() . "'\n" .
				"stdErr was: '" .
				$this->featureContext->getStdErrOfOccCommand() . "'\n";
			if (!empty($exceptions)) {
				$msg .= ' Exceptions: ' . \implode(', ', $exceptions);
			}
			throw new \Exception($msg);
		} elseif (!empty($exceptions)) {
			$msg = 'The command was successful but triggered exceptions: '
				. \implode(', ', $exceptions);
			throw new \Exception($msg);
		}
	}

	/**
	 * @Then /^the command should have failed with exit code ([0-9]+)$/
	 *
	 * @param int $exitCode
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theCommandFailedWithExitCode($exitCode) {
		$exitStatusCode = $this->featureContext->getExitStatusCodeOfOccCommand();
		if ($exitStatusCode !== (int)$exitCode) {
			throw new \Exception(
				"The command was expected to fail with exit code $exitCode but got "
				. $exitStatusCode
			);
		}
	}

	/**
	 * @Then /^the command should have failed with exception text "([^"]*)"$/
	 *
	 * @param string $exceptionText
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theCommandFailedWithExceptionText($exceptionText) {
		$exceptions = $this->featureContext->findExceptions();
		if (empty($exceptions)) {
			throw new \Exception('The command did not throw any exceptions');
		}

		if (!\in_array($exceptionText, $exceptions)) {
			throw new \Exception(
				"The command did not throw any exception with the text '$exceptionText'"
			);
		}
	}

	/**
	 * @Then /^the command output should contain the text ((?:'[^']*')|(?:"[^"]*"))$/
	 *
	 * @param string $text
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theCommandOutputContainsTheText($text) {
		// The capturing group of the regex always includes the quotes at each
		// end of the captured string, so trim them.
		$text = \trim($text, $text[0]);
		$commandOutput = $this->featureContext->getStdOutOfOccCommand();
		$lines = $this->featureContext->findLines(
			$commandOutput,
			$text
		);
		Assert::assertGreaterThanOrEqual(
			1,
			\count($lines),
			"The command output did not contain the expected text on stdout '$text'\n" .
			"The command output on stdout was:\n" .
			$commandOutput
		);
	}

	/**
	 * @Then /^the command error output should contain the text ((?:'[^']*')|(?:"[^"]*"))$/
	 *
	 * @param string $text
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theCommandErrorOutputContainsTheText($text) {
		// The capturing group of the regex always includes the quotes at each
		// end of the captured string, so trim them.
		$text = \trim($text, $text[0]);
		$commandOutput = $this->featureContext->getStdErrOfOccCommand();
		$lines = $this->featureContext->findLines(
			$commandOutput,
			$text
		);
		Assert::assertGreaterThanOrEqual(
			1,
			\count($lines),
			"The command output did not contain the expected text on stderr '$text'\n" .
			"The command output on stderr was:\n" .
			$commandOutput
		);
	}

	/**
	 * @Then the occ command JSON output should be empty
	 *
	 * @return void
	 */
	public function theOccCommandJsonOutputShouldNotReturnAnyData() {
		Assert::assertEquals(
			\trim($this->featureContext->getStdOutOfOccCommand()),
			"[]"
		);
		Assert::assertEmpty(
			$this->featureContext->getStdErrOfOccCommand()
		);
	}

	/**
	 * @Given the administrator has set the default folder for received shares to :folder
	 *
	 * @param string $folder
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasSetTheDefaultFolderForReceivedSharesTo($folder) {
		$this->addSystemConfigKeyUsingTheOccCommand(
			"share_folder", $folder
		);
	}

	/**
	 * @Given the administrator has set the mail smtpmode to :smtpmode
	 *
	 * @param string $smtpmode
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasSetTheMailSmtpmodeTo($smtpmode) {
		$this->addSystemConfigKeyUsingTheOccCommand(
			"mail_smtpmode", $smtpmode
		);
	}

	/**
	 * @When the administrator sets the log level to :level using the occ command
	 *
	 * @param string $level
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorSetsLogLevelUsingTheOccCommand($level) {
		$this->invokingTheCommand(
			"log:manage --level $level"
		);
	}

	/**
	 * @When the administrator sets the timezone to :timezone using the occ command
	 *
	 * @param string $timezone
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorSetsTimeZoneUsingTheOccCommand($timezone) {
		$this->invokingTheCommand(
			"log:manage --timezone $timezone"
		);
	}

	/**
	 * @When the administrator sets the backend to :backend using the occ command
	 *
	 * @param string $backend
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorSetsBackendUsingTheOccCommand($backend) {
		$this->invokingTheCommand(
			"log:manage --backend $backend"
		);
	}

	/**
	 * @When the administrator enables the ownCloud backend using the occ command
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorEnablesOwnCloudBackendUsingTheOccCommand() {
		$this->invokingTheCommand(
			"log:owncloud --enable"
		);
	}

	/**
	 * @When the administrator sets the log file path to :path using the occ command
	 *
	 * @param string $path
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorSetsLogFilePathUsingTheOccCommand($path) {
		$this->invokingTheCommand(
			"log:owncloud --file $path"
		);
	}

	/**
	 * @When the administrator sets the log rotate file size to :size using the occ command
	 *
	 * @param string $size
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorSetsLogRotateFileSizeUsingTheOccCommand($size) {
		$this->invokingTheCommand(
			"log:owncloud --rotate-size $size"
		);
	}

	/**
	 * @When the administrator changes the background jobs mode to :mode using the occ command
	 *
	 * @param string $mode
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorChangesTheBackgroundJobsModeTo($mode) {
		$this->changeBackgroundJobsModeUsingTheOccCommand($mode);
	}

	/**
	 * @Given the administrator has changed the background jobs mode to :mode
	 *
	 * @param string $mode
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasChangedTheBackgroundJobsModeTo($mode) {
		$this->changeBackgroundJobsModeUsingTheOccCommand($mode);
	}

	/**
	 * @When the administrator sets the external storage :mountPoint to read-only using the occ command
	 *
	 * @param string $mountPoint
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdminSetsTheExtStorageToReadOnly($mountPoint) {
		$this->setExtStorageReadOnlyUsingTheOccCommand($mountPoint);
	}

	/**
	 * @Given the administrator has set the external storage :mountPoint to read-only
	 *
	 * @param string $mountPoint
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdminHasSetTheExtStorageToReadOnly($mountPoint) {
		$this->setExtStorageReadOnlyUsingTheOccCommand($mountPoint);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator sets the external storage :mountPoint to be never scanned automatically using the occ command
	 *
	 * @param string $mountPoint
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdminSetsTheExtStorageToBeNeverScannedAutomatically($mountPoint) {
		$this->setExtStorageCheckChangesUsingTheOccCommand($mountPoint, "never");
	}

	/**
	 * @Given the administrator has set the external storage :mountPoint to be never scanned automatically
	 *
	 * @param string $mountPoint
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdminHasSetTheExtStorageToBeNeverScannedAutomatically($mountPoint) {
		$this->setExtStorageCheckChangesUsingTheOccCommand($mountPoint, "never");
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator scans the filesystem for all users using the occ command
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorScansTheFilesystemForAllUsersUsingTheOccCommand() {
		$this->scanFileSystemForAllUsersUsingTheOccCommand();
	}

	/**
	 * @Given the administrator has scanned the filesystem for all users
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasScannedTheFilesystemForAllUsersUsingTheOccCommand() {
		$this->scanFileSystemForAllUsersUsingTheOccCommand();
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator scans the filesystem for user :user using the occ command
	 *
	 * @param string $user
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorScansTheFilesystemForUserUsingTheOccCommand($user) {
		$this->scanFileSystemForAUserUsingTheOccCommand($user);
	}

	/**
	 * @Given the administrator has scanned the filesystem for user :user
	 *
	 * @param string $user
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasScannedTheFilesystemForUserUsingTheOccCommand($user) {
		$this->scanFileSystemForAUserUsingTheOccCommand($user);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator scans the filesystem in path :path using the occ command
	 *
	 * @param string $path
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorScansTheFilesystemInPathUsingTheOccCommand($path) {
		$this->scanFileSystemPathUsingTheOccCommand($path);
	}

	/**
	 * @Given the administrator scans the filesystem in path :path
	 *
	 * @param string $path
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasScannedTheFilesystemInPathUsingTheOccCommand($path) {
		$this->scanFileSystemPathUsingTheOccCommand($path);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator scans the filesystem for group :group using the occ command
	 *
	 * Used to test the --group option of the files:scan command
	 *
	 * @param string $group a single group name
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorScansTheFilesystemForGroupUsingTheOccCommand($group) {
		$this->scanFileSystemForAGroupUsingTheOccCommand($group);
	}

	/**
	 * @Given the administrator has scanned the filesystem for group :group
	 *
	 * Used to test the --group option of the files:scan command
	 *
	 * @param string $group a single group name
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasScannedTheFilesystemForGroupUsingTheOccCommand($group) {
		$this->scanFileSystemForAGroupUsingTheOccCommand($group);
	}

	/**
	 * @When the administrator scans the filesystem for groups list :groups using the occ command
	 *
	 * Used to test the --groups option of the files:scan command
	 *
	 * @param string $groups a comma-separated list of group names
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorScansTheFilesystemForGroupsUsingTheOccCommand($groups) {
		$this->scanFileSystemForGroupsUsingTheOccCommand($groups);
	}

	/**
	 * @Given the administrator has scanned the filesystem for groups list :groups
	 *
	 * Used to test the --groups option of the files:scan command
	 *
	 * @param string $groups a comma-separated list of group names
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasScannedTheFilesystemForGroupsUsingTheOccCommand($groups) {
		$this->scanFileSystemForGroupsUsingTheOccCommand($groups);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator cleanups the filesystem for all users using the occ command
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorCleanupsTheFilesystemForAllUsersUsingTheOccCommand() {
		$this->invokingTheCommand(
			"files:cleanup"
		);
	}

	/**
	 * @When the administrator creates the local storage mount :mount using the occ command
	 *
	 * @param string $mount
	 *
	 * @return void
	 */
	public function theAdministratorCreatesTheLocalStorageMountUsingTheOccCommand($mount) {
		$this->createLocalStorageMountUsingTheOccCommand($mount);
	}

	/**
	 * @Given the administrator has created the local storage mount :mount
	 *
	 * @param string $mount
	 *
	 * @return void
	 */
	public function theAdministratorHasCreatedTheLocalStorageMountUsingTheOccCommand($mount) {
		$this->createLocalStorageMountUsingTheOccCommand($mount);
	}

	/**
	 * @param $action
	 * @param $userOrGroup
	 * @param $userOrGroupName
	 * @param $mountName
	 *
	 * @return void
	 * @throws Exception
	 */
	public function addRemoveUserOrGroupToOrFromMount(
		$action, $userOrGroup, $userOrGroupName, $mountName
	) {
		if ($action === "adds" || $action === "added") {
			$action = "--add";
		} else {
			$action = "--remove";
		}
		if ($userOrGroup === "user") {
			$action = "$action-user";
		} else {
			$action = "$action-group";
		}
		$mountId = $this->featureContext->getStorageId($mountName);
		$this->featureContext->runOcc(
			[
				'files_external:applicable',
				$mountId,
				"$action ",
				"$userOrGroupName"
			]
		);
	}

	/**
	 * @param $action
	 * @param $userOrGroup
	 * @param $userOrGroupName
	 *
	 * @return void
	 * @throws Exception
	 */
	public function addRemoveUserOrGroupToOrFromLastLocalMount(
		$action, $userOrGroup, $userOrGroupName
	) {
		$storageIds = $this->featureContext->getStorageIds();
		Assert::assertGreaterThan(
			0,
			\count($storageIds),
			"addRemoveAsApplicableUserLastLocalMount no local mounts exist"
		);
		$lastMountName = \end($storageIds);
		$this->addRemoveUserOrGroupToOrFromMount(
			$action, $userOrGroup, $userOrGroupName, $lastMountName
		);
	}

	/**
	 * @When /^the administrator (adds|removes) (user|group) "([^"]*)" (?:as|from) the applicable (?:user|group) for the last local storage mount using the occ command$/
	 *
	 * @param string $action
	 * @param string $userOrGroup
	 * @param string $user
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theAdminAddsRemovesAsTheApplicableUserLastLocalMountUsingTheOccCommand(
		$action, $userOrGroup, $user
	) {
		$this->addRemoveUserOrGroupToOrFromLastLocalMount(
			$action,
			$userOrGroup,
			$user
		);
	}

	/**
	 * @Given /^the administrator has (added|removed) (user|group) "([^"]*)" (?:as|from) the applicable (?:user|group) for the last local storage mount$/
	 *
	 * @param string $action
	 * @param string $userOrGroup
	 * @param string $user
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theAdminHasAddedRemovedAsTheApplicableUserLastLocalMountUsingTheOccCommand(
		$action, $userOrGroup, $user
	) {
		$this->addRemoveUserOrGroupToOrFromLastLocalMount(
			$action,
			$userOrGroup,
			$user
		);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When /^the administrator (adds|removes) (user|group) "([^"]*)" (?:as|from) the applicable (?:user|group) for local storage mount "([^"]*)" using the occ command$/
	 *
	 * @param string $action
	 * @param string $userOrGroup
	 * @param string $user
	 * @param string $mount
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theAdminAddsRemovesAsTheApplicableUserForMountUsingTheOccCommand(
		$action, $userOrGroup, $user, $mount
	) {
		$this->addRemoveUserOrGroupToOrFromMount(
			$action,
			$userOrGroup,
			$user,
			$mount
		);
	}

	/**
	 * @Given /^the administrator has (added|removed) (user|group) "([^"]*)" (?:as|from) the applicable (?:user|group) for local storage mount "([^"]*)"$/
	 *
	 * @param string $action
	 * @param string $userOrGroup
	 * @param string $user
	 * @param string $mount
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theAdminHasAddedRemovedTheApplicableUserForMountUsingTheOccCommand(
		$action, $userOrGroup, $user, $mount
	) {
		$this->addRemoveUserOrGroupToOrFromMount(
			$action,
			$userOrGroup,
			$user,
			$mount
		);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator lists the local storage using the occ command
	 *
	 * @return void
	 * @throws Exception
	 */
	public function userListsLocalStorageMountUsingTheOccCommand() {
		$this->listLocalStorageMount();
	}

	/**
	 * @Then the following local storage should exist
	 *
	 * @param TableNode $mountPoints
	 *
	 * @return void
	 */
	public function theFollowingLocalStoragesShouldExist(TableNode $mountPoints) {
		$createdLocalStorage = [];
		$expectedLocalStorages = $mountPoints->getColumnsHash();
		$commandOutput = \json_decode($this->featureContext->getStdOutOfOccCommand());
		foreach ($commandOutput as $storageEntry) {
			$createdLocalStorage[$storageEntry->mount_id] = \ltrim($storageEntry->mount_point, '/');
		}
		foreach ($expectedLocalStorages as $expectedStorageEntry) {
			Assert::assertContains($expectedStorageEntry['localStorage'], $createdLocalStorage);
		}
	}

	/**
	 * @Then the following local storage should not exist
	 *
	 * @param TableNode $mountPoints
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theFollowingLocalStoragesShouldNotExist(TableNode $mountPoints) {
		$createdLocalStorage = [];
		$this->listLocalStorageMount();
		$expectedLocalStorages = $mountPoints->getColumnsHash();
		$commandOutput = \json_decode($this->featureContext->getStdOutOfOccCommand());
		foreach ($commandOutput as $i) {
			$createdLocalStorage[$i->mount_id] = \ltrim($i->mount_point, '/');
		}
		foreach ($expectedLocalStorages as $i) {
			Assert::assertNotContains($i['localStorage'], $createdLocalStorage);
		}
	}

	/**
	 * @Then the following local storage should be listed:
	 *
	 * @param TableNode $table
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theFollowingLocalStorageShouldBeListed(TableNode $table) {
		$expectedLocalStorages = $table->getColumnsHash();
		$commandOutput = \json_decode($this->featureContext->getStdOutOfOccCommand());
		foreach ($expectedLocalStorages as $expectedStorageEntry) {
			$isStorageEntryListed = false;
			foreach ($commandOutput as $listedStorageEntry) {
				if ($expectedStorageEntry["MountPoint"] === $listedStorageEntry->mount_point) {
					Assert::assertEquals($expectedStorageEntry['Storage'], $listedStorageEntry->storage);
					Assert::assertEquals($expectedStorageEntry['AuthenticationType'], $listedStorageEntry->authentication_type);
					Assert::assertStringStartsWith($expectedStorageEntry['Configuration'], $listedStorageEntry->configuration);
					Assert::assertEquals($expectedStorageEntry['Options'], $listedStorageEntry->options);
					Assert::assertEquals($expectedStorageEntry['ApplicableUsers'], $listedStorageEntry->applicable_users);
					Assert::assertEquals($expectedStorageEntry['ApplicableGroups'], $listedStorageEntry->applicable_groups);
					$isStorageEntryListed = true;
				}
			}
			if ($isStorageEntryListed === false) {
				throw new Exception("Expected local storages not found");
			}
		}
	}

	/**
	 * @When the administrator deletes local storage :folder using the occ command
	 *
	 * @param string $folder
	 *
	 * @return void
	 * @throws Exception
	 */
	public function administratorDeletesFolder($folder) {
		$createdLocalStorage = [];
		$this->listLocalStorageMount();
		$commandOutput = \json_decode($this->featureContext->getStdOutOfOccCommand());
		foreach ($commandOutput as $i) {
			$createdLocalStorage[$i->mount_id] = \ltrim($i->mount_point, '/');
		}
		foreach ($createdLocalStorage as $key => $value) {
			if ($value === $folder) {
				$mount_id = $key;
			}
		}
		if (!isset($mount_id)) {
			throw  new Exception("Id not found for folder to be deleted");
		}
		$this->invokingTheCommand('files_external:delete --yes ' . $mount_id);
	}

	/**
	 * @When the administrator list the repair steps using the occ command
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorListTheRepairStepsUsingTheOccCommand() {
		$this->invokingTheCommand('maintenance:repair --list');
	}

	/**
	 * @Then the background jobs mode should be :mode
	 *
	 * @param string $mode
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theBackgroundJobsModeShouldBe($mode) {
		$this->invokingTheCommand(
			"config:app:get core backgroundjobs_mode"
		);
		$lastOutput = $this->featureContext->getStdOutOfOccCommand();
		Assert::assertEquals($mode, \trim($lastOutput));
	}

	/**
	 * @Then the update channel should be :value
	 *
	 * @param string $value
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theUpdateChannelShouldBe($value) {
		$this->invokingTheCommand(
			"config:app:get core OC_Channel"
		);
		$lastOutput = $this->featureContext->getStdOutOfOccCommand();
		Assert::assertEquals($value, \trim($lastOutput));
	}

	/**
	 * @Then the log level should be :logLevel
	 *
	 * @param string $logLevel
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theLogLevelShouldBe($logLevel) {
		$this->invokingTheCommand(
			"config:system:get loglevel"
		);
		$lastOutput = $this->featureContext->getStdOutOfOccCommand();
		Assert::assertEquals($logLevel, \trim($lastOutput));
	}

	/**
	 * @When the administrator adds/updates config key :key with value :value in app :app using the occ command
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $app
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorAddsConfigKeyWithValueInAppUsingTheOccCommand($key, $value, $app) {
		$this->addConfigKeyWithValueInAppUsingTheOccCommand(
			$key,
			$value,
			$app
		);
	}

	/**
	 * @Given the administrator has added config key :key with value :value in app :app
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $app
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasAddedConfigKeyWithValueInAppUsingTheOccCommand($key, $value, $app) {
		$this->addConfigKeyWithValueInAppUsingTheOccCommand(
			$key,
			$value,
			$app
		);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator deletes config key :key of app :app using the occ command
	 *
	 * @param string $key
	 * @param string $app
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorDeletesConfigKeyOfAppUsingTheOccCommand($key, $app) {
		$this->deleteConfigKeyOfAppUsingTheOccCommand($key, $app);
	}

	/**
	 * @When the administrator adds/updates system config key :key with value :value using the occ command
	 * @When the administrator adds/updates system config key :key with value :value and type :type using the occ command
	 *
	 * @param string $key
	 * @param string $value
	 * @param boolean $type
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorAddsSystemConfigKeyWithValueUsingTheOccCommand(
		$key, $value, $type = "string"
	) {
		$this->addSystemConfigKeyUsingTheOccCommand(
			$key,
			$value,
			$type
		);
	}

	/**
	 * @Given the administrator has added/updated system config key :key with value :value
	 * @Given the administrator has added/updated system config key :key with value :value and type :type
	 *
	 * @param string $key
	 * @param string $value
	 * @param boolean $type
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasAddedSystemConfigKeyWithValueUsingTheOccCommand(
		$key, $value, $type = "string"
	) {
		$this->addSystemConfigKeyUsingTheOccCommand(
			$key,
			$value,
			$type
		);
		$this->theCommandShouldHaveBeenSuccessful();
	}

	/**
	 * @When the administrator deletes system config key :key using the occ command
	 *
	 * @param string $key
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorDeletesSystemConfigKeyUsingTheOccCommand($key) {
		$this->deleteSystemConfigKeyUsingTheOccCommand($key);
	}

	/**
	 * @When the administrator empties the trashbin of user :user using the occ command
	 *
	 * @param string $user
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorEmptiesTheTrashbinOfUserUsingTheOccCommand($user) {
		$this->emptyTrashBinOfUserUsingOccCommand($user);
	}

	/**
	 * @When the administrator deletes all the versions for user :user
	 *
	 * @param string $user
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorDeletesAllTheVersionsForUser($user) {
		$this->deleteAllVersionsForUserUsingOccCommand($user);
	}

	/**
	 * @When the administrator empties the trashbin of all users using the occ command
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorEmptiesTheTrashbinOfAllUsersUsingTheOccCommand() {
		$this->emptyTrashBinOfUserUsingOccCommand('');
	}

	/**
	 * @When the administrator gets all the jobs in the background queue using the occ command
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorGetsAllTheJobsInTheBackgroundQueueUsingTheOccCommand() {
		$this->getAllJobsInBackgroundQueueUsingOccCommand();
	}

	/**
	 * @When the administrator deletes last background job :job using the occ command
	 *
	 * @param string $job
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorDeletesLastBackgroundJobUsingTheOccCommand($job) {
		$this->deleteLastBackgroundJobUsingTheOccCommand($job);
	}

	/**
	 * @Then the last deleted background job :job should not be listed in the background jobs queue
	 *
	 * @param string $job
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theLastDeletedJobShouldNotBeListedInTheJobsQueue($job) {
		$jobId = $this->lastDeletedJobId;
		$match = $this->getLastJobIdForJob($job);
		Assert::assertNotEquals(
			$jobId, $match,
			"job $job with jobId $jobId" .
			" was not expected to be listed in background queue, but was"
		);
	}

	/**
	 * @Then system config key :key should have value :value
	 *
	 * @param string $key
	 * @param string $value
	 *
	 * @return void
	 * @throws Exception
	 */
	public function systemConfigKeyShouldHaveValue($key, $value) {
		$config = \trim($this->featureContext->getSystemConfigValue($key));
		Assert::assertSame($value, $config);
	}

	/**
	 * @Then the command output table should contain the following text:
	 *
	 * @param TableNode $table table of patterns to find with table title as 'table_column'
	 *
	 * @return void
	 */
	public function theCommandOutputTableShouldContainTheFollowingText(TableNode $table) {
		$commandOutput = $this->featureContext->getStdOutOfOccCommand();
		$this->featureContext->verifyTableNodeColumns($table, ['table_column']);
		foreach ($table as $row) {
			$lines = $this->featureContext->findLines(
				$commandOutput,
				$row['table_column']
			);
			Assert::assertNotEmpty(
				$lines,
				"Value: " . $row['table_column'] . " not found"
			);
		}
	}

	/**
	 * @Then system config key :key should not exist
	 *
	 * @param string $key
	 *
	 * @return void
	 * @throws Exception
	 */
	public function systemConfigKeyShouldNotExist($key) {
		Assert::assertEmpty($this->featureContext->getSystemConfig($key)['stdOut']);
	}

	/**
	 * @When the administrator lists the config keys
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorListsTheConfigKeys() {
		$this->invokingTheCommand(
			"config:list"
		);
	}

	/**
	 * @Then the command output should contain the apps configs
	 *
	 * @return void
	 */
	public function theCommandOutputShouldContainTheAppsConfigs() {
		$config_list = \json_decode($this->featureContext->getStdOutOfOccCommand(), true);
		Assert::assertArrayHasKey(
			'apps',
			$config_list,
			"The occ output does not contain apps configs"
		);
		Assert::assertNotEmpty(
			$config_list['apps'],
			"The occ output does not contain apps configs"
		);
	}

	/**
	 * @Then the command output should contain the system configs
	 *
	 * @return void
	 */
	public function theCommandOutputShouldContainTheSystemConfigs() {
		$config_list = \json_decode($this->featureContext->getStdOutOfOccCommand(), true);
		Assert::assertArrayHasKey(
			'system',
			$config_list,
			"The occ output does not contain system configs"
		);
		Assert::assertNotEmpty(
			$config_list['system'],
			"The occ output does not contain system configs"
		);
	}

	/**
	 * @Given the administrator has cleared the versions for user :user
	 *
	 * @param string $user
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasClearedTheVersionsForUser($user) {
		$this->deleteAllVersionsForUserUsingOccCommand($user);
		Assert::assertSame(
			"Delete versions of   $user",
			\trim($this->featureContext->getStdOutOfOccCommand())
		);
	}

	/**
	 * @Given the administrator has cleared the versions for all users
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasClearedTheVersionsForAllUsers() {
		$this->deleteAllVersionsForAllUsersUsingTheOccCommand();
		Assert::assertContains(
			"Delete all versions",
			\trim($this->featureContext->getStdOutOfOccCommand())
		);
	}

	/**
	 * get jobId of the latest job found of given job class
	 *
	 * @param string $job
	 *
	 * @return string|boolean
	 * @throws Exception
	 */
	public function getLastJobIdForJob($job) {
		$this->getAllJobsInBackgroundQueueUsingOccCommand();
		$commandOutput = $this->featureContext->getStdOutOfOccCommand();
		$lines = $this->featureContext->findLines(
			$commandOutput,
			$job
		);
		// find the jobId of the newest job among the jobs with given class
		$success = \preg_match("/\d+/", \end($lines), $match);
		if ($success) {
			return $match[0];
		}
		return false;
	}

	/**
	 * @Then the system config key :key from the last command output should match value :value of type :type
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $type
	 *
	 * @return void
	 */
	public function theSystemConfigKeyFromLastCommandOutputShouldContainValue(
		$key, $value, $type
	) {
		$configList = \json_decode(
			$this->featureContext->getStdOutOfOccCommand(), true
		);
		$systemConfig = $configList['system'];

		// convert the value to it's respective type based on type given in the type column
		if ($type === 'boolean') {
			$value = $value === 'true' ? true : false;
		} elseif ($type === 'integer') {
			$value = (int)$value;
		} elseif ($type === 'json') {
			// if the expected value of the key is a json
			// match the value with the regular expression
			$actualKeyValuePair = \json_encode(
				$systemConfig[$key], JSON_UNESCAPED_SLASHES
			);

			Assert::assertThat(
				$actualKeyValuePair,
				Assert::matchesRegularExpression($value)
			);
			return;
		}

		if (!\array_key_exists($key, $systemConfig)) {
			Assert::fail(
				"system config doesn't contain key: " . $key
			);
		}

		Assert::assertEquals(
			$value,
			$systemConfig[$key],
			"config: $key doesn't contain value: $value"
		);
	}

	/**
	 * @Given the administrator has enabled the external storage
	 *
	 * @return void
	 * @throws Exception
	 */
	public function enableExternalStorageUsingOccAsAdmin() {
		SetupHelper::runOcc(
			[
				'config:app:set',
				'core',
				'enable_external_storage',
				'--value=yes'
			],
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
		$response = SetupHelper::runOcc(
			[
				'config:app:get',
				'core',
				'enable_external_storage',
			],
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
		$status = \trim($response['stdOut']);
		Assert::assertEquals(
			'yes',
			$status
		);
	}

	/**
	 * @Given the administrator has added group :group to the exclude group from sharing list
	 *
	 * @param string $groups
	 * multiple groups can be passed as comma separated string
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasAddedGroupToTheExcludeGroupFromSharingList($groups) {
		$groups = \explode(',', \trim($groups));
		$groups = \array_map('trim', $groups); //removing whitespaces around group names
		$groups = '"' . \implode('","', $groups) . '"';
		SetupHelper::runOcc(
			[
				'config:app:set',
				'core',
				'shareapi_exclude_groups_list',
				"--value='[$groups]'"
			],
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
		$response = SetupHelper::runOcc(
			[
				'config:app:get',
				'core',
				'shareapi_exclude_groups_list'
			],
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
		$excludedGroupsFromResponse = (\trim($response['stdOut']));
		$excludedGroupsFromResponse = \trim($excludedGroupsFromResponse, '[]');
		Assert::assertEquals(
			$groups,
			$excludedGroupsFromResponse
		);
	}

	/**
	 * @Given the administrator has enabled exclude groups from sharing
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theAdministratorHasEnabledExcludeGroupsFromSharingUsingTheWebui() {
		SetupHelper::runOcc(
			[
				"config:app:set",
				"core",
				"shareapi_exclude_groups",
				"--value=yes"
			],
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
		$response = SetupHelper::runOcc(
			[
				"config:app:get",
				"core",
				"shareapi_exclude_groups"
			],
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
		$status = \trim($response['stdOut']);
		Assert::assertEquals(
			"yes",
			$status
		);
	}

	/**
	 * This will run after EVERY scenario.
	 * It will set the properties for this object.
	 *
	 * @AfterScenario
	 *
	 * @return void
	 * @throws Exception
	 */
	public function removeImportedCertificates() {
		$remainingCertificates = \array_diff($this->importedCertificates, $this->removedCertificates);
		foreach ($remainingCertificates as $certificate) {
			$this->invokingTheCommand("security:certificates:remove " . $certificate);
			$this->theCommandShouldHaveBeenSuccessful();
		}
	}

	/**
	 * This will run after EVERY scenario.
	 * It will set the properties for this object.
	 *
	 * @AfterScenario
	 *
	 * @return void
	 * @throws Exception
	 */
	public function resetDAVTechPreview() {
		if ($this->initialTechPreviewStatus === "") {
			$this->featureContext->deleteSystemConfig('dav.enable.tech_preview');
		} elseif ($this->initialTechPreviewStatus === 'true' && !$this->techPreviewEnabled) {
			$this->enableDAVTechPreview();
		} elseif ($this->initialTechPreviewStatus === 'false' && $this->techPreviewEnabled) {
			$this->disableDAVTechPreview();
		}
	}

	/**
	 * This will run before EVERY scenario.
	 * It will set the properties for this object.
	 *
	 * @BeforeScenario
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 * @throws Exception
	 */
	public function before(BeforeScenarioScope $scope) {
		// Get the environment
		$environment = $scope->getEnvironment();
		// Get all the contexts you need in this context
		$this->featureContext = $environment->getContext('FeatureContext');
		SetupHelper::init(
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
		$techPreviewEnabled = \trim(
			$this->featureContext->getSystemConfigValue('dav.enable.tech_preview')
		);
		$this->initialTechPreviewStatus = $techPreviewEnabled;
		$this->techPreviewEnabled = $techPreviewEnabled === 'true';
	}
}
