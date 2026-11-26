<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

final class ValidationResult
{
    /**
     * @param array<string, string> $values bereinigte Eingaben zum erneuten Anzeigen
     * @param array<string, array{0: string, 1: array<string, string|int>}> $errors Feld => [Textschlüssel, Parameter]
     */
    public function __construct(
        public readonly array $values,
        public readonly array $errors,
        public readonly ?Order $order,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [] && $this->order !== null;
    }
}
