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

namespace tests;

use Jaeger\Jaeger;
use Jaeger\JaegerThrift;
use Jaeger\Reporter\RemoteReporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\ScopeManager;
use Jaeger\Thrift\TagType;
use Jaeger\Transport\TransportUdp;
use PHPUnit\Framework\TestCase;

class JaegerThriftTest extends TestCase
{
    /**
     * @var JaegerThrift|null
     */
    public $jaegerThrift;

    /**
     * @var Jaeger|null
     */
    public $tracer;

    public function setUp(): void
    {
        $tranSport = new TransportUdp();
        $reporter = new RemoteReporter($tranSport);
        $sampler = new ConstSampler();
        $scopeManager = new ScopeManager();
        $this->tracer = new Jaeger('jaeger', $reporter, $sampler, $scopeManager);

        $this->jaegerThrift = new JaegerThrift();
    }

    public function testBuildProcessThrift(): void
    {
        $process = $this->jaegerThrift->buildProcessThrift($this->tracer);
        static::assertEquals('jaeger', $process->serviceName);
    }

    public function testBuildTags(): void
    {
        $tags = ['event' => 'test'];
        $jtags = $this->jaegerThrift->buildTags($tags);
        static::assertEquals('event', $jtags[0]->key);
        static::assertEquals(TagType::STRING, $jtags[0]->vType);
        static::assertEquals('test', $jtags[0]->vStr);

        $tags = ['success' => true];
        $jtags = $this->jaegerThrift->buildTags($tags);
        static::assertEquals('success', $jtags[0]->key);
        static::assertEquals(TagType::BOOL, $jtags[0]->vType);
        static::assertTrue($jtags[0]->vBool);

        $tags = ['data' => [1, 2]];
        $jtags = $this->jaegerThrift->buildTags($tags);
        static::assertEquals('data', $jtags[0]->key);
        static::assertEquals(TagType::STRING, $jtags[0]->vType);

        $tags = ['num' => 1];
        $jtags = $this->jaegerThrift->buildTags($tags);
        static::assertEquals('num', $jtags[0]->key);
        static::assertEquals(TagType::LONG, $jtags[0]->vType);
        static::assertEquals(1, $jtags[0]->vLong);
    }

    public function testBuildSpanThrift(): void
    {
        $span = $this->tracer->startSpan('BuildSpanThrift');
        $jspan = $this->jaegerThrift->buildSpanThrift($span);
        static::assertEquals('BuildSpanThrift', $jspan->operationName);
    }
}
