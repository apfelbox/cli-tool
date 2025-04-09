<?php declare(strict_types=1);

namespace App\Command;

use App\Console\CliStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
				"www",
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
			if ($isApexDomain && $input->getOption("www"))
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

			foreach ($this->fetchRecordsAsTableRow($domain, $dnsType) as $row)
			{
				$rows[] = $row;
			}
		}

		$progress->finish();
		$io->newLine();

		$io->table([
			"Domain",
			new TableCell("DNS", ["colspan" => 2]),
			"TTL",
		], $rows);

		return self::SUCCESS;
	}

	/**
	 *
	 */
	private function fetchRecordsAsTableRow (string $domain, int $type) : array
	{
		$rows = $this->renderDnsResultsAsRows($domain, $type);
		$result = [];

		$result[] = [
			new TableCell($domain, ["rowspan" => count($rows)]),
			...$rows[0],
		];

		for ($i = 1; $i < count($rows); ++$i)
		{
			$result[] = [
				...$rows[$i],
			];
		}

		return $result;
	}

	/**
	 *
	 */
	private function renderDnsResultsAsRows (string $domain, int $type) : array
	{
		$records = @dns_get_record($domain, $type);

		if (false === $records || count($records) === 0)
		{
			return [
				[
					new TableCell(
						"<fg=gray>n/a</>",
						["colspan" => 3]
					),
					]
			];
		}

		$result = [];

		foreach ($records as $record)
		{
			$result[] = [
				sprintf("<fg=yellow>%s</>", $record["type"]),
				$this->formatIp($record["ip"] ?? null),
				sprintf("<fg=gray>%d</>", $record["ttl"]),
			];
		}

		return $result;
	}

	/**
	 *
	 */
	private function formatIp (?string $ip) : string
	{
		return match ($ip)
		{
			"213.143.195.2",
			"94.186.156.122" => sprintf("21TORR (<fg=gray>%s</>)", $ip),

			"35.246.248.138",
			"35.246.184.45",
			"35.242.229.239" => sprintf("Platform.sh DE (<fg=gray>%s</>)", $ip),

			"13.51.62.86" => sprintf("Platform.sh SE (<fg=gray>%s</>)", $ip),

			null => "<fg=red>n/a</>",
			default => $ip,
		};
	}
}
