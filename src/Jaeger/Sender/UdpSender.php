<?php
/*
 * Copyright (c) 2019, The Jaeger Authors
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

namespace Jaeger\Sender;

use Exception;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Batch;
use Thrift\Transport\TMemoryBuffer;

/**
 * send thrift to jaeger-agent
 * Class UdpSender.
 */
class UdpSender implements Sender
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $post;

    /**
     * @var resource|null
     */
    private $socket;

    /**
     * @var AgentClient|null
     */
    private $agentClient;

    /**
     * @var TMemoryBuffer|null
     */
    private $tran;

    public function __construct(string $hostPost, AgentClient $agentClient, TMemoryBuffer $tran)
    {
        [$this->host, $this->post] = explode(':', $hostPost);
        $this->agentClient = $agentClient;
        $this->socket = fsockopen("udp://$this->host", (int) $this->post);
        $this->tran = $tran;
    }

    public function isOpen(): bool
    {
        return null !== $this->socket;
    }

    /**
     * send thrift.
     *
     * @throws Exception
     */
    public function emitBatch(Batch $batch): bool
    {
        $this->agentClient->emitBatch($batch);
        $len = $this->tran->available();
        if ($len > 0 && $this->isOpen()) {
            $res = fwrite($this->socket, $this->tran->read($len));
            if (false === $res) {
                throw new Exception('emit failse');
            }

            return true;
        }

        return false;
    }

    public function close(): void
    {
        fclose($this->socket);
        $this->socket = null;
    }
}
