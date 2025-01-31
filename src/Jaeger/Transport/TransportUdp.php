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

namespace Jaeger\Transport;

use Jaeger\Constants;
use Jaeger\Jaeger;
use Jaeger\JaegerThrift;
use Jaeger\Sender\Sender;
use Jaeger\Sender\UdpSender;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Batch;
use Jaeger\Thrift\Process;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TMemoryBuffer;

class TransportUdp implements Transport
{
    public const DEFAULT_AGENT_HOST_PORT = 'localhost:6831';

    /**
     * @var TMemoryBuffer|null
     */
    private $tran;

    /**
     * @var Sender|null
     */
    private $sender;

    /**
     * @var JaegerThrift|null
     */
    private $jaegerThrift;

    /**
     * @var Process|null
     */
    private $process;

    /**
     * @var string
     */
    public $agentHostPort = '';

    // sizeof(Span) * numSpans + processByteSize + emitBatchOverhead <= maxPacketSize
    public static $maxSpanBytes = 0;

    /**
     * @var Batch|null
     */
    public static $batch;

    /**
     * @var TCompactProtocol|null
     */
    public $protocol;

    public $procesSize = 0;

    public $bufferSize = 0;

    public const MAC_UDP_MAX_SIZE = 9216;

    public function __construct(string $hostport = self::DEFAULT_AGENT_HOST_PORT, int $maxPacketSize = 0, Sender $udpSender = null)
    {
        $this->agentHostPort = $hostport;

        if (0 === $maxPacketSize) {
            $maxPacketSize = stristr(PHP_OS, 'DAR') ? self::MAC_UDP_MAX_SIZE : Constants\UDP_PACKET_MAX_LENGTH;
        }

        self::$maxSpanBytes = $maxPacketSize - Constants\EMIT_BATCH_OVER_HEAD;

        $this->tran = new TMemoryBuffer();

        $this->protocol = new TCompactProtocol($this->tran);

        $agentClient = new AgentClient($this->protocol, null);

        $this->sender = $udpSender;
        if (null == $this->sender) {
            $this->sender = new UdpSender($this->agentHostPort, $agentClient, $this->tran);
        }

        $this->jaegerThrift = new JaegerThrift();
    }

    public function append(Jaeger $jaeger): bool
    {
        if (null == $this->process) {
            $this->buildAndCalcSizeOfProcessThrift($jaeger);
        }

        $thriftSpansBuffer = [];  // Uncommitted span used to temporarily store shards

        foreach ($jaeger->spans as $span) {
            $spanThrift = $this->jaegerThrift->buildSpanThrift($span);
            $spanSize = $this->getAndCalcSizeOfSerializedThrift($spanThrift);
            if ($spanSize > self::$maxSpanBytes) {
                //throw new \Exception("Span is too large");
                continue;
            }

            $thriftSpansBuffer[] = $spanThrift;
            $this->bufferSize += $spanSize;

            if ($this->bufferSize >= self::$maxSpanBytes) {
                self::$batch = new Batch([
                    'process' => $this->process,
                    'spans' => $thriftSpansBuffer,
                ]);
                $this->flush();
                $thriftSpansBuffer = [];  // Empty the temp buffer
            }
        }

        if (count($thriftSpansBuffer) > 0) {
            self::$batch = new Batch([
                'process' => $this->process,
                'spans' => $thriftSpansBuffer,
            ]);
            $this->flush();
        }

        $this->process = null;

        return true;
    }

    public function buildAndCalcSizeOfProcessThrift(Jaeger $jaeger): void
    {
        $this->process = $this->jaegerThrift->buildProcessThrift($jaeger);
        $this->procesSize = $this->getAndCalcSizeOfSerializedThrift($this->process);
        $this->bufferSize += $this->procesSize;
    }

    /**
     * @param mixed $thrift
     *
     * @return mixed
     */
    private function getAndCalcSizeOfSerializedThrift($thrift)
    {
        $thrift->write($this->protocol);
        $len = $this->tran->available();

        $this->tran->read($len);

        return $len;
    }

    public function flush(): int
    {
        if (null == self::$batch) {
            return 0;
        }

        $spanNum = count(self::$batch->spans);
        $this->sender->emitBatch(self::$batch);

        $this->resetBuffer();

        return $spanNum;
    }

    public function resetBuffer(): void
    {
        $this->bufferSize = $this->procesSize;
        self::$batch = null;
    }

    public function close(): void
    {
        $this->sender->close();
    }
}
