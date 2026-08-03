<?php

namespace App\Http\Services\GeneralLeads;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

class GeneralLeadsLeadQuery
{
    public function base(GeneralLeadsFilters $filters): Builder
    {
        return $this->apply(Lead::query()->from('leads')->whereBetween('leads.created_at', [$filters->from, $filters->to]), $filters);
    }

    public function apply(Builder $query, GeneralLeadsFilters $filters, array $except = []): Builder
    {
        if ($filters->customerId && ! in_array('customer_id', $except, true)) {
            $query->where('leads.customer_id', $filters->customerId);
        }
        if ($filters->integrationId && ! in_array('integration_id', $except, true)) {
            $query->where('leads.integration_id', $filters->integrationId);
        }
        if ($filters->source && ! in_array('source', $except, true)) {
            $this->sourceFilter($query, $filters->source);
        }
        if ($filters->campaignOrigin && ! in_array('campaign_origin', $except, true)) {
            $this->textFilter($query, 'leads.campaign_origin', $filters->campaignOrigin, 'origins');
        }
        if ($filters->platform && ! in_array('plataforma', $except, true)) {
            $this->textFilter($query, 'leads.plataforma', $filters->platform, 'platforms');
        }
        if ($filters->crmState && ! in_array('crm_state', $except, true)) {
            $query->where('leads.crm_state', $filters->crmState);
        }
        if ($filters->qualification && ! in_array('qualification', $except, true)) {
            $query->whereIn('leads.crm_state', fn ($sub) => $sub->from('crm_state')->select('id')->where('qualification', $filters->qualification));
        }
        if ($filters->language && ! in_array('lenguaje', $except, true)) {
            $query->where('leads.lenguaje', $filters->language);
        }
        if ($filters->geo && ! in_array('geo', $except, true)) {
            $query->where('leads.geo', $filters->geo);
        }

        return $query;
    }

    private function textFilter(Builder $query, string $column, string $value, string $catalogTable): void
    {
        if (str_starts_with($value, '__NULL_')) {
            $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''));

            return;
        }

        if (str_starts_with($value, '__MISSING_')) {
            $query->whereNotNull($column)
                ->where($column, '!=', '')
                ->whereNotIn($column, fn ($sub) => $sub->from($catalogTable)->select('code')->whereNotNull('code'));

            return;
        }

        $query->where($column, $value);
    }

    private function sourceFilter(Builder $query, string $value): void
    {
        if (str_starts_with($value, '__NULL_')) {
            $query->where(fn ($q) => $q
                ->whereNull('leads.campaign_origin')
                ->orWhere('leads.campaign_origin', '')
                ->orWhereNotIn('leads.campaign_origin', fn ($sub) => $sub->from('origins')->select('code')->whereNotNull('source_id')));

            return;
        }

        if (str_starts_with($value, '__MISSING_')) {
            $query->whereNotNull('leads.campaign_origin')
                ->where('leads.campaign_origin', '!=', '')
                ->whereNotIn('leads.campaign_origin', fn ($sub) => $sub->from('origins')->select('code')->whereNotNull('source_id'));

            return;
        }

        $query->whereIn('leads.campaign_origin', fn ($sub) => $sub->from('origins')->select('code')->where('source_id', $value));
    }
}
