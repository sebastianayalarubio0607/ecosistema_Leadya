<?php

namespace App\Http\Services\Meta\Subscription\Whatsapp;

use App\Models\MetaAccessToken;

class MetaWhatsappCredential
{
    public function __construct(
        public readonly MetaAccessToken $accessToken,
        public readonly string $token,
        public readonly string $metaAppId,
        public readonly string $source,
        public readonly ?int $customerId = null,
    ) {}
}
