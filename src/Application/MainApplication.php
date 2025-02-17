<?php declare(strict_types=1);

namespace App\Application;

use Symfony\Component\Console\Application;

/**
 * @final
 */
class MainApplication extends Application
{
	/**
	 *
	 */
	public function __construct (
		iterable $commands = [],
	)
	{
		parent::__construct("tool", "1.0");

		foreach ($commands as $command)
		{
			$this->add($command);
		}
	}

}
