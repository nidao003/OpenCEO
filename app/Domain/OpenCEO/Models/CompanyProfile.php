<?php

namespace Leantime\Domain\OpenCEO\Models;

class CompanyProfile
{
    public function __construct(
        public ?int $id = null,
        public string $name = '',
        public string $background = '',
        public string $businessModel = '',
        public string $mission = '',
        public string $vision = '',
        public array $strategicFocus = [],
        public array $context = [],
    ) {}
}
