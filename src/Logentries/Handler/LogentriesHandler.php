<?php

namespace Logentries\Handler;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class LogentriesHandler extends AbstractProcessingHandler
{
	private $connectionTimeout;
	private $token;
	private $resource;	
	private $timeout = 0;
	private $persistent = false;
	private $errno;
	private $errstr;
	private $api = 'api.logentries.com';
	private $port = 10000;

	/**
	 * @param string  $token  Token UUID for Logentries logfile
	 * @param integer $level  The minimum logging level at which this handler will be triggered
	 * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
	 */
	public function __construct($token, $level = Logger::DEBUG, $bubble = true)
	{
		$this->token = $token;
		parent::__construct($level, $bubble);
		$this->connectionTimeout = (float) ini_get('default_socket_timeout');
	}	

	/**
	 * {@inheritdoc}
	 */
	public function isHandling(array $record)
	{
		$resource = $this->trySetResource();

		if(!$resource){
			return false;
		}else{
			$this->resource = $resource;
			return true;
		}
	}

	/**
	 * Connect (if necessary) and write to the socket
	 *
	 * @param array $record
	 *
	 * @throws \UnexpectedValueException
	 * @throws \RuntimeException
	 */
	protected function write(array $record)
	{
		$this->connectIfNotConnected();
		$data = $this->generateDataStream($record);
		$final_data = sprintf("%s %s\n", $this->token, $data);
		$this->writeToSocket($final_data);
	}

	/**
	 * We will not close a PersistentSocket instance so it can be reused in other requests.
	 */
	public function close()
	{
		if (!$this->isPersistent()) {
			$this->closeSocket();
		}
	}

	/**
	 * Close socket, if open
	 */
	public function closeSocket()
	{
		if (is_resource($this->resource)) {
			fclose($this->resource);
			$this->resource = null;
		}
	}

	/**
	 * Set socket connection to be persistent. It only has effect before the connection is initiated.
	 *
	 * @param type $boolean
	 */
	public function setPersistent($boolean)
	{
		$this->persistent = (boolean) $boolean;
	}

	/**
	 * Set connection timeout. Only has effect before we connect.
	 * 
	 * @param float $seconds
	 */
	public function setConnectionTimeout($seconds)
	{
		$this->ValidateTimeout($seconds);
		$this->connectionTimeout = (float) $seconds;
	}

	/**
	 * Set write timeout. Only has effect before we connect.
	 *
	 * @param float $seconds
	 */
	public function setTimeout($seconds)
	{
		$this->validateTimeout($seconds);
		$this->timeout = (float) $seconds;
	}

	/**
	 * Get persistent setting
	 *
	 * @return boolean
	 */
	public function isPersistent()
	{
		return $this->persistent;
	}

	/**
	 * Get current connection timeout setting
	 *
	 * @return float
	 */
	public function getConnectionTimeout()
	{
		return $this->connectionTimeout;
	}

	/**
	 * Get current in-transfer timeout
	 *
	 * @return float
	 */
	public function getTimeout()
	{
		return $this->timeout;
	}

	/**
	 * Check to see if the socket is currently available.
	 *
	 * UDP might appear to be connected but might fail when writing. See http://php.net/fsockopen for details.
	 *
	 * @return boolean
	 */
	public function isConnected()
	{
		return is_resource($this->resource) && !feof($this->resource); // on TCP - other party can close connection.
	}

	/**
	 * Wrapper to allow mocking
	 */
	protected function pfsockopen()
	{
		return @pfsockopen($this->api, $this->port, $this->errno, $this->errstr, $this->connectionTimeout);
	}

	/**
	 * Wrapper to allow mocking
	 */
	protected function fsockopen()
	{
		return @fsockopen($this->api, $this->port, $this->errno, $this->errstr, $this->connectionTimeout);
	}

	/**
	 * Wrapper to allow mocking
	 *
	 * @see http://php.net/manual/en/function.stream-set-timeout.php
	 */
	protected function streamSetTimeout()
	{
		$seconds = floor($this->timeout);
		$microseconds = round(($this->timeout - $seconds)*1e6);

		return stream_set_timeout($this->resource, $seconds, $microseconds);
	}

	/**
	 * Wrapper to allow mocking
	 */
	protected function fwrite($data)
	{
		return @fwrite($this->resource, $data);
	}

	/**
	 * Wrapper to allow mocking
	 */
	protected function streamGetMetadata()
	{
		return stream_get_meta_data($this->resource);
	}

	private function validateTimeout($value)
	{
		$ok = filter_var($value, FILTER_VALIDATE_FLOAT);
		if ($ok === false || $value < 0) {
			throw new \InvalidArgumentException('Timeout must be 0 or a positive float (got $value)');
		}
	}

	private function connectIfNotConnected()
	{
		if ($this->isConnected()) {
			return;
		}
		$this->connect();
	}

	protected function generateDataStream($record)
	{
		return (string) $record['formatted'];
	}

	private function connect()
	{
		$this->createSocketResource();
		$this->setSocketTimeout();
	}

	private function trySetResource()
	{
		if ($this->isPersistent()) {
			$resource = $this->pfsockopen();
		} else {
			$resource = $this->fsockopen();
		}

		return $resource;
	}

	private function createSocketResource()
	{
		$resource = $this->trySetResource();		

		if (!$resource) {
			$resource = $this->trySetResource();
			if( ! $resource){
				return false;
				//throw new \UnexpectedValueException('Failed connecting to Logentries ($this->errno: $this->errstr)');
			}
		}
		$this->resource = $resource;
		return true;
	}

	private function setSocketTimeout()
	{
		if (!$this->streamSetTimeout()) {
			throw new \UnexpectedValueException('Failed setting timeout with stream_set_timeout()');
		}
	}

	private function writeToSocket($data)
	{
		$length = strlen($data);
		$sent = 0;
		while ($this->isConnected() && $sent < $length) {
			if (0 == $sent) {
				$chunk = $this->fwrite($data);
			} else {
				$chunk = $this->fwrite(substr($data, $sent));
			}
			if ($chunk === false) {
				throw new \RuntimeException('Could not write to socket');
			}
			$sent += $chunk;
			$socketInfo = $this->streamGetMetadata();
			if ($socketInfo['timed_out']) {
				throw new \RuntimeException('Write timed-out');
			}
		}
	}
}
