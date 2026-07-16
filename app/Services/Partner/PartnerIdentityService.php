<?php

namespace App\Services\Partner;

use App\Models\PartnerCatalog;
use App\Models\ResellerApiKey;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class PartnerIdentityService
{
    public function findCatalog(ResellerApiKey $resellerKey): ?PartnerCatalog
    {
        return $this->catalogQuery($resellerKey)->first();
    }

    public function resolveOrCreateCatalog(ResellerApiKey $resellerKey): PartnerCatalog
    {
        $identity = $this->identityAttributes($resellerKey);

        return PartnerCatalog::query()->firstOrCreate(
            $identity,
            ['is_active' => true],
        );
    }

    /**
     * @return array{user_id: int|null, seller_id: int|null}
     */
    public function identityAttributes(ResellerApiKey $resellerKey): array
    {
        if ($resellerKey->seller_id !== null) {
            return [
                'user_id' => null,
                'seller_id' => (int) $resellerKey->seller_id,
            ];
        }

        if ($resellerKey->user_id !== null) {
            return [
                'user_id' => (int) $resellerKey->user_id,
                'seller_id' => null,
            ];
        }

        throw new InvalidArgumentException('Partner API key is not linked to an account.');
    }

    private function catalogQuery(ResellerApiKey $resellerKey): Builder
    {
        $identity = $this->identityAttributes($resellerKey);

        return PartnerCatalog::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($identity): void {
                if ($identity['seller_id'] !== null) {
                    $query->where('seller_id', $identity['seller_id']);

                    return;
                }

                $query->where('user_id', $identity['user_id']);
            });
    }
}
