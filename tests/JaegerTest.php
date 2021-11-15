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

use Jaeger\Constants;
use Jaeger\Jaeger;
use Jaeger\Propagator\JaegerPropagator;
use Jaeger\Reporter\NullReporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\ScopeManager;
use Jaeger\Span;
use Jaeger\SpanContext;
use OpenTracing\Formats;
use OpenTracing\Reference;
use OpenTracing\UnsupportedFormatException;
use PHPUnit\Framework\TestCase;

class JaegerTest extends TestCase
{
    /**
     * @var Jaeger|null
     */
    public $tracer;

    public function setUp(): void
    {
        $reporter = new NullReporter();
        $sampler = new ConstSampler();
        $scopeManager = new ScopeManager();
        $this->tracer = new Jaeger('jaeger', $reporter, $sampler, $scopeManager);
    }

    public function testNew(): void
    {
        static::assertInstanceOf(Jaeger::class, $this->tracer);
    }

    public function testGetEnvTags(): void
    {
        $_SERVER['JAEGER_TAGS'] = 'a=b,c=d';
        $tags = $this->tracer->getEnvTags();
        static::assertNotEmpty($tags);
    }

    public function testSetTags(): void
    {
        $this->tracer->setTags(['version' => '2.0.0']);
        static::assertEquals('2.0.0', $this->tracer->tags['version']);
    }

    public function testInject(): void
    {
        $this->tracer->setPropagator(new JaegerPropagator());

        $context = new SpanContext(1, 1, 1, null, 1);

        $this->tracer->inject($context, Formats\TEXT_MAP, $_SERVER);
        static::assertEquals('0:1:1:1', $_SERVER[strtoupper(Constants\Tracer_State_Header_Name)]);
    }

    public function testInjectUnSupportFormat(): void
    {
        $this->tracer->setPropagator(new JaegerPropagator());

        $context = new SpanContext(1, 1, 1, null, 1);
        $this->expectException(UnsupportedFormatException::class);
        $this->expectExceptionMessage('The format "http_headers" is not supported.');

        $this->tracer->inject($context, Formats\HTTP_HEADERS, $_SERVER);
    }

    public function testExtract(): void
    {
        $this->tracer->setPropagator(new JaegerPropagator());

        $carrier[strtoupper(Constants\Tracer_State_Header_Name)] = '1:1:1:1';
        $spanContext = $this->tracer->extract(Formats\TEXT_MAP, $carrier);
        static::assertEquals(1, $spanContext->parentId);
        static::assertEquals(1, $spanContext->traceIdLow);
        static::assertEquals(1, $spanContext->flags);
        static::assertEquals(1, $spanContext->spanId);
    }

    public function testExtractUnSupportFormat(): void
    {
        $this->tracer->setPropagator(new JaegerPropagator());

        $_SERVER[strtoupper(Constants\Tracer_State_Header_Name)] = '1:1:1:1';
        $this->expectException(UnsupportedFormatException::class);
        $this->expectExceptionMessage('The format "http_headers" is not supported.');

        $this->tracer->extract(Formats\HTTP_HEADERS, $_SERVER);
    }

    public function testStartSpan(): void
    {
        $span = $this->tracer->startSpan('test');
        static::assertNotNull($span->startTime);
        static::assertNotEmpty($this->tracer->getSpans());
    }

    public function testStartSpanWithFollowsFromTypeRef(): void
    {
        $rootSpan = $this->tracer->startSpan('root-a');
        $childSpan = $this->tracer->startSpan('span-a', [
            'references' => Reference::createForSpan(Reference::FOLLOWS_FROM, $rootSpan),
        ]);

        if (!($childSpan->spanContext instanceof SpanContext && $rootSpan->spanContext instanceof SpanContext)) {
            static::fail('span contexts are not Jaeger\SpanContexts');
        }
        static::assertSame($childSpan->spanContext->traceIdLow, $rootSpan->spanContext->traceIdLow);
        static::assertSame(current($childSpan->references)->getSpanContext(), $rootSpan->spanContext);

        $otherRootSpan = $this->tracer->startSpan('root-a');
        $childSpan = $this->tracer->startSpan('span-b', [
            'references' => [
                Reference::createForSpan(Reference::FOLLOWS_FROM, $rootSpan),
                Reference::createForSpan(Reference::FOLLOWS_FROM, $otherRootSpan),
            ],
        ]);

        if (!($childSpan->spanContext instanceof SpanContext && $otherRootSpan->spanContext instanceof SpanContext)) {
            static::fail('span contexts are not Jaeger\SpanContexts');
        }
        static::assertSame($childSpan->spanContext->traceIdLow, $otherRootSpan->spanContext->traceIdLow);
    }

    public function testStartSpanWithChildOfTypeRef(): void
    {
        $rootSpan = $this->tracer->startSpan('root-a');
        $otherRootSpan = $this->tracer->startSpan('root-b');
        $childSpan = $this->tracer->startSpan('span-a', [
            'references' => [
                Reference::createForSpan(Reference::CHILD_OF, $rootSpan),
                Reference::createForSpan(Reference::CHILD_OF, $otherRootSpan),
            ],
        ]);

        if (!($childSpan->spanContext instanceof SpanContext && $rootSpan->spanContext instanceof SpanContext)) {
            static::fail('span contexts are not Jaeger\SpanContexts');
        }
        static::assertSame($childSpan->spanContext->traceIdLow, $rootSpan->spanContext->traceIdLow);
    }

    public function testStartSpanWithCustomStartTime(): void
    {
        $span = $this->tracer->startSpan('test', ['start_time' => 1499355363.123456]);

        static::assertSame(1499355363123456, $span->startTime);
    }

    public function testStartSpanWithAllRefType(): void
    {
        $rootSpan = $this->tracer->startSpan('root-a');
        $otherRootSpan = $this->tracer->startSpan('root-b');
        $childSpan = $this->tracer->startSpan('span-a', [
            'references' => [
                Reference::createForSpan(Reference::FOLLOWS_FROM, $rootSpan),
                Reference::createForSpan(Reference::CHILD_OF, $otherRootSpan),
            ],
        ]);

        if (!($childSpan->spanContext instanceof SpanContext && $otherRootSpan->spanContext instanceof SpanContext)) {
            static::fail('span contexts are not Jaeger\SpanContexts');
        }
        static::assertSame($childSpan->spanContext->traceIdLow, $otherRootSpan->spanContext->traceIdLow);
    }

    public function testReportSpan(): void
    {
        $this->tracer->startSpan('test');
        $this->tracer->reportSpan();
        static::assertEmpty($this->tracer->getSpans());
    }

    public function testStartActiveSpan(): void
    {
        $this->tracer->startActiveSpan('test');
        static::assertNotEmpty($this->tracer->getSpans());
    }

    public function testGetActiveSpan(): void
    {
        $this->tracer->startActiveSpan('test');

        $span = $this->tracer->getActiveSpan();
        static::assertInstanceOf(Span::class, $span);
    }

    public function testFlush(): void
    {
        $this->tracer->startSpan('test');
        $this->tracer->flush();
        static::assertEmpty($this->tracer->getSpans());
    }

    public function testNestedSpanBaggage(): void
    {
        $parent = $this->tracer->startSpan('parent');
        $parent->addBaggageItem('key', 'value');

        $child = $this->tracer->startSpan('child', [Reference::CHILD_OF => $parent]);

        static::assertEquals($parent->getBaggageItem('key'), $child->getBaggageItem('key'));
    }
}
