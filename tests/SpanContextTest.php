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

use Jaeger\SpanContext;
use PHPUnit\Framework\TestCase;

class SpanContextTest extends TestCase
{
    /**
     * @var SpanContext|null
     */
    public $spanContext;

    public function setUp(): void
    {
        $this->spanContext = new SpanContext(1, 1, 1, null, 1);
    }

    public function testNew(): void
    {
        static::assertInstanceOf(SpanContext::class, $this->spanContext);
    }

    public function testGetBaggageItem(): void
    {
        $this->spanContext->withBaggageItem('version', '2.0.0');

        $version = $this->spanContext->getBaggageItem('version');

        static::assertSame('2.0.0', $version);

        $service = $this->spanContext->getBaggageItem('service');
        static::assertNull($service);
    }

    public function testBuildString(): void
    {
        $this->spanContext->traceIdLow = 1;
        static::assertEquals('1:1:1:1', $this->spanContext->buildString());

        $this->spanContext->traceIdHigh = 1;
        static::assertEquals('10000000000000001:1:1:1', $this->spanContext->buildString());
    }

    public function testSpanIdToString(): void
    {
        static::assertEquals('1', $this->spanContext->spanIdToString());

        $this->spanContext->spanId = '111111';
        static::assertEquals('1b207', $this->spanContext->spanIdToString());
    }

    public function testTraceIdLowToString(): void
    {
        $this->spanContext->traceIdLow = '111111';
        static::assertEquals('1b207', $this->spanContext->traceIdLowToString());

        $this->spanContext->traceIdHigh = '111111';
        static::assertEquals('1b207000000000001b207', $this->spanContext->traceIdLowToString());
    }

    public function testTraceIdToString(): void
    {
        $this->spanContext->traceIdToString('1b207000000000001b207');
        static::assertEquals('111111', $this->spanContext->traceIdLow);
        static::assertEquals('1954685383581106176', $this->spanContext->traceIdHigh);

        $this->spanContext->traceIdLow = null;
        $this->spanContext->traceIdHigh = null;
        $this->spanContext->traceIdToString('1b207');
        static::assertEquals('111111', $this->spanContext->traceIdLow);
        static::assertNull($this->spanContext->traceIdHigh);
    }
}
