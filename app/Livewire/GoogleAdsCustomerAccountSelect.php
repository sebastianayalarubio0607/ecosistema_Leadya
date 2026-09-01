<?php

namespace App\Livewire;

use App\Http\Services\GoogleAds\GoogleAdsConversionTemplateService;
use Livewire\Component;

class GoogleAdsCustomerAccountSelect extends Component
{
    public ?string $selectedAccountId = null;
    public bool $readonly = false;
    public array $accounts = [];
    public ?string $errorMessage = null;
    public ?string $loadedAt = null;

    public function mount(?string $selectedAccountId = null, bool $readonly = false): void
    {
        $this->selectedAccountId = $this->normalizeCustomerId($selectedAccountId);
        $this->readonly = $readonly;

        if ($this->selectedAccountId !== '') {
            $this->accounts = [$this->fallbackAccount($this->selectedAccountId)];
        }
    }

    public function updatedSelectedAccountId(?string $value): void
    {
        $this->selectedAccountId = $this->normalizeCustomerId($value);
    }

    public function loadAccounts(): void
    {
        $this->errorMessage = null;

        $result = app(GoogleAdsConversionTemplateService::class)->listCustomerAccounts();

        if (! ($result['success'] ?? false)) {
            $this->errorMessage = $result['error_message'] ?? 'No fue posible consultar Google Ads.';
            $this->ensureSelectedFallback();
            return;
        }

        $this->accounts = $result['accounts'] ?? [];
        $this->ensureSelectedFallback();
        $this->loadedAt = now()->format('Y-m-d H:i');
    }

    public function render()
    {
        $this->selectedAccountId = $this->normalizeCustomerId($this->selectedAccountId);

        return view('livewire.google-ads-customer-account-select', [
            'selectedAccount' => $this->selectedAccount(),
            'hasLoadedAccounts' => count($this->accounts) > 0
                && ! (count($this->accounts) === 1 && ($this->accounts[0]['is_fallback'] ?? false)),
        ]);
    }

    private function selectedAccount(): ?array
    {
        $selectedAccountId = $this->normalizeCustomerId($this->selectedAccountId);

        if ($selectedAccountId === '') {
            return null;
        }

        foreach ($this->accounts as $account) {
            if ((string) ($account['id'] ?? '') === $selectedAccountId) {
                return $account;
            }
        }

        return $this->fallbackAccount($selectedAccountId);
    }

    private function ensureSelectedFallback(): void
    {
        $selectedAccountId = $this->normalizeCustomerId($this->selectedAccountId);

        if ($selectedAccountId === '') {
            return;
        }

        foreach ($this->accounts as $account) {
            if ((string) ($account['id'] ?? '') === $selectedAccountId) {
                return;
            }
        }

        array_unshift($this->accounts, $this->fallbackAccount($selectedAccountId));
    }

    private function fallbackAccount(string $id): array
    {
        return [
            'id' => $id,
            'resource_name' => '',
            'descriptive_name' => 'ID guardado',
            'currency_code' => '',
            'time_zone' => '',
            'level' => 0,
            'manager' => false,
            'status' => '',
            'is_fallback' => true,
        ];
    }

    private function normalizeCustomerId(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
