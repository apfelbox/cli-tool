<?php declare(strict_types=1);

use App\Application\MainApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container) : void
{
	$services = $container->services()
		->defaults()
			->autowire()
			->autoconfigure()
			->instanceof(Command::class)
				->tag("console.command");

	$services->load('App\\', '../src');

	// must be public, as we run it in the main file
	$services->get(MainApplication::class)
		->arg('$commands', tagged_iterator("console.command"))
		->public();
};
