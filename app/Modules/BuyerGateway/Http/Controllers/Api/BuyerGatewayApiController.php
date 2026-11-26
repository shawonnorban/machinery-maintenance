<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Http\Controllers\Api;

use App\Modules\BuyerGateway\Support\PortalBook;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buyer Gateway (AnnotechERP 25): the pickers its screens share — the
 * company's buyers, countries and staff. The lists themselves are ERP
 * resources (`/erp/bg-*`).
 */
class BuyerGatewayApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allowEither('buyergateway.access.view', 'buyergateway.access.manage', 'buyergateway.visibility.view', 'buyergateway.visibility.manage', 'buyergateway.billing.view', 'buyergateway.billing.manage', 'buyergateway.engagement.view', 'buyergateway.engagement.manage', 'buyergateway.admin.view', 'buyergateway.admin.manage');
        $pairs = fn (array $map) => array_map(fn ($k, $v) => ['value' => (string) $k, 'label' => $v], array_keys($map), array_values($map));

        return ApiResponse::ok([
            'buyers' => $pairs(PortalBook::buyers()), 'countries' => $pairs(PortalBook::countries()), 'staff' => $pairs(PortalBook::staff()), 'products' => $pairs(PortalBook::products()), 'seasons' => array_map(fn ($x) => ['value' => $x, 'label' => $x], PortalBook::seasons()), 'periods' => $pairs(PortalBook::periods()),
            'can_manage' => $this->caller()->can('buyergateway.access.manage'), 'can_manage_visibility' => $this->caller()->can('buyergateway.visibility.manage'),
        ]);
    }

    /** Shared Document Vault: a file for a document released to a buyer; its download path goes on the document. */
    public function documentFile(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('buyergateway.visibility.manage');
        $request->validate(['file' => ['required', 'file']]);
        $file = $files->handle($request->file('file'), 'bg_shared_document', $this->caller()->companyId, $this->caller()->auditUserId());

        return ApiResponse::created(['id' => $file->id, 'path' => "/api/files/{$file->id}/download", 'size_mb' => round(((int) $file->size_bytes) / 1048576, 1)]);
    }

    /** Portal Branding Setup: a buyer's logo (PNG, JPEG or WebP; no SVG, which can carry script); its download path goes on the profile. */
    public function logoFile(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('buyergateway.admin.manage');
        $request->validate(['file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048']], ['file.mimes' => __('buyergateway.v.logo_type'), 'file.max' => __('buyergateway.v.logo_size')]);
        $file = $files->handle($request->file('file'), 'bg_portal_logo', $this->caller()->companyId, $this->caller()->auditUserId());

        return ApiResponse::created(['id' => $file->id, 'path' => "/api/files/{$file->id}/download", 'name' => $file->original_name]);
    }
}
