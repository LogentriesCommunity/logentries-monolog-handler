<?php

namespace Logentries\DataHandlers;

use Logentries\DataHandlers\Exception\ConnectionConfigurationException;
use Logentries\DataHandlers\Exception\ConnectionException;
use Logentries\DataHandlers\Exception\SocketWriteException;

class Socket implements DataHandlerInterface
{
    protected $url;
    protected $port;
    protected $connectionTimeout;
    protected $resource;
    protected $errno;
    protected $errstr;
    protected $persistent;
    protected $fallback;
    protected $connectionPreviouslyFailed = false;
    protected $writingDataPreviouslyFailed = false;

    /**
     * @param string                    $url
     * @param int                       $port
     * @param bool                      $persistent
     * @param DataHandlerInterface|null $fallback Fallback data handler for when the socket is not available
     * @param float|null                $timeout  Default timeout for socket connection
     */
    public function __construct(
        string $url, int $port, $persistent = true, DataHandlerInterface $fallback = null, $timeout = null
    )
    {
        $this->url = $url;
        $this->port = $port;
        $this->connectionTimeout = (null !== $timeout ? $timeout : (float)ini_get('default_socket_timeout'));
        $this->persistent = $persistent;
        $this->fallback = $fallback;
    }

    /**
     * Writes to the fallback data handler or throws a SocketWriteException
     * 
     * @param string $data
     * @param string $errorMessage
     * 
     * @return Socket
     */
    public function writeToFallback(string $data, string $errorMessage): Socket
    {
        if (null !== $this->fallback) {
            $this->fallback->write('DELAYED at ' . date('Y-m-d H:i:s') . ' ' . $data);

            return $this;
        }

        throw new SocketWriteException(sprintf(
            'Could not send data to Logentries and no fallback is available, previous error: %s',
            $errorMessage
        ));
    }

    /**
     * Writes data to the socket, uses fallback if that fails and a fallback is provided
     *
     * @param string $data
     *
     * @return Socket
     */
    public function write(string $data): Socket
    {
        // Do not try to reconnect if the previous connection attempt during the current requests lifetime failed
        if ((!$this->isConnected() && $this->connectionPreviouslyFailed) || $this->writingDataPreviouslyFailed) {
            if ($this->connectionPreviouslyFailed) {
                $this->writeToFallback($data, 'Connection to Oneskyapp previously failed');

                return $this;
            }
        }

        if (!$this->isConnected()) {
            try {
                $this->connect();
            } catch (ConnectionException $e) {
                $this->writeToFallback($data, $e->getMessage());

                return $this;
            }
        }

        $dataWithNewline = $data . PHP_EOL;
        $length = strlen($dataWithNewline);
        $sent = 0;
        while ($this->isConnected() && $sent < $length) {
            if (0 == $sent) {
                $chunk = $this->fwrite($dataWithNewline);
            } else {
                $chunk = $this->fwrite(substr($dataWithNewline, $sent));
            }

            if ($chunk !== false) {
                $socketInfo = $this->streamGetMetadata();
            }

            // If we've failed to send the data use the fallback
            if ($chunk === false || ((isset($socketInfo) && $socketInfo['timed_out']))) {
                $this->writingDataPreviouslyFailed = true;

                $this->writeToFallback(
                    $data,
                    ($chunk ?
                        sprintf('Time out while writing to %s', $this->url . ':' . $this->port) :
                        sprintf('Unknown error when writing to %s', $this->url . ':' . $this->port)
                    )
                );

                return $this;
            }

            $sent += $chunk;
        }

        return $this;
    }

    protected function isConnected()
    {
        return is_resource($this->resource) && !feof($this->resource); // on TCP - other party can close connection.
    }

    protected function connect(): Socket
    {
        $this
            ->openSocket()
            ->setSocketTimeout();

        return $this;
    }

    protected function openSocket(): Socket
    {
        if ($this->persistent) {
            $this->resource = @fsockopen($this->url, $this->port, $this->errno, $this->errstr, $this->connectionTimeout);
        } else {
            $this->resource = @pfsockopen($this->url, $this->port, $this->errno, $this->errstr, $this->connectionTimeout);
        }

        if (!$this->resource) {
            $this->connectionPreviouslyFailed = true;

            throw new ConnectionException(sprintf(
                'Failed connecting to Logentries (%s: %s)', $this->errno, $this->errstr
            ));
        }

        return $this;
    }

    protected function setSocketTimeout()
    {
        $seconds = floor($this->connectionTimeout);
        $microseconds = round(($this->connectionTimeout - $seconds) * 1e6);

        if (!stream_set_timeout($this->resource, $seconds, $microseconds)) {
            throw new ConnectionConfigurationException('Failed setting timeout with stream_set_timeout()');
        }
    }

    public function close(bool $closePersistent = false): Socket
    {
        if (is_resource($this->resource) && ($closePersistent || !$this->persistent)) {
            fclose($this->resource);

            $this->resource = null;
        }

        return $this;
    }

    protected function fwrite(string $data)
    {
        return @fwrite($this->resource, $data);
    }

    protected function streamGetMetadata()
    {
        return stream_get_meta_data($this->resource);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function setFallback(DataHandlerInterface $fallback = null): Socket
    {
        $this->fallback = $fallback;

        return $this;
    }
}
