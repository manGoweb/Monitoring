<?php declare(strict_types=1);

namespace Pd\Monitoring\Check\Consumers;

class DnsACheck extends Check
{

	/**
	 * @param \Pd\Monitoring\Check\Check|\Pd\Monitoring\Check\DnsACheck $check
	 * @return bool
	 */
	protected function doHardJob(\Pd\Monitoring\Check\Check $check): bool
	{
		$check->lastIp = NULL;

		$entries = dns_get_record($check->url, DNS_A);
		if (!$entries) {
			return FALSE;
		}

		$entry = array_shift($entries);
		$check->lastIp = $entry['ip'];
		return TRUE;
	}


	protected function getCheckType(): int
	{
		return \Pd\Monitoring\Check\ICheck::TYPE_DNS_A;
	}
}
