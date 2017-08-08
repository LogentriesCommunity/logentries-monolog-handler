<?php

namespace Logentries\DataHandlers;

class File implements DataHandlerInterface
{
    protected $logPath;

    public function __construct(string $logPath)
    {
        $this->logPath = $logPath . (substr($logPath, -1) != '/' ? '/' : '');
    }

    public function write(string $data)
    {
        file_put_contents($this->getLogPathname(), $data . PHP_EOL, FILE_APPEND);
    }

    public function getLogFileList(): array
    {
        $logFiles = [];

        $logsDi = new \DirectoryIterator($this->logPath);

        foreach ($logsDi as $file) {
            if ($file->isDot()) {
                continue;
            }

            $filename = $file->getFilename();
            $pathname = $file->getPathname();

            if (!$this->isValidLogFilename($filename)) {
                continue;
            }

            // Ignore log files for the current minute, we'll won't be able to handle those
            if ($file->getPathname() == $this->getLogPathName()) {
                continue;
            }

            $logFiles[] = $pathname;
        }

        return $logFiles;
    }

    /**
     * Validates a filename, a valid example is: logentries.2017-12-31_11-33.log
     *
     * @param string $filename
     * @return bool
     */
    public function isValidLogFilename(string $filename): bool
    {
        if (preg_match('/^logentries.[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}.log$/i', $filename)) {
            return true;
        }

        return false;
    }

    public function getLogPathname(): string
    {
        return $this->logPath . 'logentries.' . date('Y-m-d_H-i') . '.log';
    }

    public function close()
    {
        return;
    }
}
