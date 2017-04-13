<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Monolog\Processor;

use Kdyby\Monolog\Diagnostics\TracyLogger;
use Kdyby\Monolog\Diagnostics\TracyLoggerOld;
use Tracy\Debugger;



class TracyExceptionProcessor
{

	/**
	 * @var array
	 */
	private $processedExceptionFileNames = [];

	/**
	 * @var \Tracy\Logger
	 */
	private $tracyLogger;



	public function __construct($tracyDir)
	{
		if (version_compare(Debugger::VERSION, '2.3.3', '>=')) {
			$this->tracyLogger = new TracyLogger($tracyDir);
		} else {
			$this->tracyLogger = new TracyLoggerOld($tracyDir);
		}
	}



	public function __invoke(array $record)
	{
		if (isset($record['context']['tracy'])) {
			// already processed by MonologAdapter
			return $record;
		}

		if (isset($record['context']['exception'])
			&& ($record['context']['exception'] instanceof \Exception || $record['context']['exception'] instanceof \Throwable)
		) {
			// exception passed to context
			$record['context']['tracy'] = $this->logBluescreen($record['context']['exception']);
			unset($record['context']['exception']);
		}

		return $record;
	}



	/**
	 * @param \Exception|\Throwable $exception
	 * @return string
	 */
	protected function logBluescreen($exception)
	{
		$fileName = $this->tracyLogger->getExceptionFile($exception);

		if (!isset($this->processedExceptionFileNames[$fileName])) {
			$this->tracyLogger->logException($exception, $fileName);
			$this->processedExceptionFileNames[$fileName] = TRUE;
		}

		return ltrim(strrchr($fileName, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
	}

}
