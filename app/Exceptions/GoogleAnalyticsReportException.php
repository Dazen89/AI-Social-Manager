<?php

namespace App\Exceptions;

class GoogleAnalyticsReportException extends GoogleAnalyticsException
{
    public function __construct(
        string $message,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionLabel = null,
    ) {
        parent::__construct($message);
    }
}
