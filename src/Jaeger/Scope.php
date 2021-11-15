<?php

namespace Jaeger;

class Scope implements \OpenTracing\Scope
{
    /**
     * @var ScopeManager
     */
    private $scopeManager;

    /**
     * @var Span
     */
    private $span;

    /**
     * @var bool
     */
    private $finishSpanOnClose;

    /**
     * Scope constructor.
     *
     * @param bool $finishSpanOnClose
     */
    public function __construct(ScopeManager $scopeManager, Span $span, $finishSpanOnClose)
    {
        $this->scopeManager = $scopeManager;
        $this->span = $span;
        $this->finishSpanOnClose = $finishSpanOnClose;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->finishSpanOnClose) {
            $this->span->finish();
        }

        $this->scopeManager->deactivate($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getSpan(): \OpenTracing\Span
    {
        return $this->span;
    }
}
