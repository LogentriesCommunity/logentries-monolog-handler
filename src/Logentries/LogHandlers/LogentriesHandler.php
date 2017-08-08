<?php

namespace Logentries\LogHandlers;

use Logentries\DataHandlers\Socket;
use Logentries\DataHandlers\DataHandlerInterface;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class LogentriesHandler extends AbstractProcessingHandler
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var DataHandlerInterface
     */
    protected $dataHandler;

    /**
     * @param string                    $token               Token UUID for Logentries logfile
     * @param bool|int                  $level               The minimum logging level at which this handler will be triggered
     * @param Boolean                   $bubble              Whether the messages that are handled can bubble up the stack
     * @param DataHandlerInterface|null $dataHandler         Data handler which is used to send or store the data
     * @param DataHandlerInterface|null $fallbackDataHandler Fallback handler which is used when the primary handler isn't working
     * @param float|null                $timeout             Timeout for the data handler, when reached the fallback will be used
     * @throws \Exception
     */
    public function __construct(
        $token, $level = Logger::DEBUG, $bubble = true, DataHandlerInterface $dataHandler = null,
        DataHandlerInterface $fallbackDataHandler = null, $timeout = null
    )
    {
        $this->token = $token;
        $this->dataHandler = $dataHandler;

        if (!$this->dataHandler) {
            $this->dataHandler = $this->createSocket($fallbackDataHandler, $timeout);
        } elseif (null !== $fallbackDataHandler) {
            throw new \Exception('Own socket provided, cannot attach fallback!');
        }

        parent::__construct($level, $bubble);
    }

    protected function createSocket(DataHandlerInterface $fallbackDataHandler = null, $timeout = null): Socket
    {
        return new Socket(
            'data.logentries.com', 80, true, $fallbackDataHandler, $timeout
        );
    }

    protected function write(array $record)
    {
        $data = $this->generateDataStream($record);

        $this->getDataHandler()->write($data);
    }

    public function close()
    {
        $this->getDataHandler()->close();
    }

    protected function generateDataStream(array $record): string
    {
        return sprintf(
            "%s hostname=%s %s.%s: %s %s\n",
            $this->token,
            gethostname(),
            $record['channel'],
            $record['level_name'],
            $record['message'],
            json_encode([
                'extra' => $record['extra'],
                'context' => $record['context']
            ])
        );
    }

    protected function getDataHandler(): DataHandlerInterface
    {
        return $this->dataHandler;
    }
}
