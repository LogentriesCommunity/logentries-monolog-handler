<?php

namespace Logentries\DataHandlers;

interface DataHandlerInterface
{
    /**
     * Writes the data to the destination (e.g. socket api.logentries.com or a local file)
     *
     * @param string $data
     *
     * @return void
     */
    public function write(string $data);

    /**
     * Closes the file, stream or anything else
     *
     * @return void
     */
    public function close();
}
