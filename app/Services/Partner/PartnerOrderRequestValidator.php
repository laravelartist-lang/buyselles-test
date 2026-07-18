<?php

namespace App\Services\Partner;

use App\Models\PartnerCatalogItem;
use App\Models\ResellerApiKey;
use App\Services\DirectTopUp\DirectTopUpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PartnerOrderRequestValidator
{
    /**
     * @var array<int, string>
     */
    private const NORMALIZABLE_FIELDS = [
        'supplier_denomination_id',
        'custom_amount',
        'expected_total',
        'direct_topup_account_id',
        'reference',
    ];

    public function __construct(
        private readonly PartnerProductCatalogQuery $catalogQuery,
        private readonly PartnerOrderRequirementsResolver $requirementsResolver,
        private readonly DirectTopUpService $directTopUpService,
    ) {}

    public function mergeNormalizedRequest(Request $request): Request
    {
        $request->merge($this->normalizeInput($request->all()));

        return $request;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeInput(array $input): array
    {
        foreach (self::NORMALIZABLE_FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];

            if ($value === null || $value === '') {
                $input[$field] = null;
            }
        }

        return $input;
    }

    /**
     * @return array{error?: string, errors?: array<string, mixed>, status?: int}|null
     */
    public function validateQuoteStructure(array $input): ?array
    {
        $validator = Validator::make($input, [
            'quantity' => 'nullable|integer|min:1|max:100',
            'supplier_denomination_id' => 'nullable|integer|exists:supplier_product_denominations,id',
            'custom_amount' => 'nullable|numeric|min:0.0000000001',
        ]);

        if ($validator->fails()) {
            return [
                'errors' => $validator->errors()->toArray(),
                'status' => 422,
            ];
        }

        return null;
    }

    /**
     * @return array{error?: string, errors?: array<string, mixed>, status?: int}|null
     */
    public function validateOrderStructure(array $input): ?array
    {
        $validator = Validator::make($input, [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100',
            'supplier_denomination_id' => 'nullable|integer|exists:supplier_product_denominations,id',
            'custom_amount' => 'nullable|numeric|min:0.0000000001',
            'direct_topup_account_id' => 'nullable|string|max:255',
            'expected_total' => 'nullable|numeric|min:0.0000000001',
            'reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return [
                'errors' => $validator->errors()->toArray(),
                'status' => 422,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     catalog_item: PartnerCatalogItem,
     *     denomination_id: int|null,
     *     custom_amount: float|null,
     *     direct_topup_account_id: string|null,
     *     expected_total: float|null,
     *     reference: string|null,
     *     quantity: int
     * }|array{error?: string, errors?: array<string, mixed>, status?: int}
     */
    public function validateQuoteForProduct(
        ResellerApiKey $resellerKey,
        int $productId,
        array $input,
    ): array {
        $input = $this->normalizeInput($input);

        if ($structureError = $this->validateQuoteStructure($input)) {
            return $structureError;
        }

        $catalogItem = $this->catalogQuery->findEligibleItem($resellerKey, $productId);

        if ($catalogItem === null) {
            return [
                'error' => 'Product not found.',
                'status' => 404,
            ];
        }

        return $this->validateSemanticPayload(
            catalogItem: $catalogItem,
            input: $input,
            context: 'quote',
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     catalog_item: PartnerCatalogItem,
     *     product_id: int,
     *     denomination_id: int|null,
     *     custom_amount: float|null,
     *     direct_topup_account_id: string|null,
     *     expected_total: float|null,
     *     reference: string|null,
     *     quantity: int
     * }|array{error?: string, errors?: array<string, mixed>, status?: int}
     */
    public function validateOrderPayload(
        ResellerApiKey $resellerKey,
        array $input,
    ): array {
        $input = $this->normalizeInput($input);

        if ($structureError = $this->validateOrderStructure($input)) {
            return $structureError;
        }

        $productId = (int) $input['product_id'];
        $catalogItem = $this->catalogQuery->findEligibleItem($resellerKey, $productId);

        if ($catalogItem === null) {
            return [
                'error' => 'Product is not available in this partner catalog.',
                'status' => 404,
            ];
        }

        return $this->validateSemanticPayload(
            catalogItem: $catalogItem,
            input: $input,
            context: 'order',
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateSemanticPayload(
        PartnerCatalogItem $catalogItem,
        array $input,
        string $context,
    ): array {
        $denominationId = isset($input['supplier_denomination_id'])
            ? (int) $input['supplier_denomination_id']
            : null;
        $customAmount = isset($input['custom_amount'])
            ? (float) $input['custom_amount']
            : null;
        $directTopUpAccountId = isset($input['direct_topup_account_id'])
            ? trim((string) $input['direct_topup_account_id'])
            : null;
        $quantity = (int) ($input['quantity'] ?? 1);

        $requiredFields = $this->requirementsResolver->requiredFieldsForRequest(
            catalogItem: $catalogItem,
            denominationId: $denominationId,
        );

        $fieldValues = [
            'product_id' => $catalogItem->product_id,
            'quantity' => $quantity,
            'supplier_denomination_id' => $denominationId,
            'custom_amount' => $customAmount,
            'direct_topup_account_id' => $directTopUpAccountId !== '' ? $directTopUpAccountId : null,
            'reference' => $input['reference'] ?? null,
            'expected_total' => isset($input['expected_total']) ? (float) $input['expected_total'] : null,
        ];

        $errors = [];

        foreach ($requiredFields as $field) {
            if ($field === 'product_id') {
                continue;
            }

            if ($this->isMissingRequiredValue($field, $fieldValues)) {
                $errors[$field][] = $this->missingFieldMessage($field);
            }
        }

        foreach ($this->requirementsResolver->notApplicableFieldsWhenProvided(
            catalogItem: $catalogItem,
            denominationId: $denominationId,
        ) as $field) {
            if ($this->fieldWasProvided($field, $fieldValues)) {
                $errors[$field][] = "{$field} is not applicable for this product.";
            }
        }

        if ($this->requirementsResolver->isDirectTopUpProduct($catalogItem) && $context === 'order') {
            $directTopUpErrors = $this->directTopUpService->validatePurchase(
                product: $catalogItem->product,
                accountId: (string) ($directTopUpAccountId ?? ''),
                quantity: $this->directTopUpService->resolveBundleQuantity($catalogItem->product),
            );

            foreach ($directTopUpErrors as $field => $message) {
                $errors[$field][] = $message;
            }
        }

        if ($errors !== []) {
            return [
                'errors' => $errors,
                'status' => 422,
            ];
        }

        return [
            'catalog_item' => $catalogItem,
            'product_id' => $catalogItem->product_id,
            'denomination_id' => $denominationId,
            'custom_amount' => $customAmount,
            'direct_topup_account_id' => $directTopUpAccountId,
            'expected_total' => $fieldValues['expected_total'],
            'reference' => $fieldValues['reference'],
            'quantity' => $quantity,
        ];
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    private function isMissingRequiredValue(string $field, array $fieldValues): bool
    {
        if (! array_key_exists($field, $fieldValues)) {
            return true;
        }

        $value = $fieldValues[$field];

        if ($value === null || $value === '') {
            return true;
        }

        if (in_array($field, ['quantity', 'supplier_denomination_id'], true) && (int) $value <= 0) {
            return true;
        }

        if ($field === 'custom_amount' && (float) $value <= 0) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    private function fieldWasProvided(string $field, array $fieldValues): bool
    {
        if (! array_key_exists($field, $fieldValues)) {
            return false;
        }

        $value = $fieldValues[$field];

        return $value !== null && $value !== '';
    }

    private function missingFieldMessage(string $field): string
    {
        return match ($field) {
            'direct_topup_account_id' => 'The direct top-up account ID is required for this product.',
            'supplier_denomination_id' => 'A supplier denomination is required for this product.',
            'custom_amount' => 'A custom amount is required for this denomination.',
            'quantity' => 'The quantity field is required.',
            default => "The {$field} field is required for this product.",
        };
    }
}
