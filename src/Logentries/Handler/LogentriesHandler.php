<?php namespace Logentries\Handler;

use Logentries\Socket;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class LogentriesHandler extends AbstractProcessingHandler
{
	private $token;
	private $socket;

	/**
	 * @param string  $token  Token UUID for Logentries logfile
	 * @param integer $level  The minimum logging level at which this handler will be triggered
	 * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
	 */
	public function __construct($token, $level = Logger::DEBUG, $bubble = true, Socket $socket = null)
	{
		$this->token = $token;
		$this->socket = $socket;

		if ( ! $this->socket)
		{
			$this->socket = new Socket('api.logentries.com', 10000);
		}

		parent::__construct($level, $bubble);
	}

	protected function write(array $record)
	{
		$data = $this->generateDataStream($record);
		$this->socket->write($data);
	}

	public function close()
	{
		$this->socket->close();
	}

	private function generateDataStream(array $record)
	{
		return sprintf("%s %s\n", $this->token, $record['formatted']);
	}

}
