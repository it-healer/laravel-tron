<?php

namespace ItHealer\LaravelTron\Api\DTO;

class TransferSendDTO
{
    public function __construct(
        public readonly string $txid,
        public readonly TransferPreviewDTO $preview,
        public readonly ?int $expiration = null,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'txid' => $this->txid,
            'preview' => $this->preview->toArray(),
            'expiration' => $this->expiration,
        ];
    }
}
