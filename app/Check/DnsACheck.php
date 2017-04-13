<?php declare(strict_types = 1);

namespace Pd\Monitoring\Check;

/**
 * @property string $url
 * @property string $ip
 * @property string|NULL $lastIp
 */
class DnsACheck extends Check
{

	public function __construct()
	{
		parent::__construct();
		$this->type = ICheck::TYPE_DNS_A;
	}


	protected function getStatus(): int
	{
		if ($this->lastIp === $this->ip) {
			return ICheck::STATUS_OK;
		} else {
			return ICheck::STATUS_ERROR;
		}
	}


	public function getTitle(): string
	{
		return 'Nastavení DNS A';
	}


	public function getterStatusMessage(): string
	{
		return $this->lastIp !== $this->ip ? sprintf('Očekávaná IP adresa "%s" neodpovídá zjištěné "%s"', $this->ip, $this->lastIp) : '';
	}
}
