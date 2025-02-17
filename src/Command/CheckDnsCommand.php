<?php declare(strict_types=1);

namespace App\Command;

use App\Console\CliStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * @final
 */
#[AsCommand(
	"check-dns",
	description: "Expects a file path to a TXT file containing one domain per line and returns the DNS entries"
)]
class CheckDnsCommand extends Command
{
	private const DNS_RECORD_TYPES = [
		"a" => \DNS_A,
		"aaaa" => \DNS_AAAA,
		"all" => \DNS_ALL,
		"cname" => \DNS_CNAME,
		"ns" => \DNS_NS,
		"txt" => \DNS_TXT,
	];

	/**
	 *
	 */
	#[\Override]
	protected function configure () : void
	{
		$this
			->addArgument(
				"filePath",
				mode: InputArgument::REQUIRED,
				description: "The path to the file containing the domains",
			)
			->addOption(
				"with-www",
				mode: InputOption::VALUE_NONE,
				description: "Automatically add www.* submdomains for all apex domains",
			)
			->addOption(
			"type",
				mode: InputOption::VALUE_REQUIRED,
				description: "The type of DNS record the check for",
				default: "A",
			);
	}


	/**
	 *
	 */
	#[\Override]
	protected function execute (InputInterface $input, OutputInterface $output) : int
	{
		$io = new CliStyle($input, $output);
		$io->title("Check DNS");

		$filePath = $input->getArgument("filePath");
		$dnsType = self::DNS_RECORD_TYPES[
			strtolower((string) $input->getOption("type"))
		] ?? null;

		if (null === $dnsType)
		{
			$io->error("Invalid DNS record type: {$input->getOption('type')}");
			return 1;
		}

		if (!is_file($filePath))
		{
			$io->error("File does not exist");
			return self::FAILURE;
		}

		$fileContents = @file_get_contents($filePath);
		$lines = explode("\n", $fileContents);
		$domains = [];

		foreach ($lines as $line)
		{
			$line = trim($line);

			if ("" === $line)
			{
				continue;
			}

			if (!preg_match("/^[a-z0-9_\\-]+(\\.[a-z0-9_\\-]+)+$/i", $line))
			{
				$io->error("Invalid domain name: {$line}");
				return self::FAILURE;
			}

			$domains[$line] = true;

			$isApexDomain = 1 === mb_substr_count($line, ".");
			if ($isApexDomain && $input->getOption("with-www"))
			{
				$domains["www.{$line}"] = true;
			}
		}

		uksort(
			$domains,
			static function (string $left, string $right) : int
			{
				$leftWithoutWWW = preg_replace("/^www\./i", "", $left);
				$rightWithoutWWW = preg_replace("/^www\./i", "", $right);

				// sort the www and non-www together, with the www at the bottom
				if ($leftWithoutWWW === $right)
				{
					return 1;
				}

				if ($rightWithoutWWW === $leftWithoutWWW)
				{
					return -1;
				}

				return strnatcasecmp($leftWithoutWWW, $rightWithoutWWW);
			}
		);

		$rows = [];
		$progress = $io->createProgressBar(count($domains));
		$progress->setFormat(" %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%");

		foreach ($domains as $domain => $_)
		{
			$progress->setMessage($domain);
			$progress->advance();

			$rows[] = [
				$domain,
				$this->fetchRecords($domain, $dnsType),
			];
		}

		$progress->finish();
		$io->newLine();

		$io->table(["Domain", "DNS"], $rows);

		return self::SUCCESS;
	}

	/**
	 *
	 */
	private function fetchRecords (string $domain, int $type) : string
	{
		$records = @dns_get_record($domain, $type);
		$result = [];

		if (false === $records)
		{
			return "<fg=gray>n/a</>";
		}

		foreach ($records as $record)
		{
			$result[] = sprintf(
				"<fg=yellow>%s</> %s <fg=gray>(TTL: %d)</>",
				$record["type"],
				$record["ip"] ?? "<fg=red>n/a</>",
				$record["ttl"],
			);
		}

		return implode("\n", $result);
	}

}
