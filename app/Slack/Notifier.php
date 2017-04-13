<?php declare(strict_types = 1);

namespace Pd\Monitoring\Slack;

class Notifier
{

	/**
	 * @var string
	 */
	private $hookUrl;

	/**
	 * @var \Monolog\Logger
	 */
	private $logger;

	/**
	 * @var \Kdyby\Clock\IDateTimeProvider
	 */
	private $dateTimeProvider;


	public function __construct(
		string $hookUrl,
		\Monolog\Logger $logger,
		\Kdyby\Clock\IDateTimeProvider $dateTimeProvider
	) {
		$this->hookUrl = $hookUrl;
		$this->logger = $logger;
		$this->dateTimeProvider = $dateTimeProvider;
	}


	public function notify(string $message, string $color)
	{
		$payload = [
			'attachments' => [
				[
					'text' => $message,
					'color' => $color,
					'ts' => $this->dateTimeProvider->getDateTime()->format('U'),
				],
			],
		];

		$options = [
			'json' => $payload,
		];

		try {
			$client = new \GuzzleHttp\Client();
			$client->request('POST', $this->hookUrl, $options);
		} catch (\Throwable $e) {
			$this->logger->addError($e);
		}
	}
}
