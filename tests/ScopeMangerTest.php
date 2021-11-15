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

use Jaeger\ScopeManager;
use Jaeger\Span;
use OpenTracing\NoopSpanContext;
use PHPUnit\Framework\TestCase;

class ScopeMangerTest extends TestCase
{
    public function testActivate(): void
    {
        $span1 = new Span('test', new NoopSpanContext(), []);

        $scopeManager = new ScopeManager();
        $scope = $scopeManager->activate($span1, true);
        $span2 = $scope->getSpan();

        static::assertSame($span1, $span2);
    }

    public function testGetActive(): void
    {
        $span = new Span('test', new NoopSpanContext(), []);

        $scopeManager = new ScopeManager();
        $scope1 = $scopeManager->activate($span, true);

        $scope2 = $scopeManager->getActive();
        static::assertSame($scope1, $scope2);
    }

    public function testDelActive(): void
    {
        $span = new Span('test', new NoopSpanContext(), []);

        $scopeManager = new ScopeManager();
        $scope = $scopeManager->activate($span, true);

        $res = $scopeManager->deactivate($scope);
        static::assertTrue($res);

        $getRes = $scopeManager->getActive();
        static::assertNull($getRes);
    }

    public function testDelActiveNestedScopes(): void
    {
        $scopeManager = new ScopeManager();
        $span1 = new Span('Z', new NoopSpanContext(), []);
        $scope1 = $scopeManager->activate($span1, true);
        $span2 = new Span('Y', new NoopSpanContext(), []);
        $scope2 = $scopeManager->activate($span2, true);
        $span3 = new Span('X', new NoopSpanContext(), []);
        $scope3 = $scopeManager->activate($span3, true);

        $active = $scopeManager->getActive();
        static::assertSame($active, $scope3);

        $res = $scopeManager->deactivate($scope3);
        static::assertTrue($res);
        $active = $scopeManager->getActive();
        static::assertSame($active, $scope2);

        $res = $scopeManager->deactivate($scope2);
        static::assertTrue($res);
        $active = $scopeManager->getActive();
        static::assertSame($active, $scope1);

        $res = $scopeManager->deactivate($scope1);
        static::assertTrue($res);
        $active = $scopeManager->getActive();
        static::assertNull($active);
    }

    public function testDelActiveReNestScopes(): void
    {
        $scopeManager = new ScopeManager();
        $span1 = new Span('A', new NoopSpanContext(), []);
        $scope1 = $scopeManager->activate($span1, true);
        $span2 = new Span('B', new NoopSpanContext(), []);
        $scope2 = $scopeManager->activate($span2, true);

        $active = $scopeManager->getActive();
        static::assertSame($active, $scope2);

        // Remove scope2 so that scope1 is active
        $scopeManager->deactivate($scope2);
        $active = $scopeManager->getActive();
        static::assertSame($active, $scope1);

        // Add a new active scope3
        $span3 = new Span('C', new NoopSpanContext(), []);
        $scope3 = $scopeManager->activate($span3, true);
        $active = $scopeManager->getActive();
        static::assertSame($active, $scope3);

        // Delete active scope3
        $scopeManager->deactivate($scope3);
        $active = $scopeManager->getActive();
        static::assertSame($active, $scope1);

        $scopeManager->deactivate($scope1);
        $active = $scopeManager->getActive();
        static::assertNull($active);
    }
}
