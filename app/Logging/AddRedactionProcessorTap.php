<?php

namespace App\Logging;

use Illuminate\Log\Logger;

final class AddRedactionProcessorTap
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new RedactSensitiveLogProcessor);
    }
}
