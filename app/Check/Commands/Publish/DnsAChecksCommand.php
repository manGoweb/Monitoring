<?php declare(strict_types = 1);

namespace Pd\Monitoring\Check\Commands\Publish;

class DnsAChecksCommand extends PublishChecksCommand
{

	protected function generateName(): string
	{
		return 'pd:monitoring:check:publish:dns-a-checks';
	}

	protected function getConditions(): array
	{
		return [
			'type' => \Pd\Monitoring\Check\ICheck::TYPE_DNS_A,
		];
	}

}
