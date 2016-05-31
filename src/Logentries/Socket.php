<?php namespace Logentries;

class Socket
{
	private $url;
	private $port;
	private $connectionTimeout;
	private $resource;
	private $errno;
	private $errstr;

	public function __construct($url, $port)
	{
		$this->url = $url;
		$this->port = $port;
		$this->connectionTimeout = (float) ini_get('default_socket_timeout');
	}

	public function write($data)
	{
		if ( ! $this->isConnected())
		{
			$this->connect();
		}

		$length = strlen($data);
		$sent = 0;
		while ($this->isConnected() && $sent < $length)
		{
			if (0 == $sent)
			{
				$chunk = $this->fwrite($data);
			}
			else 
			{
				$chunk = $this->fwrite(substr($data, $sent));
			}

			if ($chunk === false)
			{
				throw new \RuntimeException('Could not write to socket');
			}

			$sent += $chunk;
			$socketInfo = $this->streamGetMetadata();

			if ($socketInfo['timed_out'])
			{
				throw new \RuntimeException('Write timed-out');
			}
		}
	}

	public function close()
	{
		if (is_resource($this->resource)) {
			fclose($this->resource);
			$this->resource = null;
		}
	}

	private function connect()
	{
		$this->createResource();
		$this->setSocketTimeout();
	}

	private function createResource()
	{
		$this->resource = $this->fsockopen();

		if ( ! $this->resource)
		{
			throw new \RuntimeException(sprintf('Failed connecting to Logentries (%s: %s)', $this->errno, $this->errstr));
		}
	}

	private function setSocketTimeout()
	{
		if ( ! $this->streamSetTimeout())
		{
			throw new \RuntimeException('Failed setting timeout with stream_set_timeout()');
		}
	}

	private function fsockopen()
	{
		return @fsockopen($this->url, $this->port, $this->errno, $this->errstr, $this->connectionTimeout);
	}

	private function fwrite($data)
	{
		return @fwrite($this->resource, $data);
	}

	private function streamSetTimeout()
	{
		$seconds = floor($this->connectionTimeout);
		$microseconds = round(($this->connectionTimeout - $seconds) * 1e6);

		return stream_set_timeout($this->resource, $seconds, $microseconds);
	}
	
	private function streamGetMetadata()
	{
		return stream_get_meta_data($this->resource);
	}

	private function isConnected()
	{
		return is_resource($this->resource) && !feof($this->resource); // on TCP - other party can close connection.
	}

}
