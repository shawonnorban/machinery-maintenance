<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\SharedDocument;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Shared Document Vault (AnnotechERP 25/02): tech packs, test reports and
 * shipping documents released to a buyer — kind, shared on and by, size,
 * downloads and state (shared, withdrawn, expired). Releasing is a decision:
 * it can be withdrawn. An attached file gives the document its size; the
 * file must be one uploaded for the vault.
 */
class SharedDocumentResource extends BuyerGatewayResource
{
    private const FILE = '#^/api/files/([0-9a-z]{26})/download$#i';

    protected function area(): string
    {
        return 'visibility';
    }

    public function key(): string
    {
        return 'bg-shared-documents';
    }

    public function model(): string
    {
        return SharedDocument::class;
    }

    public function columns(): array
    {
        return ['title', 'buyer_id', 'kind', 'shared_on', 'shared_by', 'size_mb', 'downloads', 'state', 'file_path'];
    }

    public function defaults(): array
    {
        return ['state' => 'SHARED', 'downloads' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:160'],
            'buyer_id' => $this->buyerRule(! $row),
            'kind' => ['nullable', Rule::in(PortalBook::DOCUMENT_KINDS)],
            'shared_on' => ['nullable', 'date_format:Y-m-d'],
            'shared_by' => ['nullable', 'string', Rule::exists('company_users', 'user_id')->where('company_id', $this->company())],
            'size_mb' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'downloads' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'state' => ['sometimes', Rule::in(PortalBook::DOCUMENT_STATES)],
            'file_path' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $v, Closure $fail): void {
                if ($v && ! $this->file((string) $v)) {
                    $fail(__('buyergateway.v.file'));
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['title.required' => __('buyergateway.v.document'), 'buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
        }
        if (! empty($data['file_path']) && ($file = $this->file($data['file_path']))) {
            $data['size_mb'] = round(((int) $file->size_bytes) / 1048576, 1);
        }
        if (! $row) {
            $data['shared_on'] ??= CarbonImmutable::now('Asia/Dhaka')->toDateString();
            $data['shared_by'] ??= $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        return parent::present($row) + ['shared_by_name' => PortalBook::staff()[$row->getAttribute('shared_by')] ?? null];
    }

    /** The vault's own upload behind a download path, in this company. */
    private function file(string $path): ?object
    {
        if (! preg_match(self::FILE, $path, $m)) {
            return null;
        }

        return DB::table('file_attachments')->whereRaw('lower(id) = ?', [strtolower($m[1])])->where('company_id', $this->company())->where('attachable_type', 'bg_shared_document')->first(['id', 'size_bytes']);
    }
}
