<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\BrandingProfile;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Portal Branding Setup (AnnotechERP 25/05): the logo, theme, accent and
 * wording a buyer sees when the portal opens. One profile per buyer; a buyer
 * without a live one gets the group default. The logo is a file name or an
 * uploaded image of this company's; the profile is stamped with the day it
 * was last changed.
 */
class BrandingProfileResource extends BuyerGatewayResource
{
    /** An uploaded logo's download path. */
    private const FILE = '#^/api/files/([0-9A-Za-z]{26})/download$#';

    protected function area(): string
    {
        return 'admin';
    }

    public function key(): string
    {
        return 'bg-branding-profiles';
    }

    public function model(): string
    {
        return BrandingProfile::class;
    }

    public function columns(): array
    {
        return ['buyer_id', 'theme', 'accent_slot', 'logo_file', 'welcome_line', 'support_email', 'updated_on', 'live'];
    }

    public function booleans(): array
    {
        return ['live'];
    }

    public function defaults(): array
    {
        return ['theme' => 'LIGHT', 'accent_slot' => 1, 'live' => true];
    }

    public function rules(?Model $row): array
    {
        return [
            'buyer_id' => [...$this->buyerRule(! $row), $this->unique($row, ['buyer_id'], 'buyergateway.v.profile_taken')],
            'theme' => ['sometimes', Rule::in(PortalBook::THEMES)],
            'accent_slot' => ['sometimes', 'integer', 'min:1', 'max:'.PortalBook::ACCENT_SLOTS],
            'logo_file' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $v, Closure $fail): void {
                if (str_starts_with((string) $v, '/api/') && ! $this->file((string) $v)) {
                    $fail(__('buyergateway.v.logo_file'));
                }
            }],
            'welcome_line' => ['nullable', 'string', 'max:160'],
            'support_email' => ['nullable', 'email:rfc', 'max:160'],
            'updated_on' => ['nullable', 'date_format:Y-m-d'],
            'live' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['logo_file', 'welcome_line', 'support_email'] as $f) {
            if (isset($data[$f])) {
                $data[$f] = trim($data[$f]) ?: null;
            }
        }
        // Any change is stamped with today, unless the day is given.
        if (empty($data['updated_on'])) {
            $data['updated_on'] = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        }

        return $data;
    }

    /** The logo by name — an upload's own file name — and, for an upload, where it opens. */
    public function present(Model $row): array
    {
        $out = parent::present($row);
        $logo = (string) $out['logo_file'];
        $file = str_starts_with($logo, '/api/') ? $this->file($logo) : null;
        $out['logo_name'] = $file->original_name ?? ($logo !== '' ? $logo : null);
        $out['logo_url'] = $file ? $logo : null;

        return $out;
    }

    /** A logo uploaded for this screen, in this company. */
    private function file(string $path): ?object
    {
        if (! preg_match(self::FILE, $path, $m)) {
            return null;
        }

        return DB::table('file_attachments')->whereRaw('lower(id) = ?', [strtolower($m[1])])->where('company_id', $this->company())->where('attachable_type', 'bg_portal_logo')->first(['id', 'original_name']);
    }
}
