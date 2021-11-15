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

namespace Jaeger;

use OpenTracing\Span as OpenTracingSpan;
use OpenTracing\SpanContext as OpenTracingSpanContext;

class Span implements OpenTracingSpan
{
    /**
     * @var string
     */
    private $operationName;

    /**
     * @var int|null
     */
    public $startTime;

    /**
     * @var int|null
     */
    public $finishTime;

    /**
     * @var string
     */
    public $spanKind = '';

    /**
     * @var OpenTracingSpanContext|null
     */
    public $spanContext;

    /**
     * @var int
     */
    public $duration = 0;

    /**
     * @var array
     */
    public $logs = [];

    /**
     * @var array
     */
    public $tags = [];

    /**
     * @var array
     */
    public $references = [];

    public function __construct($operationName, OpenTracingSpanContext $spanContext, $references, $startTime = null)
    {
        $this->operationName = $operationName;
        $this->startTime = null == $startTime ? $this->microtimeToInt() : $startTime;
        $this->spanContext = $spanContext;
        $this->references = $references;
    }

    /**
     * {@inheritdoc}
     */
    public function getOperationName(): string
    {
        return $this->operationName;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): OpenTracingSpanContext
    {
        return $this->spanContext;
    }

    /**
     * {@inheritdoc}
     */
    public function finish($finishTime = null): void
    {
        $this->finishTime = null == $finishTime ? $this->microtimeToInt() : $finishTime;
        $this->duration = $this->finishTime - $this->startTime;
    }

    /**
     * {@inheritdoc}
     */
    public function overwriteOperationName(string $newOperationName): void
    {
        $this->operationName = $newOperationName;
    }

    /**
     * {@inheritdoc}
     */
    public function setTag(string $key, $value): void
    {
        $this->tags[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function log(array $fields = [], $timestamp = null): void
    {
        $log['timestamp'] = $timestamp ?: $this->microtimeToInt();
        $log['fields'] = $fields;
        $this->logs[] = $log;
    }

    /**
     * {@inheritdoc}
     */
    public function addBaggageItem(string $key, string $value): void
    {
        $this->log([
            'event' => 'baggage',
            'key' => $key,
            'value' => $value,
        ]);

        $this->spanContext->withBaggageItem($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getBaggageItem(string $key): ?string
    {
        return $this->spanContext->getBaggageItem($key);
    }

    private function microtimeToInt(): int
    {
        return (int) (microtime(true) * 1000000);
    }
}
