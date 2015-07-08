<?php

/**
 * Routines to assist in the installation of various parts of QCubed with composer.
 *
 */

namespace QCubed\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

$__CONFIG_ONLY__ = true;

class Installer extends LibraryInstaller
{
	/** Overrides **/
	public function supports($packageType)
	{
		return ('qcubed-plugin' === $packageType ||
			'qcubed-framework' === $packageType);
	}

	public function getPackageBasePath(PackageInterface $package)
	{
		$parts = explode('_', $package->getName());

		if ('qcubed/plugin' === $parts[0]) {
			$this->initializeVendorDir();
			return ($this->vendorDir ? $this->vendorDir . '/' : '') . 'qcubed/plugin/' . $parts[1];
		} else {
			return parent::getPackageBasePath($package);
		}
	}

	public function install(InstalledRepositoryInterface $repo, PackageInterface $package) {
		parent::install($repo, $package);

		$strPackageName = $package->getName();

		if (self::startsWith($strPackageName, 'qcubed/plugin')) {
			$this->composerPluginInstall($package);
		}
		elseif (self::startsWith($strPackageName, 'qcubed/framework')) {
			// updating the framework
			$this->composerFrameworkInstall($package);
		}

	}

	/**
	 * Move files out of the vendor directory and into the project directory that are in the plugin's install directory.
	 * @param $strPackageName
	 */
	protected function composerPluginInstall ($package) {
		require_once(($this->vendorDir ? $this->vendorDir . '/' : '') . 'qcubed/framework/qcubed.inc.php');	// get the configuration options so we can know where to put the plugin files

		// recursively copy the contents of the install subdirectory in the plugin.
		$strPluginDir = $this->getPackageBasePath($package);
		$strInstallDir = $strPluginDir . '/install';
		$strDestDir = __INCLUDES__ . '/plugins';

		$this->filesystem->ensureDirectoryExists($strDestDir);
		$this->io->write('Copying files from ' . $strInstallDir . ' to ' . $strDestDir);
		self::copy_dir($strInstallDir, $strDestDir);
	}

	/**
	 * First time installation of framework. For first-time installation, we create the project directory and modify
	 * the configuration file.
	 *
	 * @param $strPackageName
	 */
	protected function composerFrameworkInstall ($package) {

		$extra = $package->getExtra();
		// recursively copy the contents of the install directory, providing each file is not there.
		$strInstallDir = $this->getPackageBasePath($package) . '/install/project';
		$strDestDir = realpath(($this->vendorDir ? $this->vendorDir . '/' : '') .'../project');

		$this->io->write('Copying files from ' . $strInstallDir . ' to ' . $strDestDir);
		self::copy_dir($strInstallDir, $strDestDir);

		// Make sure particular directories are writable by the web server. These are listed in the extra section of the composer.json file.
		// We are assuming that the first time installation is installed in a subdirectory of docroot.
		$strInstallDir = realpath(dirname($strDestDir));
		$strSubDirectory = '/' . basename($strInstallDir);
		$strDocRoot = realpath ($strInstallDir . '/../');
		$strConfigDirectory = $strDestDir . '/includes/configuration';

		$this->io->write('Updating permissions');
		foreach ($extra['writePermission'] as $strDir) {
			chmod ($strInstallDir . '/' . $strDir, 0777);
		}

		// fix up the configuration file
		$strFile = file_get_contents($strConfigDirectory . '/configuration.inc.sample.php');
		if ($strFile) {
			$strFile = str_replace (['{docroot}', '{vd}', '{subdir}'], [$strDocRoot, '', $strSubDirectory], $strFile);
			file_put_contents($strConfigDirectory . '/configuration.inc.php', $strFile);
		}
	}

	public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target) {
		parent::install($repo, $initial, $target);

		$strPackageName = $target->getName();
		if (self::startsWith($strPackageName, 'qcubed/plugin')) {
			$this->composerPluginInstall($target);
		}
		elseif (self::startsWith($strPackageName, 'qcubed/framework')) {
			// updating the framework
			$this->composerFrameworkUpdate($target);
		}

	}

	protected static function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
	}

	protected function composerFrameworkUpdate ($package) {
		require_once(($this->vendorDir ? $this->vendorDir . '/' : '') . 'qcubed/framework/qcubed.inc.php');	// get the configuration options so we can know where to put the plugin files

		// recursively copy the contents of the install directory, providing each file is not there.
		$strInstallDir = $this->getPackageBasePath($package) . '/install/project';
		$strDestDir = __PROJECT__;

		// copy_dir will not overwrite files, but will add any new stub files
		$this->io->write('Copying files from ' . $strInstallDir . ' to ' . $strDestDir);
		self::copy_dir($strInstallDir, $strDestDir);
	}

	public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		$strPackageName = $package->getName();
		if (self::startsWith($strPackageName, 'qcubed/plugin')) {
			$this->composerPluginUninstall($package);
		}
		parent::uninstall ($repo, $package);
	}


	protected static function copy_dir($src,$dst) {
		$dir = opendir($src);

		if (!file_exists($dst)) {
			mkdir($dst);
		}
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($src . '/' . $file) ) {
					self::copy_dir($src . '/' . $file,$dst . '/' . $file);
				}
				else {
					if (!file_exists($dst . '/' . $file)) {
						copy($src . '/' . $file,$dst . '/' . $file);
					}
				}
			}
		}
		closedir($dir);
	}

	public function composerPluginUninstall ($package) {
		require_once(($this->vendorDir ? $this->vendorDir . '/' : '') . 'qcubed/framework/qcubed.inc.php');	// get the configuration options so we can know where to put the plugin files

		// recursively delete the contents of the install directory, providing each file is there.
		$strPluginDir = $this->getPackageBasePath($package) . '/install';
		$strDestDir = __INCLUDES__ . '/plugins';

		$this->io->write('Removing files from ' . $strPluginDir);
		self::remove_matching_dir($strPluginDir, $strDestDir);
	}

	protected static function remove_matching_dir($src,$dst) {
		if (!$dst || !$src) return;	// prevent deleting an entire disk by accidentally calling this with an empty string!
		$dir = opendir($src);

		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($src . '/' . $file) ) {
					self::remove_dir($dst . '/' . $file);
				}
				else {
					if (file_exists($dst . '/' . $file)) {
						unlink($dst . '/' . $file);
					}
				}
			}
		}
		closedir($dir);
	}

	protected static function remove_dir($dst) {
		if (!$dst) return;	// prevent deleting an entire disk by accidentally calling this with an empty string!
		$dir = opendir($dst);

		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($dst . '/' . $file) ) {
					self::remove_dir($dst . '/' . $file);
				}
				else {
					unlink($dst . '/' . $file);
				}
			}
		}
		closedir($dir);
		rmdir($dst);
	}


}
