<?php

/**
 * Routines to assist in the installation of various parts of QCubed with composer.
 *
 */

namespace QCubed\Composer;

$__CONFIG_ONLY__ = true;

class Installer extends \Composer\Installers\BaseInstaller
{
	protected $locations = array(
		'plugin' => '{$vendor}/plugin/{$name}/',
	);
}
