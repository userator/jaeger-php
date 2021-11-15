<?php

namespace Jaeger;

use OpenTracing\Scope as OpentracingScope;
use OpenTracing\ScopeManager as OpentracingScopeManager;
use OpenTracing\Span as OpentracingSpan;

class ScopeManager implements OpentracingScopeManager
{
    private $scopes = [];

    /**
     * {@inheritdoc}
     *
     * @param Span $span
     *
     * @return Scope
     */
    public function activate(OpentracingSpan $span, bool $finishSpanOnClose = self::DEFAULT_FINISH_SPAN_ON_CLOSE): OpentracingScope
    {
        $scope = new Scope($this, $span, $finishSpanOnClose);
        $this->scopes[] = $scope;

        return $scope;
    }

    /**
     * {@inheritdoc}
     *
     * @return Scope
     */
    public function getActive(): ?OpentracingScope
    {
        if (empty($this->scopes)) {
            return null;
        }

        return $this->scopes[count($this->scopes) - 1];
    }

    public function deactivate(Scope $scope): bool
    {
        $scopeLength = count($this->scopes);

        if ($scopeLength <= 0) {
            return false;
        }

        for ($i = 0; $i < $scopeLength; ++$i) {
            if ($scope === $this->scopes[$i]) {
                array_splice($this->scopes, $i, 1);
            }
        }

        return true;
    }
}
