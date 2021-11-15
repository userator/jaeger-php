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

namespace tests\Sender;

use Jaeger\Sender\UdpSender;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Batch;
use Jaeger\Thrift\Process;
use Jaeger\Thrift\Span;
use Jaeger\Thrift\Tag;
use PHPUnit\Framework\TestCase;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TMemoryBuffer;

class UdpSenderTest extends TestCase
{
    /**
     * @var UdpSender|null
     */
    public $udpSender;

    /**
     * @var AgentClient|null
     */
    public $agentClient;

    /**
     * @var TMemoryBuffer|null
     */
    public $tran;

    /**
     * @var TCompactProtocol|null
     */
    public $protocol;

    public function setUp(): void
    {
        $this->tran = new TMemoryBuffer();
        $this->protocol = new TCompactProtocol($this->tran);
        $this->agentClient = (new AgentClient($this->protocol, null));
        $this->udpSender = new UdpSender('localhost:6831', $this->agentClient, $this->tran);
    }

    public function testIsOpen(): void
    {
        static::assertTrue($this->udpSender->isOpen());
    }

    public function testClose(): void
    {
        $this->udpSender->close();
        static::assertFalse($this->udpSender->isOpen());
    }

    public function testEmitBatch(): void
    {
        $span = new Span(
            [
                'traceIdLow' => 1609214197859399756,
                'traceIdHigh' => 1609214197860113544,
                'spanId' => 1609214197859399756,
                'parentSpanId' => 0,
                'operationName' => 'test',
                'flags' => 1,
                'startTime' => 1609214197860775,
                'duration' => 3216877,
                'tags' => [],
                'logs' => [],
            ]
        );

        $batch = new Batch(
            [
                'process' => new Process([
                    'serviceName' => 'EmitBatch',
                    'tags' => [
                        (new Tag([
                            'key' => 'peer.ipv4',
                            'vType' => 0,
                            'vStr' => '0.0.0.0',
                        ])),
                        (new Tag([
                            'key' => 'peer.port',
                            'vType' => 0,
                            'vStr' => '80',
                        ])),
                        (new Tag([
                            'key' => 'sampler.type',
                            'vType' => 0,
                            'vStr' => 'const',
                        ])),
                        (new Tag([
                            'key' => 'sampler.param',
                            'vType' => 2,
                            'vBool' => true,
                        ])),
                    ],
                ]),
                'spans' => [$span],
            ]
        );
        static::assertTrue($this->udpSender->emitBatch($batch));
    }
}
