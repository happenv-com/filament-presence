<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Data;

final readonly class PresenceMember
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $avatarUrl = null,
        public ?string $profileUrl = null,
        public string $state = 'viewing',
    ) {}

    /**
     * @return array{id: string, name: string, avatarUrl: string|null, profileUrl: string|null, state: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatarUrl' => $this->avatarUrl,
            'profileUrl' => $this->profileUrl,
            'state' => $this->state,
        ];
    }
}
