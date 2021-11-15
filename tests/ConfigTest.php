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

use Jaeger\Config;
use Jaeger\Reporter\NullReporter;
use OpenTracing\NoopTracer;
use OpenTracing\Tracer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConfigTest extends TestCase
{
    public function testSetDisabled(): void
    {
        $config = Config::getInstance();
        $config->setDisabled(true);

        static::assertEquals(true, $config::$disabled);
    }

    public function testNoopTracer(): void
    {
        $config = Config::getInstance();
        $config->setDisabled(true);
        $trace = $config->initTracer('test');

        static::assertInstanceOf(NoopTracer::class, $trace);
    }

    public function testflushMulTracer(): void
    {
        $report = new NullReporter();
        $config = Config::getInstance();
        $config->setDisabled(false);
        $config->setReporter($report);
        $tracer1 = $config->initTracer('tracer1', 'localhost:1');
        static::assertInstanceOf(Tracer::class, $tracer1);
        $tracer2 = $config->initTracer('tracer2', 'localhost:2');
        static::assertInstanceOf(Tracer::class, $tracer2);
        static::assertTrue($config->flush());
    }

    public function testEmptyServiceName(): void
    {
        $this->expectException(RuntimeException::class);

        $report = new NullReporter();
        $config = Config::getInstance();
        $config->setDisabled(false);
        $config->setReporter($report);
        $config->initTracer('');
    }
}
