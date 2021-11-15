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

use Jaeger\Span;
use Jaeger\SpanContext;
use OpenTracing\NoopSpanContext;
use PHPUnit\Framework\TestCase;

class SpanTest extends TestCase
{
    public function testOverwriteOperationName(): void
    {
        $span = new Span('test', new NoopSpanContext(), []);
        $span->overwriteOperationName('test2');
        static::assertEquals('test2', $span->getOperationName());
    }

    public function testAddTags(): void
    {
        $span = new Span('test', new NoopSpanContext(), []);
        $span->setTag('test', 'test');
        static::assertTrue((isset($span->tags['test']) && 'test' == $span->tags['test']));
    }

    public function testFinish(): void
    {
        $span = new Span('test', new NoopSpanContext(), []);
        $span->setTag('test', 'test');
        $span->finish();
        static::assertTrue(null !== $span->finishTime && null !== $span->duration);
    }

    public function testGetContext(): void
    {
        $span = new Span('test', new NoopSpanContext(), []);
        $spanContext = $span->getContext();
        static::assertInstanceOf(NoopSpanContext::class, $spanContext);
    }

    public function testLog(): void
    {
        $span = new Span('test', new NoopSpanContext(), []);
        $logs = [
            'msg' => 'is test',
            'msg2' => 'is msg 2',
        ];
        $span->log($logs);
        static::assertCount(1, $span->logs);
    }

    public function testGetBaggageItem(): void
    {
        $span = new Span('test', new SpanContext(0, 0, 0), []);
        $span->addBaggageItem('version', '2.0.0');

        $version = $span->getBaggageItem('version');
        static::assertEquals('2.0.0', $version);

        $service = $span->getBaggageItem('service');
        static::assertNull($service);
    }
}
