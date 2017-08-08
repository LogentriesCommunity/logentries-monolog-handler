<?php

namespace Logentries\LogHandlers;

use Logentries\DataHandlers\DataHandlerInterface;
use Logentries\DataHandlers\File;
use Logentries\DataHandlers\Socket;
use Monolog\Logger;

class LogentriesDelayedHandler extends LogentriesHandler
{
    /**
     * @var File
     */
    protected $dataHandler;

    /**
     * @param string   $token   Token UUID for Logentries logfile
     * @param bool|int $level   The minimum logging level at which this handler will be triggered
     * @param bool     $bubble  Whether the messages that are handled can bubble up the stack or not
     * @param string   $logPath The directory where the log files will be stored
     */
    public function __construct($token, $level = Logger::DEBUG, $bubble = true, string $logPath)
    {
        parent::__construct($token, $level, $bubble, (new File($logPath)));
    }

    protected function write(array $record)
    {
        $this->getDataHandler()->write($this->generateDataStream($record));
    }

    /**
     * Sends all log entries from log files in the specified logs directory
     *
     * Files are removed as soon as they've been sent to Logentries. Files which are created in the current minute will
     * be ignored.
     */
    public function sendToLogentries(): array
    {
        /** @var Socket $socket */
        $socket = $this->createSocket(null, 30);
        $dataHandler = $this->getDataHandler();
        $files = $dataHandler->getLogFileList();
        $processedFiles = [];

        foreach ($files as $filepathname) {
            $fileContents = file_get_contents($filepathname);
            $lines = explode(PHP_EOL, $fileContents);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $socket->write($line);
            }

            unlink($filepathname);

            $processedFiles[] = $filepathname;
        }

        return $processedFiles;
    }

    /**
     * @return DataHandlerInterface|File
     */
    protected function getDataHandler(): DataHandlerInterface
    {
        return parent::getDataHandler();
    }
}
