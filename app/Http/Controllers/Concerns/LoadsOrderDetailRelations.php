<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PlatformOrder;
use App\Models\PlatformOrderDocument;
use App\Services\Order\OrderDocumentService;
use App\Services\Order\OrderStatusWorkflowService;
use Illuminate\Http\Request;

trait LoadsOrderDetailRelations
{
    /**
     * @return list<string>
     */
    protected function orderDetailRelations(): array
    {
        return [
            'items.product.images',
            'items.warehouse',
            'responsibleContact',
            'manufacturerResponsibleContact',
            'claims.claimStatus',
            'distributorProfile.contacts',
            'distributorProfile.regions',
            'distributorProfile.warehouses',
            'distributorProfile.user',
            'endCompanyProfile.contacts',
            'endCompanyProfile.user',
            'deliveryMethod',
            'transportCompany',
            'deliveryAddress.region',
            'deliveryAddress.contact',
            'documents.uploadedBy',
            'statusLogs.performedBy',
            'pausedBy',
        ];
    }

    /**
     * @return array{
     *     can_mutate_documents: bool,
     *     document_types: array<string, string>,
     *     document_store_route: ?string,
     *     document_download_route: string,
     *     document_preview_route: ?string,
     *     document_destroy_route: ?string,
     *     uploader_role: string,
     *     show_product_links: bool,
     *     action_labels: array<string, string>
     * }
     */
    protected function documentUiContext(
        Request $request,
        PlatformOrder $order,
        string $uploaderRole,
        OrderDocumentService $documents,
        string $downloadRouteName,
        ?string $storeRouteName = null,
        ?string $destroyRouteName = null,
        ?string $previewRouteName = null,
    ): array {
        return [
            'can_mutate_documents' => $storeRouteName !== null && $documents->canMutate($order, $uploaderRole),
            'document_types' => $documents->availableTypesFor($uploaderRole),
            'document_store_route' => $storeRouteName ? route($storeRouteName, $order) : null,
            'document_download_route' => $downloadRouteName,
            'document_preview_route' => $previewRouteName,
            'document_destroy_route' => $destroyRouteName,
            'uploader_role' => $uploaderRole,
            'show_product_links' => true,
            'action_labels' => OrderStatusWorkflowService::actionLabels(),
        ];
    }
}
